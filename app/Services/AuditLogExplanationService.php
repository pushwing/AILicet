<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEventType;
use App\Exceptions\AiException;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Models\AuditLogModel;
use Throwable;

/**
 * 감사 로그 AI 사람용 설명 생성 — 미설명 감사 로그를 골라 저비용 모델로 한국어 설명을 채운다.
 *
 * 요청 사이클이 아니라 배치(별도 슬라이스의 Command)에서 호출된다(부하분산 원칙).
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op 이며, 개별 실패는 재시도 후 한도 초과 시 dead-letter 로 격리한다.
 * (logs/inquiries AI 분류 서비스와 동일 패턴)
 */
final class AuditLogExplanationService
{
    /** 실패 재시도 한도 — 초과 시 dead-letter 로 격리해 head-of-line 블로킹을 막는다. */
    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly ?AiClient $ai = null,
    ) {
    }

    /**
     * 미설명 감사 로그를 최대 $limit 건 설명해 저장한다.
     *
     * @return array{processed:int, skipped:int}
     */
    public function explainPending(int $limit = 100): array
    {
        $ai = $this->ai ?? service('aiClient');

        // AI 미설정이면 아무것도 하지 않는다(파이프라인은 배선되어 있으나 no-op).
        if (! $ai->isConfigured()) {
            return ['processed' => 0, 'skipped' => 0];
        }

        $model     = model(AuditLogModel::class);
        $rows      = $model->findPendingAiExplanation($limit);
        $processed = 0;
        $skipped   = 0;

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id === 0) {
                continue;
            }

            try {
                $explanation = $this->explainOne($ai, $row);
                $model->update($id, [
                    'ai_explanation'  => $explanation,
                    'ai_processed_at' => date('Y-m-d H:i:s'),
                ]);
                $processed++;
            } catch (Throwable $e) {
                $this->recordFailure($model, $row, $id, $e->getMessage());
                $skipped++;
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * 개별 실패를 기록한다. 재시도 횟수를 올리고, 한도 초과 시 dead-letter(ai_processed_at 마킹)로
     * 격리해 뒤의 새 감사 로그가 막히지 않게 한다(형제 분류 서비스의 dead-letter 원칙과 정합).
     *
     * @param array<string, mixed> $row
     */
    private function recordFailure(AuditLogModel $model, array $row, int $id, string $reason): void
    {
        $attempts = (isset($row['ai_attempts']) ? (int) $row['ai_attempts'] : 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            // dead-letter: 큐에서 제거(ai_processed_at 설정)하되 ai_explanation 은 null 로 남겨
            // "반복 실패로 미설명"임을 정상 설명과 구분한다.
            $model->update($id, ['ai_attempts' => $attempts, 'ai_processed_at' => date('Y-m-d H:i:s')]);
            log_message('error', sprintf('AI 감사 로그 설명 %d회 실패로 dead-letter(audit_log_id=%d): %s', $attempts, $id, $reason));

            return;
        }

        $model->update($id, ['ai_attempts' => $attempts]);
        log_message('warning', sprintf('AI 감사 로그 설명 실패, 재시도 예정(audit_log_id=%d, 시도=%d): %s', $id, $attempts, $reason));
    }

    /**
     * 감사 로그 한 건을 사람용 설명으로 변환한다. 파싱 실패는 예외로 던져 재시도·격리 대상이 되게 한다.
     *
     * @param array<string, mixed> $row
     *
     * @throws AiException 파싱 실패(응답은 왔으나 사용 불가)
     */
    private function explainOne(AiClient $ai, array $row): string
    {
        // 설명 생성은 저비용 등급 사용(CLAUDE.md 모델 선택 기준).
        $raw         = $ai->complete(AiModelTier::Cheap, $this->systemPrompt(), $this->userPrompt($row));
        $explanation = $this->parse($raw);

        // 파싱 실패는 조용히 삼키지 않고 실패로 처리한다(가시화 + 재시도/격리).
        if ($explanation === null || $explanation === '') {
            throw new AiException('AI 응답을 파싱할 수 없습니다.', 'AI_PARSE_FAILED', 502);
        }

        return mb_substr($explanation, 0, 500);
    }

    /**
     * AI 응답(JSON 문자열)에서 explanation 을 추출한다. 파싱 불가 시 null.
     */
    private function parse(string $raw): ?string
    {
        // 모델이 앞뒤 설명을 붙일 수 있어 첫 JSON 객체만 추출한다.
        if (preg_match('/\{.*\}/s', $raw, $m) !== 1) {
            return null;
        }
        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return null;
        }

        return isset($decoded['explanation']) ? trim((string) $decoded['explanation']) : '';
    }

    private function systemPrompt(): string
    {
        return '너는 소프트웨어 라이선스 관리 시스템의 감사 로그 해설가다. '
            . '주어진 감사 이벤트(유형·상세 정보)를 운영자가 한눈에 이해할 수 있도록 한국어 한두 문장으로 설명하라. '
            . '반드시 {"explanation":"<사람이 읽는 설명>"} 형식의 JSON 만 출력하라.';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function userPrompt(array $row): string
    {
        $eventType = (string) ($row['event_type'] ?? '');
        $label     = AuditEventType::tryFrom($eventType)?->label() ?? $eventType;
        $detail    = $row['detail'] ?? null;
        $detail    = is_string($detail) ? $detail : (string) json_encode($detail, JSON_UNESCAPED_UNICODE);
        $hasDetail = $detail !== '' && $detail !== 'null' && $detail !== 'false';

        return sprintf(
            "event_type: %s\nlabel: %s\ndetail: %s",
            $eventType !== '' ? $eventType : '(none)',
            $label !== '' ? $label : '(none)',
            $hasDetail ? $detail : '(none)',
        );
    }
}
