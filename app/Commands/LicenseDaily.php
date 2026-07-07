<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 라이센스 일일 배치 — 만료 임박 알림 / 만료 종료 / 부정사용 감지 + 슬랙 알림.
 *
 *   php spark license:daily
 *
 * CI4 Scheduler(Config\Tasks)로 매일 09:00 자동 실행된다.
 */
final class LicenseDaily extends BaseCommand
{
    protected $group       = 'License';
    protected $name        = 'license:daily';
    protected $description = '만료 알림·종료·부정사용 감지 일일 배치.';

    /** 만료 임박 알림 기준(일). */
    private const array ALERT_DAYS = [15, 10];

    /**
     * @param list<string> $params
     */
    public function run(array $params): void
    {
        $notifier = service('notifier');
        $expiry   = service('licenseExpiryService');

        // 1) 만료 임박 알림
        foreach (self::ALERT_DAYS as $days) {
            $list = $expiry->expiringInDays($days);
            if ($list !== []) {
                $notifier->send("라이센스 만료 {$days}일 전", $this->summarize($list), 'warning');
                CLI::write("  만료 {$days}일 전: " . count($list) . '건 알림', 'yellow');
            }
        }

        // 2) 만료 종료 처리
        $terminated = $expiry->terminateExpired();
        if ($terminated !== []) {
            $notifier->send('라이센스 만료 종료', count($terminated) . '건 자동 종료 (id: ' . implode(', ', $terminated) . ')', 'info');
            CLI::write('  만료 종료: ' . count($terminated) . '건', 'green');
        }

        // 3) 부정사용 감지 (어제 사용 로그)
        $entries  = $this->readRawLog(date('Y-m-d', strtotime('-1 day')));
        $detected = service('abuseDetectionService')->detect($entries);
        if ($detected !== []) {
            $lines = array_map(static fn ($d) => "{$d['event_type']} · key {$d['license_key']}", $detected);
            $notifier->send('부정사용 감지', implode("\n", $lines), 'error');
            CLI::write('  부정사용 감지: ' . count($detected) . '건', 'red');
        }

        CLI::write('license:daily 완료', 'green');
    }

    /**
     * @param list<array<string, mixed>> $list
     */
    private function summarize(array $list): string
    {
        $lines = array_map(
            static fn ($l) => "#{$l['id']} (만료 {$l['expire_date']})",
            array_slice($list, 0, 20),
        );

        return implode("\n", $lines) . (count($list) > 20 ? "\n… 외 " . (count($list) - 20) . '건' : '');
    }

    /**
     * 원시 사용 로그(날짜별 JSON 라인)를 엔트리 배열로 읽는다.
     *
     * @return list<array{license_key?:string, host_id?:string, ip?:string}>
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
                $entries[] = [
                    'license_key' => (string) $row['license_key'],
                    'host_id'     => (string) ($row['host_id'] ?? ''),
                    'ip'          => (string) ($row['ip'] ?? ''),
                ];
            }
        }

        return $entries;
    }
}
