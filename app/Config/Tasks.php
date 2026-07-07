<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Tasks\Config\Tasks as BaseTasks;
use CodeIgniter\Tasks\Scheduler;

/**
 * CI4 Tasks 스케줄러 — 주기 실행 작업 정의.
 *
 * 실제 주기 실행은 시스템 크론이 매분 `php spark tasks:run` 을 호출하도록 설정한다:
 *   * * * * * cd /path/to/app && php spark tasks:run >> /dev/null 2>&1
 */
final class Tasks extends BaseTasks
{
    public function init(Scheduler $schedule): void
    {
        // 매일 09:00 라이센스 일일 배치(만료 알림·종료·부정사용 감지)
        $schedule->command('license:daily')->daily('09:00')->named('license-daily');
    }
}
