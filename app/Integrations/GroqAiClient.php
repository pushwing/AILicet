<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\AiException;

/**
 * Groq API 클라이언트 — OpenAI 호환 Chat Completions(`/openai/v1/chat/completions`).
 *
 * AnthropicAiClient 와 동일하게 CI4 `curlrequest` + 5초 타임아웃 + http_errors:false 패턴을 따른다.
 * Bearer 인증, OpenAI 형식 messages(system+user)로 호출한다.
 *
 * ⚠️ 실호출 검증은 후속(실제 GROQ_API_KEY 주입 후)에서 수행한다.
 */
final class GroqAiClient implements AiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.groq.com',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
    {
        if (! $this->isConfigured()) {
            throw new AiException('AI 클라이언트가 설정되지 않았습니다.', 'AI_NOT_CONFIGURED', 503);
        }

        $response = service('curlrequest')->request('POST', rtrim($this->baseUrl, '/') . '/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'json' => [
                'model'      => $this->model($tier),
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'timeout'     => 5,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        $body   = json_decode((string) $response->getBody(), true);
        $body   = is_array($body) ? $body : [];

        if ($status < 200 || $status >= 300) {
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];

            throw new AiException(
                (string) ($error['message'] ?? 'AI 호출에 실패했습니다.'),
                (string) ($error['type'] ?? 'AI_ERROR'),
                $status,
            );
        }

        return $this->extractText($body);
    }

    /** 작업 등급 → Groq 모델명. */
    private function model(AiModelTier $tier): string
    {
        return match ($tier) {
            AiModelTier::Cheap     => 'llama-3.1-8b-instant',
            AiModelTier::Reasoning => 'llama-3.3-70b-versatile',
        };
    }

    /**
     * OpenAI 호환 응답에서 첫 choice 의 message.content 를 추출한다.
     *
     * @param array<string, mixed> $body
     */
    private function extractText(array $body): string
    {
        $choices = is_array($body['choices'] ?? null) ? $body['choices'] : [];
        $first   = is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = is_array($first['message'] ?? null) ? $first['message'] : [];

        return (string) ($message['content'] ?? '');
    }
}
