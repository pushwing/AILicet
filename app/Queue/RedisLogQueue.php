<?php

declare(strict_types=1);

namespace App\Queue;

use Predis\Client as Redis;

/**
 * Redis 리스트 로그 큐(소비자) — frontApi 가 LPUSH 한 항목을 RPOP 으로 소비(FIFO).
 */
final class RedisLogQueue implements LogQueue
{
    /** frontApi 생산자와 공유하는 큐 키. */
    public const string KEY = 'ailicet:log_queue';

    public function __construct(private readonly Redis $redis)
    {
    }

    public function pop(): ?array
    {
        $raw = $this->redis->rpop(self::KEY);
        if (! is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
