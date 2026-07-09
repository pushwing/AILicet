<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InquiryCategory;
use App\Exceptions\AiException;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Models\InquiryModel;
use Throwable;

/**
 * 고객 문의 AI 자동 분류·답변 초안 — 미처리 문의를 골라 카테고리와 답변 초안을 채운다.
 *
 * 요청 사이클이 아니라 ai:draft-inquiries 배치 Command 에서 호출된다(부하분산 원칙).
 * 분류와 초안을 한 번의 Reasoning 호출로 동시에 생성한다(비용·지연 최소).
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op 이며, 개별 문의 AI 실패는 건너뛰어 다음 배치에서 재시도된다.
 *
 * ⚠️ AI 는 어디까지나 "초안"까지만 만든다. 실제 발송(reply/status=answered)은 운영자가 확정한다(human-in-the-loop).
 */
final class InquiryClassificationService
{
    /** 실패 재시도 한도 — 초과 시 dead-letter 로 격리해 head-of-line 블로킹을 막는다. */
    private const int MAX_ATTEMPTS = 5;

    /** 답변 초안 최대 길이(과도한 토큰·저장 방지). */
    private const int MAX_DRAFT_LENGTH = 2000;

    public function __construct(
        private readonly ?AiClient $ai = null,
    ) {
    }

    /**
     * 미처리 문의를 최대 $limit 건 분류하고 답변 초안을 생성해 저장한다.
     *
     * @return array{processed:int, skipped:int}
     */
    public function draftPending(int $limit = 50): array
    {
        $ai = $this->ai ?? service('aiClient');

        // AI 미설정이면 아무것도 하지 않는다(파이프라인은 배선되어 있으나 no-op).
        if (! $ai->isConfigured()) {
            return ['processed' => 0, 'skipped' => 0];
        }

        $model     = model(InquiryModel::class);
        $rows      = $model->findPendingAiClassification($limit);
        $processed = 0;
        $skipped   = 0;

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id === 0) {
                continue;
            }

            try {
                $result = $this->draftOne($ai, $row);
                $model->update($id, [
                    'ai_category'     => $result['category']->value,
                    'ai_draft_reply'  => $result['draft'],
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
     * 격리해 뒤의 새 문의가 막히지 않게 한다(형제 LogClassificationService 의 dead-letter 원칙과 정합).
     *
     * @param array<string, mixed> $row
     */
    private function recordFailure(InquiryModel $model, array $row, int $id, string $reason): void
    {
        $attempts = (isset($row['ai_attempts']) ? (int) $row['ai_attempts'] : 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            // dead-letter: 큐에서 제거(ai_processed_at 설정)하되 ai_category 는 null 로 남겨
            // "반복 실패로 미분류"임을 정상 분류('other')와 구분한다.
            $model->update($id, ['ai_attempts' => $attempts, 'ai_processed_at' => date('Y-m-d H:i:s')]);
            log_message('error', sprintf('AI 문의 초안 %d회 실패로 dead-letter(inquiry_id=%d): %s', $attempts, $id, $reason));

            return;
        }

        $model->update($id, ['ai_attempts' => $attempts]);
        log_message('warning', sprintf('AI 문의 초안 실패, 재시도 예정(inquiry_id=%d, 시도=%d): %s', $id, $attempts, $reason));
    }

    /**
     * 문의 한 건을 분류하고 답변 초안을 만든다. 파싱 실패는 예외로 던져 재시도·격리 대상이 되게 한다.
     *
     * @param array<string, mixed> $row
     *
     * @return array{category: InquiryCategory, draft: string}
     *
     * @throws AiException 파싱 실패(응답은 왔으나 사용 불가)
     */
    private function draftOne(AiClient $ai, array $row): array
    {
        // 분류+초안 생성은 자연어 판단·품질이 필요하므로 추론 등급 사용(CLAUDE.md 모델 선택 기준).
        $raw    = $ai->complete(AiModelTier::Reasoning, $this->systemPrompt(), $this->userPrompt($row), 1500);
        $parsed = $this->parse($raw);

        // 파싱 실패는 조용히 'other' 로 삼키지 않고 실패로 처리한다(가시화 + 재시도/격리).
        if ($parsed === null) {
            throw new AiException('AI 응답을 파싱할 수 없습니다.', 'AI_PARSE_FAILED', 502);
        }

        return [
            'category' => InquiryCategory::fromString($parsed['category']),
            'draft'    => mb_substr(trim($parsed['draft']), 0, self::MAX_DRAFT_LENGTH),
        ];
    }

    /**
     * AI 응답(JSON 문자열)에서 category·draft_reply 를 추출한다. 파싱 불가 시 null.
     *
     * @return array{category: string, draft: string}|null
     */
    private function parse(string $raw): ?array
    {
        // 모델이 앞뒤 설명을 붙일 수 있어 첫 JSON 객체만 추출한다.
        if (preg_match('/\{.*\}/s', $raw, $m) !== 1) {
            return null;
        }
        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return null;
        }

        $draft = $decoded['draft_reply'] ?? '';

        return [
            'category' => isset($decoded['category']) ? (string) $decoded['category'] : '',
            'draft'    => is_string($draft) ? $draft : '',
        ];
    }

    private function systemPrompt(): string
    {
        return '너는 소프트웨어 라이선스 관리 서비스의 고객지원 담당자다. '
            . '주어진 고객 문의를 아래 카테고리 중 하나로 분류하고, 정중한 한국어 존댓말로 답변 초안을 작성하라. '
            . '카테고리(값 그대로 사용): ' . InquiryCategory::allowedValuesCsv() . '. '
            . '초안은 확정 답변이 아니라 운영자가 검토·수정할 참고안이다. '
            . '추측이 필요한 부분은 단정하지 말고 확인이 필요하다고 안내하라. '
            . '반드시 {"category":"<값>","draft_reply":"<답변 초안>"} 형식의 JSON 만 출력하라.';
    }

    /**
     * 외부 AI 로 보내는 프롬프트. 개인정보(email·customer_id) 는 제외하고 문의 본문만 전송한다(PII 최소화).
     *
     * @param array<string, mixed> $row
     */
    private function userPrompt(array $row): string
    {
        $subject = trim((string) ($row['subject'] ?? ''));
        $content = trim((string) ($row['content'] ?? ''));

        return sprintf(
            "제목: %s\n\n내용:\n%s",
            $subject !== '' ? $subject : '(제목 없음)',
            $content !== '' ? $content : '(내용 없음)',
        );
    }
}
