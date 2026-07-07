<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 로그 큐 — 수신한 로그를 비동기 처리용 큐에 적재한다.
 */
interface LogQueue
{
    /**
     * @param array<string, mixed> $entry
     */
    public function push(array $entry): void;
}
