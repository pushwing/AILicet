<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 고객 문의 AI 자동 분류·답변 초안 배치 — 미처리 문의를 골라 분류·초안을 채운다.
 *
 *   php spark ai:draft-inquiries [--limit 50]
 *
 * CI4 Scheduler(Config\Tasks)로 5분마다 자동 실행된다.
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op(처리 0건).
 */
final class AiDraftInquiries extends BaseCommand
{
    protected $group       = 'AI';
    protected $name        = 'ai:draft-inquiries';
    protected $description = '미처리 고객 문의를 AI 로 분류하고 답변 초안을 생성해 저장.';
    protected $usage       = 'ai:draft-inquiries [--limit N]';
    protected $options     = ['--limit' => '한 번에 처리할 최대 건수(기본 50)'];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $limitOption = CLI::getOption('limit');
        $limit       = is_numeric($limitOption) ? (int) $limitOption : 50;
        $result      = service('inquiryClassificationService')->draftPending($limit > 0 ? $limit : 50);

        CLI::write(
            sprintf('초안 %d건 / 건너뜀 %d건', $result['processed'], $result['skipped']),
            $result['skipped'] > 0 ? 'yellow' : 'green'
        );
    }
}
