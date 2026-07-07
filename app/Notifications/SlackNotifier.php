<?php

declare(strict_types=1);

namespace App\Notifications;

use Throwable;

/**
 * 슬랙 Incoming Webhook 알림기.
 *
 * 전송 실패 시 dead-letter 로 남긴다(writable/logs/notify-failed/).
 */
final class SlackNotifier implements Notifier
{
    public function __construct(
        private readonly string $webhookUrl,
        private readonly string $deadLetterPath,
    ) {
    }

    public function send(string $title, string $message, string $level = 'info'): bool
    {
        $emoji = match ($level) {
            'error', 'danger' => ':rotating_light:',
            'warning'         => ':warning:',
            default           => ':information_source:',
        };
        $text = "{$emoji} *{$title}*\n{$message}";

        if ($this->webhookUrl === '') {
            $this->deadLetter($title, $message, 'webhook 미설정');

            return false;
        }

        try {
            $response = service('curlrequest')->post($this->webhookUrl, [
                'json'        => ['text' => $text],
                'timeout'     => 5,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return true;
            }

            $this->deadLetter($title, $message, 'HTTP ' . $response->getStatusCode());

            return false;
        } catch (Throwable $e) {
            $this->deadLetter($title, $message, $e->getMessage());

            return false;
        }
    }

    private function deadLetter(string $title, string $message, string $reason): void
    {
        if (! is_dir($this->deadLetterPath)) {
            @mkdir($this->deadLetterPath, 0770, true);
        }
        $line = json_encode([
            'ts'      => date('c'),
            'title'   => $title,
            'message' => $message,
            'reason'  => $reason,
        ], JSON_UNESCAPED_UNICODE);

        file_put_contents(
            $this->deadLetterPath . '/' . date('Y-m-d') . '.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
