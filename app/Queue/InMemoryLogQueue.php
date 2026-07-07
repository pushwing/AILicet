<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * 인메모리 로그 큐(소비자) — 테스트용. seed() 로 항목을 채운다.
 */
final class InMemoryLogQueue implements LogQueue
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    /**
     * @param array<string, mixed> $entry
     */
    public function seed(array $entry): void
    {
        $this->items[] = $entry;
    }

    public function pop(): ?array
    {
        return array_shift($this->items);
    }
}
