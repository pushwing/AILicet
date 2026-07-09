<?php

declare(strict_types=1);

use App\Exceptions\AiException;
use App\Integrations\AiModelTier;
use App\Integrations\GroqAiClient;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\FakeCurlRequestTrait;

/**
 * Groq 클라이언트 단위 테스트 — OpenAI 호환 응답을 가짜 curlrequest 로 주입.
 *
 * @internal
 */
final class GroqAiClientTest extends CIUnitTestCase
{
    use FakeCurlRequestTrait;

    public function testCompleteReturnsMessageContent(): void
    {
        $this->fakeCurl(200, json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => '{"anomalous":true}']],
            ],
        ]) ?: '');

        $text = (new GroqAiClient('gsk-test'))->complete(AiModelTier::Reasoning, 'sys', 'prompt');
        $this->assertSame('{"anomalous":true}', $text);
    }

    public function testErrorResponseThrowsWithRemoteType(): void
    {
        $this->fakeCurl(401, json_encode(['error' => ['type' => 'invalid_api_key', 'message' => '키 오류']]) ?: '');

        try {
            (new GroqAiClient('gsk-test'))->complete(AiModelTier::Cheap, 'sys', 'prompt');
            $this->fail('예외가 발생해야 한다');
        } catch (AiException $e) {
            $this->assertSame('invalid_api_key', $e->errorCode());
            $this->assertSame(401, $e->httpStatusCode());
        }
    }

    public function testUnconfiguredClientThrows(): void
    {
        $this->expectException(AiException::class);
        (new GroqAiClient(''))->complete(AiModelTier::Cheap, 'sys', 'prompt');
    }
}
