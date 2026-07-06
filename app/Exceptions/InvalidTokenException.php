<?php

declare(strict_types=1);

namespace App\Exceptions;

/** 토큰 형식·서명 오류. */
final class InvalidTokenException extends DomainException
{
    public function __construct(string $message = '유효하지 않은 토큰입니다.')
    {
        parent::__construct($message);
    }

    public function httpStatusCode(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'INVALID_TOKEN';
    }
}
