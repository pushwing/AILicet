<?php

declare(strict_types=1);

namespace App\Exception;

/** 토큰 만료. */
final class TokenExpiredException extends ApiException
{
    public function __construct(string $message = '토큰이 만료되었습니다.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'TOKEN_EXPIRED';
    }
}
