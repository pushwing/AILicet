<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * AITessera 연동 호출 실패 — 원격 응답의 상태코드·에러코드를 그대로 전달한다.
 */
final class AitesseraException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $remoteCode,
        private readonly int $remoteStatus,
    ) {
        parent::__construct($message);
    }

    public function httpStatusCode(): int
    {
        return $this->remoteStatus;
    }

    public function errorCode(): string
    {
        return $this->remoteCode;
    }
}
