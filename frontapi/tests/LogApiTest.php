<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Support\InMemoryLogQueue;
use App\Support\LogQueue;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * 로그 수집 엔드포인트 — 큐 적재 + 202.
 *
 * @internal
 */
final class LogApiTest extends TestCase
{
    private const string SECRET = 'log-api-secret-0123456789-abcdefgh';

    private App $app;
    private InMemoryLogQueue $queue;
    private string $token;

    protected function setUp(): void
    {
        $this->app = App::create([
            'APP_ENV'            => 'testing',
            'JWT_SECRET'         => self::SECRET,
            'RATE_LIMIT_ENABLED' => 'false',
        ]);
        /** @var InMemoryLogQueue $queue */
        $queue       = $this->app->container()->get(LogQueue::class);
        $this->queue = $queue;
        $this->token = $this->makeToken();
    }

    private function makeToken(): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $h   = $b64((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p   = $b64((string) json_encode(['sub' => 42, 'role' => 3, 'exp' => time() + 300]));

        return "{$h}.{$p}." . $b64(hash_hmac('sha256', "{$h}.{$p}", self::SECRET, true));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body, bool $auth = true): int
    {
        $req = (new Psr17Factory())->createServerRequest('POST', '/api/v1/logs', ['REMOTE_ADDR' => '10.0.0.1']);
        if ($auth) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $this->token);
        }
        $req->getBody()->write((string) json_encode($body));

        return $this->app->handle($req)->getStatusCode();
    }

    public function testRequiresAuth(): void
    {
        $this->assertSame(401, $this->post(['message' => 'x'], false));
    }

    public function testValidationErrorWithoutMessage(): void
    {
        $this->assertSame(422, $this->post(['level' => 'info']));
    }

    public function testAcceptsAndQueues(): void
    {
        $status = $this->post([
            'level'   => 'error',
            'source'  => 'client-app',
            'message' => '결제 실패',
            'context' => ['code' => 500],
        ]);

        $this->assertSame(202, $status);

        $items = $this->queue->all();
        $this->assertCount(1, $items);
        $this->assertSame('결제 실패', $items[0]['message']);
        $this->assertSame('error', $items[0]['level']);
        $this->assertSame(42, $items[0]['user_id']); // 토큰 주입값
        $this->assertSame('10.0.0.1', $items[0]['client_ip']);
    }
}
