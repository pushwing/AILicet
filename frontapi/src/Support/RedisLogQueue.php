<?php

declare(strict_types=1);

namespace App\Support;

use Predis\Client as Redis;

/**
 * Redis 리스트 기반 로그 큐(LPUSH). CI4 소비자가 RPOP 으로 소비한다(FIFO).
 */
final class RedisLogQueue implements LogQueue
{
    /** frontApi 생산자 · CI4 소비자가 공유하는 큐 키. */
    public const string KEY = 'ailicet:log_queue';

    public function __construct(private readonly Redis $redis)
    {
    }

    public function push(array $entry): void
    {
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $this->redis->lpush(self::KEY, [$json]);
    }
}
