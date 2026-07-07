<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 인메모리 로그 큐 — 테스트·Redis 미사용 환경용.
 */
final class InMemoryLogQueue implements LogQueue
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    public function push(array $entry): void
    {
        $this->items[] = $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->items;
    }
}
