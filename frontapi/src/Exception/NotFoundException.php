<?php

declare(strict_types=1);

namespace App\Exception;

/** 리소스·라우트 없음. */
final class NotFoundException extends ApiException
{
    public function __construct(string $message = '요청한 리소스를 찾을 수 없습니다.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 404;
    }

    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }
}
