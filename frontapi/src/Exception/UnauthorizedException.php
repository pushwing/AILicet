<?php

declare(strict_types=1);

namespace App\Exception;

/** 인증 토큰 없음. */
final class UnauthorizedException extends ApiException
{
    public function __construct(string $message = '인증 토큰이 필요합니다.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'UNAUTHORIZED';
    }
}
