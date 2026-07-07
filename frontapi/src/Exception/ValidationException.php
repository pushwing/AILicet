<?php

declare(strict_types=1);

namespace App\Exception;

/** 입력 유효성 검사 실패. */
final class ValidationException extends ApiException
{
    public function __construct(string $message = '입력값이 유효하지 않습니다.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }
}
