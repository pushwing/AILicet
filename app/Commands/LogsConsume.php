<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 로그 큐 소비자 — 큐의 로그를 원시파일 + DB 로 처리.
 *
 *   php spark logs:consume [--max 1000]
 *
 * CI4 Scheduler(Config\Tasks)로 매분 자동 실행된다.
 */
final class LogsConsume extends BaseCommand
{
    protected $group       = 'Log';
    protected $name        = 'logs:consume';
    protected $description = '로그 큐를 소비해 원시파일 보존 + DB 저장.';
    protected $usage       = 'logs:consume [--max N]';
    protected $options     = ['--max' => '한 번에 처리할 최대 건수(기본 1000)'];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $maxOption = CLI::getOption('max');
        $max       = is_numeric($maxOption) ? (int) $maxOption : 1000;
        $result    = service('logQueueConsumer')->consume($max > 0 ? $max : 1000);

        CLI::write(sprintf('처리 %d건 / 실패 %d건', $result['processed'], $result['failed']),
            $result['failed'] > 0 ? 'yellow' : 'green');
    }
}
