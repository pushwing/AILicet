<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Integrations\NullAiClient;
use App\Models\AuditLogModel;
use App\Services\AuditLogExplanationService;
use Tests\Support\DatabaseTestCase;

/**
 * 감사 로그 AI 사람용 설명 서비스 — 미설명 감사 로그 UPDATE / 미설정 no-op / 개별 실패 격리.
 *
 * @internal
 */
final class AuditLogExplanationServiceTest extends DatabaseTestCase
{
    /**
     * 지정한 응답을 돌려주는 가짜 AiClient.
     */
    private function fakeAi(string $response, bool $configured = true): AiClient
    {
        return new class ($response, $configured) implements AiClient {
            public function __construct(
                private readonly string $response,
                private readonly bool $configured,
            ) {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
            {
                return $this->response;
            }
        };
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedAuditLog(array $overrides = []): int
    {
        $model = model(AuditLogModel::class);

        return $model->insert(array_merge([
            'event_type'  => AuditEventType::IllegalHost->value,
            'license_key' => 'KEY-1',
            'detail'      => json_encode(['host_id' => 'H1'], JSON_UNESCAPED_UNICODE),
        ], $overrides), true);
    }

    public function testExplainsPendingAuditLogs(): void
    {
        $model = model(AuditLogModel::class);
        $id    = $this->seedAuditLog();

        $ai      = $this->fakeAi('{"explanation":"등록된 호스트와 다른 기기에서 라이선스를 사용했습니다."}');
        $service = new AuditLogExplanationService($ai);
        $result  = $service->explainPending();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['skipped']);

        $row = $model->find($id);
        $this->assertSame('등록된 호스트와 다른 기기에서 라이선스를 사용했습니다.', $row['ai_explanation']);
        $this->assertNotNull($row['ai_processed_at']);
    }

    public function testUnconfiguredIsNoop(): void
    {
        $this->seedAuditLog();

        $result = (new AuditLogExplanationService(new NullAiClient()))->explainPending();

        $this->assertSame(['processed' => 0, 'skipped' => 0], $result);
    }

    public function testAlreadyExplainedAreSkipped(): void
    {
        $this->seedAuditLog(['ai_explanation' => '이미 설명됨', 'ai_processed_at' => '2026-07-09 00:00:00']);

        $result = (new AuditLogExplanationService($this->fakeAi('{"explanation":"x"}')))->explainPending();

        $this->assertSame(0, $result['processed']);
    }

    public function testMalformedAiResponseIsCountedAsFailureNotSuccess(): void
    {
        $model = model(AuditLogModel::class);
        $id    = $this->seedAuditLog();

        $result = (new AuditLogExplanationService($this->fakeAi('죄송합니다 JSON 이 아닙니다')))->explainPending();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['skipped']);

        $row = $model->find($id);
        // 아직 미처리(재시도 대상) — 마커·설명 없음, 시도 횟수만 증가
        $this->assertNull($row['ai_processed_at']);
        $this->assertNull($row['ai_explanation']);
        $this->assertSame(1, (int) $row['ai_attempts']);
    }

    public function testRepeatedFailureIsDeadLetteredAfterMaxAttempts(): void
    {
        $model = model(AuditLogModel::class);
        // 이미 4회 실패 — 5회째(MAX_ATTEMPTS)에서 dead-letter 되어야 한다.
        $id = $this->seedAuditLog(['ai_attempts' => 4]);

        $result = (new AuditLogExplanationService($this->fakeAi('여전히 깨진 응답')))->explainPending();

        $this->assertSame(1, $result['skipped']);

        $row = $model->find($id);
        // dead-letter: 큐에서 제거(ai_processed_at 마킹)되나 설명은 null(정상 설명과 구분)
        $this->assertNotNull($row['ai_processed_at']);
        $this->assertNull($row['ai_explanation']);
        $this->assertSame(5, (int) $row['ai_attempts']);

        // 다음 배치에서 재선택되지 않음
        $this->assertSame([], $model->findPendingAiExplanation(10));
    }
}
