<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

/**
 * frontApi 미들웨어 파이프라인 통합 테스트(헬스체크·인증·PDO·Rate Limit).
 *
 * @internal
 */
final class PipelineTest extends TestCase
{
    private const string SECRET = 'pipeline-secret-0123456789-abcdefgh';

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function env(array $overrides = []): array
    {
        return array_merge([
            'APP_ENV'            => 'testing', // InMemory rate limiter
            'DB_HOST'            => getenv('DB_HOST') ?: 'localhost',
            'DB_PORT'            => getenv('DB_PORT') ?: '3306',
            'DB_NAME'            => getenv('DB_NAME') ?: 'ailicet',
            'DB_USER'            => getenv('DB_USER') ?: 'ailicet',
            'DB_PASS'            => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'licet_qostm!1',
            'JWT_SECRET'         => self::SECRET,
            'RATE_LIMIT_ENABLED' => 'false',
            'RATE_LIMIT_MAX'     => '60',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $env
     */
    private function request(string $method, string $path, ?string $token = null, array $env = []): ResponseInterface
    {
        $factory = new Psr17Factory();
        $req     = $factory->createServerRequest($method, $path, ['REMOTE_ADDR' => '127.0.0.1']);
        if ($token !== null) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $token);
        }

        return App::create($this->env($env))->handle($req);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function token(array $claims): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $h   = $b64((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p   = $b64((string) json_encode($claims + ['exp' => time() + 300]));
        $s   = $b64(hash_hmac('sha256', $h . '.' . $p, self::SECRET, true));

        return "{$h}.{$p}.{$s}";
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ResponseInterface $res): array
    {
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function testHealthIsPublicAndReportsDb(): void
    {
        $res  = $this->request('GET', '/health');
        $body = $this->body($res);

        $this->assertContains($res->getStatusCode(), [200, 503]);
        $this->assertSame('success', $body['status']);
        $this->assertArrayHasKey('db', $body['data']);
    }

    public function testProtectedRouteRequiresToken(): void
    {
        $res = $this->request('GET', '/api/v1/ping');
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame('UNAUTHORIZED', $this->body($res)['code']);
    }

    public function testInvalidTokenRejected(): void
    {
        $res = $this->request('GET', '/api/v1/ping', 'not.a.valid.token');
        $this->assertSame(401, $res->getStatusCode());
        $this->assertSame('INVALID_TOKEN', $this->body($res)['code']);
    }

    public function testValidTokenPassesAndPdoBindingWorks(): void
    {
        $res  = $this->request('GET', '/api/v1/ping', $this->token(['sub' => 7, 'role' => 3]));
        $body = $this->body($res);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('pong', $body['data']['echoed']);       // prepared statement 바인딩 결과
        $this->assertSame(7, $body['data']['user_id']);            // 인증 미들웨어 주입값
    }

    public function testUnknownRouteReturns404(): void
    {
        $res = $this->request('GET', '/nope', $this->token(['sub' => 1]));
        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame('NOT_FOUND', $this->body($res)['code']);
    }

    public function testRateLimitWithinSameInstance(): void
    {
        $factory = new Psr17Factory();
        $app     = App::create($this->env(['RATE_LIMIT_ENABLED' => 'true', 'RATE_LIMIT_MAX' => '2']));
        $token   = $this->token(['sub' => 1]);

        $make = static function () use ($factory, $token): ServerRequestInterface {
            return $factory->createServerRequest('GET', '/api/v1/ping', ['REMOTE_ADDR' => '10.0.0.9'])
                ->withHeader('Authorization', 'Bearer ' . $token);
        };

        $this->assertSame(200, $app->handle($make())->getStatusCode());
        $this->assertSame(200, $app->handle($make())->getStatusCode());
        $this->assertSame(429, $app->handle($make())->getStatusCode()); // 한도 초과
    }
}
