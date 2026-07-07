<?php

declare(strict_types=1);

namespace App\Exception;

/** 요청 빈도 초과. */
final class RateLimitedException extends ApiException
{
    public function __construct(string $message = '요청이 너무 많습니다. 잠시 후 다시 시도하세요.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 429;
    }

    public function errorCode(): string
    {
        return 'RATE_LIMITED';
    }
}
