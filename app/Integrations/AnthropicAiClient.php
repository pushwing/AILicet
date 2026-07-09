<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\AiException;

/**
 * Anthropic Messages API 클라이언트.
 *
 * `POST {baseUrl}/v1/messages` 를 호출해 텍스트 응답을 받는다.
 * AitesseraClient 와 동일하게 CI4 `curlrequest` + 5초 타임아웃 + http_errors:false 패턴을 따른다.
 *
 * ⚠️ 실호출 검증은 후속 작업(실제 ANTHROPIC_API_KEY 주입 후)에서 수행한다.
 *    현재는 인터페이스·파이프라인 배선을 완성하고 호출 골격만 제공한다.
 */
final class AnthropicAiClient implements AiClient
{
    /** Anthropic API 버전 헤더(고정). */
    private const string API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.anthropic.com',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function complete(string $model, string $system, string $prompt, int $maxTokens = 1024): string
    {
        if (! $this->isConfigured()) {
            throw new AiException('AI 클라이언트가 설정되지 않았습니다.', 'AI_NOT_CONFIGURED', 503);
        }

        $response = service('curlrequest')->request('POST', rtrim($this->baseUrl, '/') . '/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
                'accept'            => 'application/json',
            ],
            'json' => [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [
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

    /**
     * Messages API 응답에서 텍스트 블록을 이어붙여 추출한다.
     *
     * @param array<string, mixed> $body
     */
    private function extractText(array $body): string
    {
        $content = is_array($body['content'] ?? null) ? $body['content'] : [];
        $text    = '';
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return $text;
    }
}
