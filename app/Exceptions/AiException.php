<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * AI(Anthropic) 연동 호출 실패 — 상태코드·에러코드를 실어 전달한다.
 */
final class AiException extends DomainException
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
