<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 인메모리 Rate Limiter — 단일 프로세스(테스트·Redis 미사용 환경)용.
 *
 * @internal 분산 환경에서는 RedisRateLimiter 를 사용한다.
 */
final class InMemoryRateLimiter implements RateLimiter
{
    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly int $max,
    ) {
    }

    public function hit(string $key): bool
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

        return $this->counts[$key] <= $this->max;
    }
}
