<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 라이센스 일일 배치 — 만료 임박 알림 / 만료 종료 / 부정사용 감지.
 *
 *   php spark license:daily
 *
 * CI4 Scheduler(Config\Tasks)로 매일 00:05 자동 실행된다.
 * 만료 임박·종료는 회원·대행사·운영자에게 인앱 메시지로 발송하고,
 * 부정사용 감지는 운영팀 Slack 으로 알린다.
 */
final class LicenseDaily extends BaseCommand
{
    protected $group       = 'License';
    protected $name        = 'license:daily';
    protected $description = '만료 알림·종료·부정사용 감지 일일 배치.';

    /** 만료 임박 알림 기준(일) — 1달/1주/1일 전. */
    private const array ALERT_DAYS = [30, 7, 1];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $notify = service('notificationService');
        $expiry = service('licenseExpiryService');

        // 1) 만료 임박 알림 → 회원·대행사·운영자 인앱 메시지
        foreach (self::ALERT_DAYS as $days) {
            $list = $expiry->expiringInDays($days);
            if ($list === []) {
                continue;
            }

            foreach ($list as $license) {
                $notify->notifyLicenseExpiring($license, $days);
            }
            $notify->notifyExpiringSummaryToOperators($days, $list);
            CLI::write("  만료 {$days}일 전: " . count($list) . '건 알림', 'yellow');
        }

        // 2) 만료 종료 처리 → 상태 종료 + 회원·대행사·운영자 인앱 메시지
        $terminated = $expiry->terminateExpired();
        if ($terminated !== []) {
            $notify->notifyLicenseExpired($terminated);
            CLI::write('  만료 종료: ' . count($terminated) . '건', 'green');
        }

        // 3) 부정사용 감지(어제 사용 로그) → 운영팀 Slack
        $entries  = $this->readRawLog(date('Y-m-d', strtotime('-1 day')));
        $detected = service('abuseDetectionService')->detect($entries);
        if ($detected !== []) {
            $lines = array_map(static fn ($d) => "{$d['event_type']} · key {$d['license_key']}", $detected);
            service('notifier')->send('부정사용 감지', implode("\n", $lines), 'error');
            CLI::write('  부정사용 감지: ' . count($detected) . '건', 'red');
        }

        // 3-2) AI 이상 탐지 보강(행동 패턴) → 운영팀 Slack 초안(실제 정지는 사람 확정)
        $anomalies = service('aiAbuseDetectionService')->detect($entries);
        if ($anomalies !== []) {
            $lines = array_map(static fn ($a) => "[{$a['severity']}] key {$a['license_key']} — {$a['reason']}", $anomalies);
            service('notifier')->send('AI 부정사용 이상 탐지(초안)', implode("\n", $lines), 'warning');
            CLI::write('  AI 이상 탐지: ' . count($anomalies) . '건(초안)', 'yellow');
        }

        CLI::write('license:daily 완료', 'green');
    }

    /**
     * 원시 사용 로그(날짜별 JSON 라인)를 엔트리 배열로 읽는다.
     *
     * @return list<array{license_key?:string, host_id?:string, ip?:string, logged_at?:string}>
     */
    private function readRawLog(string $date): array
    {
        $base = (string) (env('abuse.rawLogPath') ?: WRITEPATH . 'logs/raw');
        $file = rtrim($base, '/') . '/' . $date . '.log';
        if (! is_file($file)) {
            return [];
        }

        $entries = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && isset($row['license_key'])) {
                // frontApi 사용 로그(NodeLockAuthService)는 시각을 'ts'(ISO 8601)로 남긴다.
                // 다른 생산자 호환을 위해 logged_at 도 폴백으로 허용한다.
                $entries[] = [
                    'license_key' => (string) $row['license_key'],
                    'host_id'     => (string) ($row['host_id'] ?? ''),
                    'ip'          => (string) ($row['ip'] ?? ''),
                    'logged_at'   => (string) ($row['ts'] ?? $row['logged_at'] ?? ''),
                ];
            }
        }

        return $entries;
    }
}
