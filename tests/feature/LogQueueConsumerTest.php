<?php

declare(strict_types=1);

use App\Queue\InMemoryLogQueue;
use App\Services\LogQueueConsumer;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 로그 큐 소비자 — 원시파일 보존 + DB INSERT + dead-letter.
 *
 * @internal
 */
final class LogQueueConsumerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private string $rawDir;
    private string $deadDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rawDir  = WRITEPATH . 'logs/raw-test';
        $this->deadDir = WRITEPATH . 'logs/queue-failed-test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ([$this->rawDir, $this->deadDir] as $dir) {
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }
    }

    public function testConsumeWritesRawFileAndDb(): void
    {
        $queue = new InMemoryLogQueue();
        $queue->seed(['level' => 'info', 'source' => 'app', 'message' => '첫 로그', 'context' => ['a' => 1], 'client_ip' => '1.1.1.1', 'user_id' => 7, 'logged_at' => '2026-07-06 10:00:00']);
        $queue->seed(['level' => 'error', 'message' => '둘째 로그']);

        $consumer = new LogQueueConsumer($queue, $this->rawDir, $this->deadDir);
        $result   = $consumer->consume();

        $this->assertSame(2, $result['processed']);
        $this->assertSame(0, $result['failed']);

        // DB 저장
        $this->seeInDatabase('logs', ['message' => '첫 로그', 'level' => 'info', 'user_id' => 7]);
        $this->seeInDatabase('logs', ['message' => '둘째 로그', 'level' => 'error']);

        // 원시파일 보존
        $raw = $this->rawDir . '/' . date('Y-m-d') . '.log';
        $this->assertFileExists($raw);
        $this->assertStringContainsString('첫 로그', (string) file_get_contents($raw));
    }

    public function testEmptyQueueNoop(): void
    {
        $result = (new LogQueueConsumer(new InMemoryLogQueue(), $this->rawDir, $this->deadDir))->consume();
        $this->assertSame(['processed' => 0, 'failed' => 0], $result);
    }

    public function testFailedInsertGoesToDeadLetter(): void
    {
        // message 초과 등으로 INSERT 실패를 유도하기 어려우므로, raw 디렉토리를 파일로 만들어
        // 쓰기 단계에서 예외 대신, 여기서는 잘못된 데이터로 DB 예외를 유발한다.
        $queue = new InMemoryLogQueue();
        // level 컬럼은 VARCHAR(20) — 초과 길이로 데이터 예외 유도
        $queue->seed(['level' => str_repeat('x', 300), 'message' => 'dead']);

        $result = (new LogQueueConsumer($queue, $this->rawDir, $this->deadDir))->consume();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['failed']);

        $dead = $this->deadDir . '/' . date('Y-m-d') . '.log';
        $this->assertFileExists($dead);
        $this->assertStringContainsString('dead', (string) file_get_contents($dead));
    }
}
