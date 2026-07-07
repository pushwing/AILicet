<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * 로그 큐(소비자 측) — 큐에서 로그 엔트리를 하나씩 꺼낸다.
 */
interface LogQueue
{
    /**
     * @return array<string, mixed>|null 큐가 비었으면 null
     */
    public function pop(): ?array;
}
