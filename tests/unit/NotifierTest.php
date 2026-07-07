<?php

declare(strict_types=1);

use App\Notifications\LogNotifier;
use App\Notifications\SlackNotifier;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 알림기 단위 테스트 (DB 불필요).
 *
 * @internal
 */
final class NotifierTest extends CIUnitTestCase
{
    private string $deadLetterDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deadLetterDir = WRITEPATH . 'logs/notify-failed-test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->deadLetterDir)) {
            array_map('unlink', glob($this->deadLetterDir . '/*') ?: []);
            @rmdir($this->deadLetterDir);
        }
    }

    public function testLogNotifierAlwaysSucceeds(): void
    {
        $this->assertTrue((new LogNotifier())->send('제목', '내용', 'info'));
    }

    public function testSlackNotifierWithoutWebhookWritesDeadLetter(): void
    {
        $notifier = new SlackNotifier('', $this->deadLetterDir);

        $this->assertFalse($notifier->send('만료 알림', '3건 만료 임박', 'warning'));

        $file = $this->deadLetterDir . '/' . date('Y-m-d') . '.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('만료 알림', (string) file_get_contents($file));
        $this->assertStringContainsString('webhook 미설정', (string) file_get_contents($file));
    }
}
