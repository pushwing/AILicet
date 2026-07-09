<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\AiException;

/**
 * AI 미설정 시 주입되는 no-op 클라이언트.
 *
 * ANTHROPIC_API_KEY 가 없을 때 Services::aiClient() 가 이 구현을 반환한다.
 * Service 는 isConfigured() 로 먼저 걸러 complete() 를 호출하지 않으므로,
 * complete() 는 방어적으로 예외를 던진다(직접 호출 시 즉시 드러나도록).
 */
final class NullAiClient implements AiClient
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
    {
        throw new AiException('AI 클라이언트가 설정되지 않았습니다.', 'AI_NOT_CONFIGURED', 503);
    }
}
