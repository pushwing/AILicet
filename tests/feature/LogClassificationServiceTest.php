<?php

declare(strict_types=1);

use App\Enums\LogCategory;
use App\Integrations\AiClient;
use App\Integrations\NullAiClient;
use App\Models\LogModel;
use App\Services\LogClassificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 로그 AI 분류·요약 서비스 — 미분류 로그 UPDATE / 미설정 no-op / 개별 실패 skip.
 *
 * @internal
 */
final class LogClassificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    /**
     * 지정한 응답을 돌려주는 가짜 AiClient. $throwOn 메시지가 오면 예외를 던진다.
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

            public function complete(string $model, string $system, string $prompt, int $maxTokens = 1024): string
            {
                return $this->response;
            }
        };
    }

    public function testClassifiesPendingLogs(): void
    {
        $model = model(LogModel::class);
        $id    = $model->insert(['level' => 'error', 'source' => 'app', 'message' => 'DB 연결 실패'], true);

        $ai      = $this->fakeAi('{"category":"error","summary":"데이터베이스 연결이 실패했습니다."}');
        $service = new LogClassificationService($ai);
        $result  = $service->classifyPending();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['skipped']);

        $row = $model->find($id);
        $this->assertSame(LogCategory::Error->value, $row['ai_category']);
        $this->assertSame('데이터베이스 연결이 실패했습니다.', $row['ai_summary']);
        $this->assertNotNull($row['ai_processed_at']);
    }

    public function testUnknownCategoryFallsBackToOther(): void
    {
        $model = model(LogModel::class);
        $id    = $model->insert(['level' => 'info', 'message' => '알 수 없음'], true);

        $service = new LogClassificationService($this->fakeAi('{"category":"미확인값","summary":"요약"}'));
        $service->classifyPending();

        $row = $model->find($id);
        $this->assertSame(LogCategory::Other->value, $row['ai_category']);
    }

    public function testAlreadyProcessedLogsAreSkipped(): void
    {
        $model = model(LogModel::class);
        $model->insert(['level' => 'info', 'message' => '이미 처리됨', 'ai_category' => 'system', 'ai_processed_at' => '2026-07-09 00:00:00']);

        $result = (new LogClassificationService($this->fakeAi('{"category":"error","summary":"x"}')))->classifyPending();

        // 미분류가 없으므로 처리 0건
        $this->assertSame(0, $result['processed']);
    }

    public function testUnconfiguredIsNoop(): void
    {
        $model = model(LogModel::class);
        $model->insert(['level' => 'error', 'message' => '미설정']);

        $result = (new LogClassificationService(new NullAiClient()))->classifyPending();

        $this->assertSame(['processed' => 0, 'skipped' => 0], $result);
    }

    public function testMalformedAiResponseIsCountedAsFailureNotSuccess(): void
    {
        $model = model(LogModel::class);
        $id    = $model->insert(['level' => 'warning', 'message' => '깨진 응답'], true);

        // JSON 이 아닌 응답 → 조용히 'other' 로 삼키지 않고 실패로 처리(가시화 + 재시도).
        $result = (new LogClassificationService($this->fakeAi('죄송합니다 JSON 이 아닙니다')))->classifyPending();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['skipped']);

        $row = $model->find($id);
        // 아직 미처리(재시도 대상) — 마커 없음, 시도 횟수만 증가
        $this->assertNull($row['ai_processed_at']);
        $this->assertNull($row['ai_category']);
        $this->assertSame(1, (int) $row['ai_attempts']);
    }

    public function testRepeatedFailureIsDeadLetteredAfterMaxAttempts(): void
    {
        $model = model(LogModel::class);
        // 이미 4회 실패한 로그 — 5회째(MAX_ATTEMPTS)에서 dead-letter 되어야 한다.
        $id = $model->insert(['level' => 'error', 'message' => '독성 로그', 'ai_attempts' => 4], true);

        $result = (new LogClassificationService($this->fakeAi('여전히 깨진 응답')))->classifyPending();

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
