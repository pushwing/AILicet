<?php

declare(strict_types=1);

namespace App\Exceptions;

/** 권한 없음(인증은 되었으나 역할·소유권 불충분). */
final class ForbiddenException extends DomainException
{
    public function __construct(string $message = '접근 권한이 없습니다.')
    {
        parent::__construct($message);
    }

    public function httpStatusCode(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'FORBIDDEN';
    }
}
