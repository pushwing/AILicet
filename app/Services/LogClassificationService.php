<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LogCategory;
use App\Exceptions\AiException;
use App\Integrations\AiClient;
use App\Models\LogModel;
use Throwable;

/**
 * 수집 로그 AI 자동 분류·요약 — 미분류 로그를 골라 저비용 모델로 카테고리·요약을 채운다.
 *
 * 요청 사이클이 아니라 ai:classify-logs 배치 Command 에서 호출된다(부하분산 원칙).
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op 이며, 개별 로그 AI 실패는 건너뛰어 다음 배치에서 재시도된다.
 */
final class LogClassificationService
{
    /** 분류·요약은 저비용 모델 사용(CLAUDE.md 모델 선택 기준). */
    private const string MODEL = 'claude-haiku-4-5';

    /** 실패 재시도 한도 — 초과 시 dead-letter 로 격리해 head-of-line 블로킹을 막는다. */
    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly ?AiClient $ai = null,
    ) {
    }

    /**
     * 미분류 로그를 최대 $limit 건 분류·요약해 저장한다.
     *
     * @return array{processed:int, skipped:int}
     */
    public function classifyPending(int $limit = 100): array
    {
        $ai = $this->ai ?? service('aiClient');

        // AI 미설정이면 아무것도 하지 않는다(파이프라인은 배선되어 있으나 no-op).
        if (! $ai->isConfigured()) {
            return ['processed' => 0, 'skipped' => 0];
        }

        $model     = model(LogModel::class);
        $rows      = $model->findPendingAiClassification($limit);
        $processed = 0;
        $skipped   = 0;

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id === 0) {
                continue;
            }

            try {
                $result = $this->classifyOne($ai, $row);
                $model->update($id, [
                    'ai_category'     => $result['category']->value,
                    'ai_summary'      => $result['summary'],
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
     * 격리해 뒤의 새 로그가 막히지 않게 한다(형제 LogQueueConsumer 의 dead-letter 원칙과 정합).
     *
     * @param array<string, mixed> $row
     */
    private function recordFailure(LogModel $model, array $row, int $id, string $reason): void
    {
        $attempts = (isset($row['ai_attempts']) ? (int) $row['ai_attempts'] : 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            // dead-letter: 큐에서 제거(ai_processed_at 설정)하되 ai_category 는 null 로 남겨
            // "반복 실패로 미분류"임을 정상 분류('other')와 구분한다.
            $model->update($id, ['ai_attempts' => $attempts, 'ai_processed_at' => date('Y-m-d H:i:s')]);
            log_message('error', sprintf('AI 로그 분류 %d회 실패로 dead-letter(log_id=%d): %s', $attempts, $id, $reason));

            return;
        }

        $model->update($id, ['ai_attempts' => $attempts]);
        log_message('warning', sprintf('AI 로그 분류 실패, 재시도 예정(log_id=%d, 시도=%d): %s', $id, $attempts, $reason));
    }

    /**
     * 로그 한 건을 분류·요약한다. 파싱 실패는 예외로 던져 재시도·격리 대상이 되게 한다.
     *
     * @param array<string, mixed> $row
     *
     * @return array{category: LogCategory, summary: string}
     *
     * @throws AiException 파싱 실패(응답은 왔으나 사용 불가)
     */
    private function classifyOne(AiClient $ai, array $row): array
    {
        $raw    = $ai->complete(self::MODEL, $this->systemPrompt(), $this->userPrompt($row));
        $parsed = $this->parse($raw);

        // 파싱 실패는 조용히 'other' 로 삼키지 않고 실패로 처리한다(가시화 + 재시도/격리).
        if ($parsed === null) {
            throw new AiException('AI 응답을 파싱할 수 없습니다.', 'AI_PARSE_FAILED', 502);
        }

        return [
            'category' => LogCategory::fromString($parsed['category']),
            'summary'  => mb_substr(trim($parsed['summary']), 0, 500),
        ];
    }

    /**
     * AI 응답(JSON 문자열)에서 category·summary 를 추출한다. 파싱 불가 시 null.
     *
     * @return array{category: string, summary: string}|null
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

        return [
            'category' => isset($decoded['category']) ? (string) $decoded['category'] : '',
            'summary'  => isset($decoded['summary']) ? (string) $decoded['summary'] : '',
        ];
    }

    private function systemPrompt(): string
    {
        return '너는 소프트웨어 라이선스 관리 시스템의 로그 분석기다. '
            . '주어진 로그를 아래 카테고리 중 하나로 분류하고 한국어 한 문장으로 요약하라. '
            . '카테고리(값 그대로 사용): ' . LogCategory::allowedValuesCsv() . '. '
            . '반드시 {"category":"<값>","summary":"<한 문장 요약>"} 형식의 JSON 만 출력하라.';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function userPrompt(array $row): string
    {
        $level      = (string) ($row['level'] ?? 'info');
        $source     = (string) ($row['source'] ?? '');
        $message    = (string) ($row['message'] ?? '');
        $context    = $row['context'] ?? null;
        $context    = is_string($context) ? $context : (string) json_encode($context, JSON_UNESCAPED_UNICODE);
        $hasContext = $context !== '' && $context !== 'null' && $context !== 'false';

        return sprintf(
            "level: %s\nsource: %s\nmessage: %s\ncontext: %s",
            $level,
            $source !== '' ? $source : '(none)',
            $message !== '' ? $message : '(none)',
            $hasContext ? $context : '(none)',
        );
    }
}
