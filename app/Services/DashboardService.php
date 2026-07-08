<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Models\AuditLogModel;
use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;

/**
 * 대시보드 집계(읽기) 유스케이스 — 관리 첫 화면의 통계 카드·차트·최근 라이선스.
 *
 * 저트래픽 관리 화면이지만 부하분산 원칙에 따라 집계 결과를 짧은 TTL 캐시로 서빙한다.
 * 신선도는 5분 TTL 로 확보한다(발급/종료 즉시 무효화 대신 TTL 만료 기준).
 *
 * @phpstan-type StatCard array{label:string, value:string, delta:string, dir:string}
 * @phpstan-type DashboardSummary array{
 *     stats: list<StatCard>,
 *     chart: array{labels:list<string>, values:list<int>},
 *     rows: list<array{sn:string, product:string, type:string, customer:string, status:string, expire:string}>
 * }
 */
final class DashboardService
{
    /** 집계 결과 캐시 키. (CI4 캐시 키 예약문자 `:` 불가 → `.` 구분) */
    private const string CACHE_KEY = 'dashboard.summary';

    /** 집계 캐시 TTL(초) — 5분. */
    private const int CACHE_TTL = 300;

    /** 만료 임박 기준 일수. */
    private const int EXPIRING_DAYS = 15;

    /** 최근 라이선스 표시 건수. */
    private const int RECENT_LIMIT = 8;

    /** 발급 추이 차트 표시 개월 수. */
    private const int CHART_MONTHS = 6;

    /**
     * 대시보드 요약(캐시). 통계 카드·차트·최근 라이선스.
     *
     * @return DashboardSummary
     */
    public function summary(): array
    {
        $cache  = cache();
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            /** @var DashboardSummary $cached */
            return $cached;
        }

        $summary = [
            'stats' => $this->buildStats(),
            'chart' => $this->buildChart(),
            'rows'  => $this->buildRecentLicenses(),
        ];

        $cache->save(self::CACHE_KEY, $summary, self::CACHE_TTL);

