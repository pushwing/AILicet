<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * 로그 기록 알림기 — 슬랙 webhook 미설정 시(개발) 로그로 대체.
 */
final class LogNotifier implements Notifier
{
    public function send(string $title, string $message, string $level = 'info'): bool
    {
        log_message($level === 'error' ? 'error' : 'info', "[알림:{$level}] {$title} — {$message}");

        return true;
    }
}
