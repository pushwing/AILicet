<?php

declare(strict_types=1);

namespace App\Exceptions;

/** 허용되지 않은 라이센스 상태 전이. */
final class InvalidStateTransitionException extends DomainException
{
    public function httpStatusCode(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'INVALID_STATE_TRANSITION';
    }
}
