<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 도메인 예외 기반 클래스.
 *
 * 모든 도메인 예외는 HTTP 상태코드 + 에러 코드(문자열)를 제공한다.
 * 에러 코드는 UPPER_SNAKE_CASE (CLAUDE.md 에러 코드 네이밍 규칙).
 */
abstract class DomainException extends RuntimeException
{
    abstract public function httpStatusCode(): int;

    abstract public function errorCode(): string;
}
