<?php

declare(strict_types=1);

namespace App\Support;

use Predis\Client as Redis;
use Throwable;

/**
 * Redis 고정 윈도우 Rate Limiter.
 *
 * 키별로 window 초 동안 max 회까지 허용한다. Redis 장애 시에는 요청을 막지 않는다(fail-open).
 */
final class RedisRateLimiter implements RateLimiter
{
    public function __construct(
        private readonly Redis $redis,
        private readonly int $max,
        private readonly int $window,
    ) {
    }

    public function hit(string $key): bool
    {
        try {
            $redisKey = 'ratelimit:' . $key;
            $count    = (int) $this->redis->incr($redisKey);
            if ($count === 1) {
                $this->redis->expire($redisKey, $this->window);
            }

            return $count <= $this->max;
        } catch (Throwable) {
            return true; // fail-open: Redis 장애가 서비스 전체를 막지 않도록
        }
    }
}
