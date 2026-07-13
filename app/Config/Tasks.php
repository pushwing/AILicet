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
        // 매일 00:05 라이센스 일일 배치(만료 알림·종료·부정사용 감지)
        $schedule->command('license:daily')->daily('00:05')->named('license-daily');

        // 매분 로그 큐 소비(원시파일 + DB)
        $schedule->command('logs:consume')->everyMinute()->named('logs-consume');

        // 5분마다 미분류 로그 AI 분류·요약(ANTHROPIC_API_KEY 미설정 시 no-op)
        // singleInstance: 배치가 5분을 넘겨도 다음 틱과 겹쳐 AI 이중 호출되지 않도록 캐시 락.
        $schedule->command('ai:classify-logs')->everyFiveMinutes()->named('ai-classify-logs')->singleInstance();

        // 5분마다 미처리 고객 문의 AI 분류·답변 초안 생성(ANTHROPIC_API_KEY 미설정 시 no-op)
        $schedule->command('ai:draft-inquiries')->everyFiveMinutes()->named('ai-draft-inquiries')->singleInstance();

        // 5분마다 미설명 감사 로그 AI 사람용 설명 생성(ANTHROPIC_API_KEY 미설정 시 no-op)
        $schedule->command('ai:explain-audit-logs')->everyFiveMinutes()->named('ai-explain-audit-logs')->singleInstance();
    }
}
