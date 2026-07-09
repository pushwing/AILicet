<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 수집 로그 AI 자동 분류·요약 배치 — 미분류 로그를 골라 카테고리·요약을 채운다.
 *
 *   php spark ai:classify-logs [--limit 100]
 *
 * CI4 Scheduler(Config\Tasks)로 5분마다 자동 실행된다.
 * ANTHROPIC_API_KEY 미설정 시 안전한 no-op(처리 0건).
 */
final class AiClassifyLogs extends BaseCommand
{
    protected $group       = 'AI';
    protected $name        = 'ai:classify-logs';
    protected $description = '미분류 수집 로그를 AI 로 분류·요약해 저장.';
    protected $usage       = 'ai:classify-logs [--limit N]';
    protected $options     = ['--limit' => '한 번에 처리할 최대 건수(기본 100)'];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $limitOption = CLI::getOption('limit');
        $limit       = is_numeric($limitOption) ? (int) $limitOption : 100;
        $result      = service('logClassificationService')->classifyPending($limit > 0 ? $limit : 100);

        CLI::write(
            sprintf('분류 %d건 / 건너뜀 %d건', $result['processed'], $result['skipped']),
            $result['skipped'] > 0 ? 'yellow' : 'green'
        );
    }
}
