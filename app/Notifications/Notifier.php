<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * 알림 전송기.
 */
interface Notifier
{
    /**
     * 알림을 전송한다. 실패 시 false(구현체는 dead-letter 로깅).
     */
    public function send(string $title, string $message, string $level = 'info'): bool;
}
