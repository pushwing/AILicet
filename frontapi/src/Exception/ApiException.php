<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * API 예외 기반 클래스 — HTTP 상태 + 에러 코드(UPPER_SNAKE_CASE)를 제공.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function statusCode(): int;

    abstract public function errorCode(): string;
}
