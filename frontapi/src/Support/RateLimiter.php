<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 요청 빈도 제한기.
 */
interface RateLimiter
{
    /**
     * 키에 대한 요청을 1회 소비하고 허용 여부를 반환한다.
     *
     * @return bool true=허용, false=한도 초과
     */
    public function hit(string $key): bool;
}