        return $summary;
    }

    /**
     * 통계 카드 4종 — 실데이터 + 전월 대비 델타.
     *
     * @return list<StatCard>
     */
    private function buildStats(): array
    {
        $thisMonthStart = date('Y-m-01');
        $nextMonthStart = date('Y-m-01', strtotime('first day of next month'));
        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));

        $active        = $this->activeLicenseCount();
        $issuedThis    = $this->licensesIssuedBetween($thisMonthStart, $nextMonthStart);
        $issuedLast    = $this->licensesIssuedBetween($lastMonthStart, $thisMonthStart);
        $expiringSoon  = $this->expiringSoonCount();
        $abuseThis     = $this->abuseDetectedBetween($thisMonthStart, $nextMonthStart);
        $abuseLast     = $this->abuseDetectedBetween($lastMonthStart, $thisMonthStart);

        return [
            [
                'label' => '활성 라이선스',
                'value' => number_format($active),
                'delta' => $issuedThis > 0 ? "▲ {$issuedThis}건 이번 달" : '이번 달 신규 0건',
                'dir'   => 'up',
            ],
            [
                'label' => '이번 달 발급',
                'value' => number_format($issuedThis),
                'delta' => $this->monthOverMonthDelta($issuedThis, $issuedLast),
                'dir'   => $issuedThis >= $issuedLast ? 'up' : 'down',
            ],
            [
                'label' => '만료 임박(' . self::EXPIRING_DAYS . '일)',
                'value' => number_format($expiringSoon),
                'delta' => $expiringSoon > 0 ? '주의 필요' : '안정',
                'dir'   => $expiringSoon > 0 ? 'down' : 'up',
            ],
            [
                'label' => '부정사용 감지',
                'value' => number_format($abuseThis),
                'delta' => $this->abuseDelta($abuseThis, $abuseLast),
                'dir'   => $abuseThis > 0 ? 'down' : 'up',
            ],
        ];
    }

    /**
     * 최근 N개월 발급 추이(빈 달 0 채움).
     *
     * @return array{labels:list<string>, values:list<int>}
     */
    private function buildChart(): array
    {
        $labels  = [];
        $buckets = [];
        for ($i = self::CHART_MONTHS - 1; $i >= 0; $i--) {
            $ts       = strtotime("first day of -{$i} month");
            $labels[] = (int) date('n', $ts) . '월';
            $buckets[date('Y-m', $ts)] = 0;
        }

        $start = date('Y-m-01', strtotime('first day of -' . (self::CHART_MONTHS - 1) . ' month'));

        /** @var list<array{ym:string, c:int}> $rows */
        $rows = model(LicenseModel::class)
            ->select("DATE_FORMAT(issue_date, '%Y-%m') AS ym, COUNT(*) AS c", false)
            ->where('issue_date >=', $start)
            ->where('issue_date IS NOT NULL')
            ->where('deleted_at', null)
            ->groupBy('ym')
            ->findAll();

        foreach ($rows as $row) {
            $ym = (string) $row['ym'];
            if (array_key_exists($ym, $buckets)) {
                $buckets[$ym] = (int) $row['c'];
            }
        }

        return ['labels' => $labels, 'values' => array_values($buckets)];
    }

    /**
     * 최근 라이선스 목록 — 상품명·고객명·시리얼(N+1 없이 배치 조회).
     *
     * @return list<array{sn:string, product:string, type:string, customer:string, status:string, expire:string}>
     */
    private function buildRecentLicenses(): array
    {
        /** @var list<array<string, mixed>> $licenses */
        $licenses = model(LicenseModel::class)
            ->select('licenses.id, licenses.license_type, licenses.status, licenses.expire_date, products.name AS product_name')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->orderBy('licenses.id', 'DESC')
            ->findAll(self::RECENT_LIMIT);

        if ($licenses === []) {
            return [];
        }

        $ids       = array_map(static fn (array $l): int => (int) $l['id'], $licenses);
        $customers = $this->customerNamesByLicense($ids);
        $serials   = $this->serialsByLicense($ids);

        $rows = [];
        foreach ($licenses as $license) {
            $id     = (int) $license['id'];
            $type   = LicenseType::tryFrom((string) $license['license_type']);
            $expire = (string) ($license['expire_date'] ?? '');
            $rows[] = [
                'sn'       => $serials[$id] ?? '#' . $id,
                'product'  => (string) ($license['product_name'] ?? '—'),
                'type'     => $type?->label() ?? (string) $license['license_type'],
                'customer' => $customers[$id] ?? '—',
                'status'   => (string) $license['status'],
                'expire'   => $expire !== '' ? $expire : '무기한',
            ];
        }

        return $rows;
    }

    /**
     * 라이선스별 대표 고객명(가장 먼저 연결된 고객).
     *
     * @param list<int> $licenseIds
     *
     * @return array<int, string>
     */
    private function customerNamesByLicense(array $licenseIds): array
    {
        /** @var list<array{license_id:int, company_name:?string, name:?string}> $rows */
        $rows = model(CustomerModel::class)
            ->select('customer_license.license_id AS license_id, customers.company_name, customers.name')
            ->join('customer_license', 'customer_license.customer_id = customers.id')
            ->whereIn('customer_license.license_id', $licenseIds)
            ->orderBy('customer_license.id', 'ASC')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row['license_id'];
            if (isset($map[$id])) {
                continue; // 대표 1건만
            }
            $company  = (string) ($row['company_name'] ?? '');
            $map[$id] = $company !== '' ? $company : (string) ($row['name'] ?? '');
        }

        return $map;
    }

    /**
     * 라이선스별 최신 발급 시리얼(license_history.license_sn).
     *
     * @param list<int> $licenseIds
     *
     * @return array<int, string>
     */
    private function serialsByLicense(array $licenseIds): array
    {
        /** @var list<array{license_id:int, license_sn:?string}> $rows */
        $rows = model(LicenseHistoryModel::class)
            ->select('license_id, license_sn')
            ->whereIn('license_id', $licenseIds)
            ->whereIn('type', ['issue', 'reissue'])
            ->orderBy('id', 'DESC')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row['license_id'];
            $sn = (string) ($row['license_sn'] ?? '');
            if (!isset($map[$id]) && $sn !== '') {
                $map[$id] = $sn; // 최신(id DESC) 1건만
            }
        }

        return $map;
    }

    /** 현재 활성 라이선스 수. */
    private function activeLicenseCount(): int
    {
        return model(LicenseModel::class)
            ->where('status', LicenseStatus::Active->value)
            ->where('deleted_at', null)
            ->countAllResults();
    }

    /** [start, end) 기간에 발급(issue_date)된 라이선스 수. */
    private function licensesIssuedBetween(string $start, string $end): int
    {
        return model(LicenseModel::class)
            ->where('issue_date >=', $start)
            ->where('issue_date <', $end)
            ->where('deleted_at', null)
            ->countAllResults();
    }

    /** 만료 임박(활성 + expire_date 가 오늘~+N일) 라이선스 수. */
    private function expiringSoonCount(): int
    {
        return model(LicenseModel::class)
            ->where('status', LicenseStatus::Active->value)
            ->where('expire_date >=', date('Y-m-d'))
            ->where('expire_date <=', date('Y-m-d', strtotime('+' . self::EXPIRING_DAYS . ' days')))
            ->where('deleted_at', null)
            ->countAllResults();
    }

    /** [start, end) 기간에 감지된 부정사용(audit_logs) 수. */
    private function abuseDetectedBetween(string $start, string $end): int
    {
        return model(AuditLogModel::class)
            ->where('created_at >=', $start)
            ->where('created_at <', $end)
            ->countAllResults();
    }

    /** 발급 건수 전월 대비 델타 문구. */
    private function monthOverMonthDelta(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '▲ 신규 발급' : '발급 없음';
        }

        $pct = round(($current - $previous) / $previous * 100, 1);

        return ($pct >= 0 ? '▲ ' : '▼ ') . abs($pct) . '% 전월 대비';
    }

    /** 부정사용 감지 전월 대비 델타 문구. */
    private function abuseDelta(int $current, int $previous): string
    {
        if ($current === 0 && $previous === 0) {
            return '이상 없음';
        }

        $diff = $current - $previous;
        $mark = $diff > 0 ? '▲ ' : ($diff < 0 ? '▼ ' : '– ');

        return $mark . abs($diff) . '건 전월 대비';
    }
}
