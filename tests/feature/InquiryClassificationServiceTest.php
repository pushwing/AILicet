<?php

declare(strict_types=1);

use App\Enums\InquiryCategory;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Integrations\NullAiClient;
use App\Models\InquiryModel;
use App\Services\InquiryClassificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 문의 AI 분류·초안 서비스 — 미처리 문의 UPDATE / 미설정 no-op / 개별 실패 skip / dead-letter.
 *
 * @internal
 */
final class InquiryClassificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

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

    private function insertInquiry(InquiryModel $model, array $extra = []): int
    {
        return (int) $model->insert(array_merge([
            'email'   => 'user@example.com',
            'subject' => '라이선스 연장 문의',
            'content' => '라이선스 만료가 임박했는데 연장 방법을 알려주세요.',
            'status'  => 'open',
        ], $extra), true);
    }

    public function testDraftsPendingInquiries(): void
    {
        $model = model(InquiryModel::class);
        $id    = $this->insertInquiry($model);

        $ai      = $this->fakeAi('{"category":"license","draft_reply":"안녕하세요. 라이선스 연장은 마이페이지에서 가능합니다."}');
        $service = new InquiryClassificationService($ai);
        $result  = $service->draftPending();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['skipped']);

        $row = $model->find($id);
        $this->assertSame(InquiryCategory::License->value, $row['ai_category']);
        $this->assertStringContainsString('라이선스 연장', (string) $row['ai_draft_reply']);
        $this->assertNotNull($row['ai_processed_at']);
        // 초안 생성만으로 상태가 바뀌면 안 된다(발송은 사람 확정).
        $this->assertSame('open', $row['status']);
        $this->assertNull($row['reply']);
    }

    public function testUnknownCategoryFallsBackToOther(): void
    {
        $model = model(InquiryModel::class);
        $id    = $this->insertInquiry($model);

        $service = new InquiryClassificationService($this->fakeAi('{"category":"미확인값","draft_reply":"답변"}'));
        $service->draftPending();

        $row = $model->find($id);
        $this->assertSame(InquiryCategory::Other->value, $row['ai_category']);
    }

    public function testAlreadyProcessedInquiriesAreSkipped(): void
    {
        $model = model(InquiryModel::class);
        $this->insertInquiry($model, ['ai_category' => 'billing', 'ai_processed_at' => '2026-07-09 00:00:00']);

        $result = (new InquiryClassificationService($this->fakeAi('{"category":"license","draft_reply":"x"}')))->draftPending();

        $this->assertSame(0, $result['processed']);
    }

    public function testUnconfiguredIsNoop(): void
    {
        $model = model(InquiryModel::class);
        $this->insertInquiry($model);

        $result = (new InquiryClassificationService(new NullAiClient()))->draftPending();

        $this->assertSame(['processed' => 0, 'skipped' => 0], $result);
    }

    public function testMalformedAiResponseIsCountedAsFailureNotSuccess(): void
    {
        $model = model(InquiryModel::class);
        $id    = $this->insertInquiry($model);

        // JSON 이 아닌 응답 → 조용히 삼키지 않고 실패로 처리(가시화 + 재시도).
        $result = (new InquiryClassificationService($this->fakeAi('죄송합니다 JSON 이 아닙니다')))->draftPending();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['skipped']);

        $row = $model->find($id);
        $this->assertNull($row['ai_processed_at']);
        $this->assertNull($row['ai_category']);
        $this->assertSame(1, (int) $row['ai_attempts']);
    }

    public function testRepeatedFailureIsDeadLetteredAfterMaxAttempts(): void
    {
        $model = model(InquiryModel::class);
        // 이미 4회 실패 — 5회째(MAX_ATTEMPTS)에서 dead-letter 되어야 한다.
        $id = $this->insertInquiry($model, ['ai_attempts' => 4]);

        $result = (new InquiryClassificationService($this->fakeAi('여전히 깨진 응답')))->draftPending();

        $this->assertSame(1, $result['skipped']);

        $row = $model->find($id);
        // dead-letter: 큐에서 제거(ai_processed_at 마킹)되나 category 는 null(정상 'other' 와 구분)
        $this->assertNotNull($row['ai_processed_at']);
        $this->assertNull($row['ai_category']);
        $this->assertSame(5, (int) $row['ai_attempts']);

        // 다음 배치에서 재선택되지 않음
        $this->assertSame([], $model->findPendingAiClassification(10));
    }
}
