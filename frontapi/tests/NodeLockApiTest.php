<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Support\Database;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * 노드락 인증 API 통합 테스트.
 *
 * TEMPORARY 테이블을 공유 커넥션에 만들어 실 스키마 없이도 자체 완결적으로 검증한다
 * (커넥션 종료 시 자동 삭제 → DB 오염 없음, CI 이식성 확보).
 *
 * @internal
 */
final class NodeLockApiTest extends TestCase
{
    private const string SECRET = 'nodelock-api-secret-0123456789-abcd';

    private App $app;
    private PDO $pdo;
    private string $token;
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/frontapi-nl-' . getmypid();

        $this->app = App::create([
            'APP_ENV'            => 'testing',
            'DB_HOST'            => getenv('DB_HOST') ?: 'localhost',
            'DB_PORT'            => getenv('DB_PORT') ?: '3306',
            'DB_NAME'            => getenv('DB_NAME') ?: 'ailicet',
            'DB_USER'            => getenv('DB_USER') ?: 'ailicet',
            'DB_PASS'            => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'licet_qostm!1',
            'JWT_SECRET'         => self::SECRET,
            'RATE_LIMIT_ENABLED' => 'false',
            'RAW_LOG_PATH'       => $this->logDir,
        ]);

        /** @var Database $db */
        $db        = $this->app->container()->get(Database::class);
        $this->pdo = $db->pdo();
        $this->seed();
        $this->token = $this->makeToken();
    }

    protected function tearDown(): void
    {
        // TEMPORARY 테이블은 커넥션 종료 시 자동 삭제. 로그 디렉토리만 정리.
        if (is_dir($this->logDir)) {
            array_map('unlink', glob($this->logDir . '/*') ?: []);
            @rmdir($this->logDir);
        }
    }

    private function seed(): void
    {
        $this->pdo->exec('CREATE TEMPORARY TABLE products (id INT PRIMARY KEY, name VARCHAR(100), product_code VARCHAR(30))');
        $this->pdo->exec(
            'CREATE TEMPORARY TABLE licenses (
                id INT PRIMARY KEY, product_id INT, license_type VARCHAR(20), period_code VARCHAR(30),
                status VARCHAR(20), version VARCHAR(30), host_id VARCHAR(64), expire_date DATE,
                support_end_date DATE, config JSON, deleted_at DATETIME NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TEMPORARY TABLE license_history (id INT PRIMARY KEY, license_id INT, type VARCHAR(20), license_key VARCHAR(64))',
        );

        $this->pdo->exec("INSERT INTO products VALUES (1, 'tES LAB', 'PT001')");

        // 1: 정상 노드락, host HOST-A, 무기한
        $this->pdo->exec("INSERT INTO licenses VALUES (1,1,'nodelock','perpetual','active','3.0','HOST-A',NULL,NULL,'{\"modules\":[\"MD001\"]}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (1,1,'issue','KEY-A1')");

        // 2: 재발급됨(현재키 KEY-NEW), host HOST-B
        $this->pdo->exec("INSERT INTO licenses VALUES (2,1,'nodelock','perpetual','active','3.0','HOST-B',NULL,NULL,'{}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (2,2,'issue','KEY-OLD')");
        $this->pdo->exec("INSERT INTO license_history VALUES (3,2,'reissue','KEY-NEW')");

        // 3: 종료됨
        $this->pdo->exec("INSERT INTO licenses VALUES (3,1,'nodelock','perpetual','terminated','3.0','HOST-C',NULL,NULL,'{}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (4,3,'issue','KEY-TERM')");

        // 4: 만료됨
        $this->pdo->exec("INSERT INTO licenses VALUES (4,1,'nodelock','period','active','3.0','HOST-D','2020-01-01',NULL,'{}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (5,4,'issue','KEY-EXP')");
    }

    private function makeToken(): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $h   = $b64((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p   = $b64((string) json_encode(['sub' => 1, 'role' => 3, 'exp' => time() + 300]));

        return "{$h}.{$p}." . $b64(hash_hmac('sha256', "{$h}.{$p}", self::SECRET, true));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body, bool $auth = true): ResponseInterface
    {
        $req = (new Psr17Factory())->createServerRequest('POST', $path, ['REMOTE_ADDR' => '127.0.0.1']);
        if ($auth) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $this->token);
        }
        $req->getBody()->write((string) json_encode($body));

        return $this->app->handle($req);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ResponseInterface $res): array
    {
        $body = json_decode((string) $res->getBody(), true);

        return is_array($body) ? $body : [];
    }

    public function testRequiresAuth(): void
    {
        $res = $this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-A1', 'host_id' => 'HOST-A'], false);
        $this->assertSame(401, $res->getStatusCode());
    }

    public function testGetLicenseInfo(): void
    {
        $res  = $this->post('/api/v1/licenses/info', ['license_key' => 'KEY-A1']);
        $body = $this->data($res);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('tES LAB', $body['data']['product']);
        $this->assertSame(['MD001'], $body['data']['modules']);
    }

    public function testInfoUnknownKeyReturns404(): void
    {
        $res = $this->post('/api/v1/licenses/info', ['license_key' => 'NOPE']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function testEffectivenessValid(): void
    {
        $body = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-A1', 'host_id' => 'HOST-A']));
        $this->assertTrue($body['data']['valid']);
        $this->assertSame('OK', $body['data']['reason']);
    }

    public function testEffectivenessHostMismatch(): void
    {
        $body = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-A1', 'host_id' => 'WRONG']));
        $this->assertFalse($body['data']['valid']);
        $this->assertSame('HOST_MISMATCH', $body['data']['reason']);
    }

    public function testEffectivenessInvalidKey(): void
    {
        $body = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'NOPE', 'host_id' => 'X']));
        $this->assertSame('INVALID_LICENSE_KEY', $body['data']['reason']);
    }

    public function testRevokedKeyRejected(): void
    {
        // 이전 키(KEY-OLD)는 재발급으로 폐기됨
        $old = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-OLD', 'host_id' => 'HOST-B']));
        $this->assertSame('REVOKED_KEY', $old['data']['reason']);

        // 현재 키(KEY-NEW)는 유효
        $new = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-NEW', 'host_id' => 'HOST-B']));
        $this->assertTrue($new['data']['valid']);
    }

    public function testTerminatedAndExpired(): void
    {
        $term = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-TERM', 'host_id' => 'HOST-C']));
        $this->assertSame('INACTIVE', $term['data']['reason']);

        $exp = $this->data($this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-EXP', 'host_id' => 'HOST-D']));
        $this->assertSame('EXPIRED', $exp['data']['reason']);
    }

    public function testBypassWritesRawLog(): void
    {
        $res = $this->post('/api/v1/licenses/bypass', ['license_key' => 'KEY-A1', 'host_id' => 'HOST-A', 'version' => '3.0']);
        $this->assertSame(202, $res->getStatusCode());

        $file = $this->logDir . '/' . gmdate('Y-m-d') . '.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('KEY-A1', (string) file_get_contents($file));
    }

    public function testValidationErrorOnMissingParams(): void
    {
        $res = $this->post('/api/v1/licenses/effectiveness', ['license_key' => 'KEY-A1']); // host_id 누락
        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame('VALIDATION_ERROR', $this->data($res)['code']);
    }

    public function testPublicVerifyEndpointWorksWithoutAuth(): void
    {
        $res  = $this->post('/api/v1/nodelock/verify', ['license_key' => 'KEY-A1', 'host_id' => 'HOST-A'], false);
        $body = $this->data($res);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($body['data']['valid']);
        $this->assertSame('OK', $body['data']['reason']);
    }

    public function testPublicVerifyEndpointHostMismatchWithoutAuth(): void
    {
        $body = $this->data($this->post('/api/v1/nodelock/verify', ['license_key' => 'KEY-A1', 'host_id' => 'WRONG'], false));

        $this->assertFalse($body['data']['valid']);
        $this->assertSame('HOST_MISMATCH', $body['data']['reason']);
    }
}
