<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Support\Database;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 플로팅 인증 API 통합 테스트 — 활성화→유효성→분석 차감 전체 플로우.
 *
 * TEMPORARY 테이블로 자체 완결(커넥션 종료 시 자동 삭제).
 *
 * @internal
 */
final class FloatingApiTest extends TestCase
{
    private const string SECRET = 'floating-api-secret-0123456789-abcd';

    private App $app;
    private PDO $pdo;
    private string $token;

    protected function setUp(): void
    {
        $this->app = App::create([
            'APP_ENV'            => 'testing',
            'DB_HOST'            => getenv('DB_HOST') ?: 'localhost',
            'DB_PORT'            => getenv('DB_PORT') ?: '3306',
            'DB_NAME'            => getenv('DB_NAME') ?: 'ailicet',
            'DB_USER'            => getenv('DB_USER') ?: 'ailicet',
            'DB_PASS'            => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'licet_qostm!1',
            'JWT_SECRET'         => self::SECRET,
            'RATE_LIMIT_ENABLED' => 'false',
        ]);

        /** @var Database $db */
        $db          = $this->app->container()->get(Database::class);
        $this->pdo   = $db->pdo();
        $this->seed();
        $this->token = $this->makeToken();
    }

    private function seed(): void
    {
        $this->pdo->exec(
            'CREATE TEMPORARY TABLE licenses (
                id INT PRIMARY KEY, license_type VARCHAR(20), period_code VARCHAR(30), status VARCHAR(20),
                version VARCHAR(30), host_id VARCHAR(64), expire_date DATE, support_end_date DATE,
                activate_term INT, check_term INT, config JSON, deleted_at DATETIME NULL
            )',
        );
        $this->pdo->exec('CREATE TEMPORARY TABLE license_history (id INT PRIMARY KEY, license_id INT, type VARCHAR(20), license_key VARCHAR(64))');
        $this->pdo->exec('CREATE TEMPORARY TABLE floating_activations (id INT AUTO_INCREMENT PRIMARY KEY, license_id INT, host_id VARCHAR(64), activated_at DATETIME, expires_at DATETIME, created_at DATETIME)');
        $this->pdo->exec('CREATE TEMPORARY TABLE analysis_logs (id INT AUTO_INCREMENT PRIMARY KEY, license_id INT, analysis_key VARCHAR(64) UNIQUE, host_id VARCHAR(64), status VARCHAR(20), amount INT, created_at DATETIME, ended_at DATETIME)');

        // 1: 크레딧제(100)
        $this->pdo->exec("INSERT INTO licenses VALUES (1,'floating','perpetual_credit','active','1.0',NULL,NULL,NULL,24,30,'{\"limits\":{\"credit\":100}}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (1,1,'issue','KEY-F1')");
        // 2: 카운트제(3)
        $this->pdo->exec("INSERT INTO licenses VALUES (2,'floating','perpetual_count','active','1.0',NULL,NULL,NULL,24,30,'{\"limits\":{\"count\":3}}',NULL)");
        $this->pdo->exec("INSERT INTO license_history VALUES (2,2,'issue','KEY-CNT')");
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
     *
     * @return array<string, mixed>
     */
    private function call(string $path, array $body): array
    {
        $req = (new Psr17Factory())->createServerRequest('POST', $path, ['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $this->token);
        $req->getBody()->write((string) json_encode($body));

        $res  = $this->app->handle($req);
        $data = json_decode((string) $res->getBody(), true);

        return ['status' => $res->getStatusCode(), 'body' => is_array($data) ? $data : []];
    }

    public function testActivationIssuesToken(): void
    {
        $r = $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['body']['data']['activated']);
        $this->assertArrayHasKey('token', $r['body']['data']);
        $this->assertSame(30, $r['body']['data']['check_term']);
    }

    public function testDoubleActivationBlockedButSameHostAllowed(): void
    {
        $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);

        // 다른 호스트가 활성 구간 내 활성화 시도 → 차단
        $other = $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-B']);
        $this->assertFalse($other['body']['data']['activated']);
        $this->assertSame('ALREADY_ACTIVE', $other['body']['data']['reason']);

        // 같은 호스트 재활성화 → 허용
        $same = $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);
        $this->assertTrue($same['body']['data']['activated']);
    }

    public function testFullFlowCreditDeduction(): void
    {
        // 활성화
        $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);

        // 유효성 — 잔여 100
        $eff = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-F1']);
        $this->assertTrue($eff['body']['data']['valid']);
        $this->assertSame(100, $eff['body']['data']['remaining']['credit']);

        // 분석 시작 → 종료(크레딧 30 차감)
        $start = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);
        $ak    = $start['body']['data']['analysis_key'];
        $this->assertNotEmpty($ak);

        $end = $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => true, 'amount' => 30]);
        $this->assertTrue($end['body']['data']['deducted']);
        $this->assertSame(30, $end['body']['data']['amount']);

        // 유효성 재확인 — 잔여 70
        $eff2 = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-F1']);
        $this->assertSame(70, $eff2['body']['data']['remaining']['credit']);
    }

    public function testAnalysisEndIsIdempotent(): void
    {
        $this->call('/api/v1/floating/activation', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A']);
        $ak = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-F1', 'host_id' => 'HOST-A'])['body']['data']['analysis_key'];

        $first  = $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => true, 'amount' => 40]);
        $second = $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => true, 'amount' => 40]);

        $this->assertTrue($first['body']['data']['deducted']);
        $this->assertFalse($second['body']['data']['deducted']);        // 중복 차감 안 됨
        $this->assertSame('ALREADY_PROCESSED', $second['body']['data']['reason']);

        // 잔여는 40만 차감(60)
        $eff = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-F1']);
        $this->assertSame(60, $eff['body']['data']['remaining']['credit']);
    }

    public function testCountBasedDeduction(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $ak = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-CNT', 'host_id' => 'H'])['body']['data']['analysis_key'];
            $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => true]);
        }

        $eff = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-CNT']);
        $this->assertSame(1, $eff['body']['data']['remaining']['count']); // 3 - 2
    }

    public function testFailedAnalysisNoDeduction(): void
    {
        $ak  = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-CNT', 'host_id' => 'H'])['body']['data']['analysis_key'];
        $end = $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => false]);

        $this->assertFalse($end['body']['data']['deducted']);
        $eff = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-CNT']);
        $this->assertSame(3, $eff['body']['data']['remaining']['count']); // 미차감
    }

    public function testUsageExceededBlocksStart(): void
    {
        // 카운트 3 모두 소진
        for ($i = 0; $i < 3; $i++) {
            $ak = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-CNT', 'host_id' => 'H'])['body']['data']['analysis_key'];
            $this->call('/api/v1/floating/analysis/end', ['analysis_key' => $ak, 'success' => true]);
        }

        $eff = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'KEY-CNT']);
        $this->assertFalse($eff['body']['data']['valid']);
        $this->assertSame('USAGE_EXCEEDED', $eff['body']['data']['reason']);

        $start = $this->call('/api/v1/floating/analysis/start', ['license_key' => 'KEY-CNT', 'host_id' => 'H']);
        $this->assertFalse($start['body']['data']['started']);
        $this->assertSame('USAGE_EXCEEDED', $start['body']['data']['reason']);
    }

    public function testInvalidKey(): void
    {
        $r = $this->call('/api/v1/floating/effectiveness', ['license_key' => 'NOPE']);
        $this->assertSame('INVALID_LICENSE_KEY', $r['body']['data']['reason']);
    }
}
