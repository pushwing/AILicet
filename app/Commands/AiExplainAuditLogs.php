<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 감사 로그 AI 사람용 설명 생성 배치 — 미설명 감사 로그를 골라 한국어 설명을 채운다.
 *
 *   php spark ai:explain-audit-logs [--limit 100]
 *
 * CI4 Scheduler(Config\Tasks)로 5분마다 자동 실행된다.
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op(처리 0건).
 */
final class AiExplainAuditLogs extends BaseCommand
{
    protected $group       = 'AI';
    protected $name        = 'ai:explain-audit-logs';
    protected $description = '미설명 감사 로그를 AI 로 사람용 설명으로 변환해 저장.';
    protected $usage       = 'ai:explain-audit-logs [--limit N]';
    protected $options     = ['--limit' => '한 번에 처리할 최대 건수(기본 100)'];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $limitOption = CLI::getOption('limit');
        $limit       = is_numeric($limitOption) ? (int) $limitOption : 100;
        $result      = service('auditLogExplanationService')->explainPending($limit > 0 ? $limit : 100);

        CLI::write(
            sprintf('설명 %d건 / 건너뜀 %d건', $result['processed'], $result['skipped']),
            $result['skipped'] > 0 ? 'yellow' : 'green'
        );
    }
}
