<?php

declare(strict_types=1);

use App\Integrations\AnthropicAiClient;
use App\Integrations\GroqAiClient;
use App\Integrations\NullAiClient;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * aiClient() 팩토리 — AI_PROVIDER 로 제공자 선택, 키 미설정 시 NullAiClient(no-op).
 *
 * @internal
 */
final class AiClientFactoryTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $keys = ['AI_PROVIDER', 'ANTHROPIC_API_KEY', 'GROQ_API_KEY'];

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->keys as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    public function testDefaultsToAnthropicWhenKeySet(): void
    {
        $this->setEnv('ANTHROPIC_API_KEY', 'sk-test');
        $this->assertInstanceOf(AnthropicAiClient::class, Services::aiClient(false));
    }

    public function testSelectsGroqWhenProviderGroqAndKeySet(): void
    {
        $this->setEnv('AI_PROVIDER', 'groq');
        $this->setEnv('GROQ_API_KEY', 'gsk-test');
        $this->assertInstanceOf(GroqAiClient::class, Services::aiClient(false));
    }

    public function testGroqWithoutKeyFallsBackToNull(): void
    {
        $this->setEnv('AI_PROVIDER', 'groq');
        $this->assertInstanceOf(NullAiClient::class, Services::aiClient(false));
    }

    public function testAnthropicWithoutKeyFallsBackToNull(): void
    {
        $this->setEnv('AI_PROVIDER', 'anthropic');
        $this->assertInstanceOf(NullAiClient::class, Services::aiClient(false));
    }
}
