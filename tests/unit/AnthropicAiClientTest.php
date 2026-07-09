<?php

declare(strict_types=1);

use App\Exceptions\AiException;
use App\Integrations\AiModelTier;
use App\Integrations\AnthropicAiClient;
use App\Integrations\NullAiClient;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\FakeCurlRequestTrait;

/**
 * Anthropic 클라이언트 단위 테스트 — curlrequest 를 가짜 응답으로 주입.
 *
 * @internal
 */
final class AnthropicAiClientTest extends CIUnitTestCase
{
    use FakeCurlRequestTrait;

    public function testCompleteReturnsConcatenatedText(): void
    {
        $this->fakeCurl(200, json_encode([
            'content' => [
                ['type' => 'text', 'text' => '{"category":"error",'],
                ['type' => 'text', 'text' => '"summary":"요약"}'],
            ],
        ]) ?: '');

        $text = (new AnthropicAiClient('sk-test'))->complete(AiModelTier::Cheap, 'sys', 'prompt');
        $this->assertSame('{"category":"error","summary":"요약"}', $text);
    }

    public function testErrorResponseThrowsWithRemoteType(): void
    {
        $this->fakeCurl(429, json_encode(['error' => ['type' => 'rate_limit_error', 'message' => '한도 초과']]) ?: '');

        try {
            (new AnthropicAiClient('sk-test'))->complete(AiModelTier::Cheap, 'sys', 'prompt');
            $this->fail('예외가 발생해야 한다');
        } catch (AiException $e) {
            $this->assertSame('rate_limit_error', $e->errorCode());
            $this->assertSame(429, $e->httpStatusCode());
        }
    }

    public function testUnconfiguredClientThrows(): void
    {
        $this->expectException(AiException::class);
        (new AnthropicAiClient(''))->complete(AiModelTier::Cheap, 'sys', 'prompt');
    }

    public function testNullClientIsNotConfigured(): void
    {
        $client = new NullAiClient();
        $this->assertFalse($client->isConfigured());
        $this->expectException(AiException::class);
        $client->complete(AiModelTier::Cheap, 's', 'p');
    }
}
