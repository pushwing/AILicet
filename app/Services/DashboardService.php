<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DashboardMetric;
use App\Enums\DashboardPeriod;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Models\AuditLogModel;
use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use Throwable;

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
 * @phpstan-type QueryResult array{
 *     ok: bool,
 *     question: string,
 *     metric: ?string,
 *     period: ?string,
 *     value: ?int,
 *     insight: string,
 *     error: ?string
 * }
 */
final class DashboardService
{
    /** 집계 결과 캐시 키. (CI4 캐시 키 예약문자 `:` 불가 → `.` 구분) */
    private const string CACHE_KEY = 'dashboard.summary';

    /** 집계 캐시 TTL(초) — 5분. */
    private const int CACHE_TTL = 300;

    /** 자연어 질의 결과 캐시 키 접두어(질의 해시가 뒤에 붙음). */
    private const string QUERY_CACHE_KEY = 'dashboard.query.';

    /** 자연어 질의 캐시 TTL(초) — 동일 질문 반복 호출 방어(짧게). */
    private const int QUERY_CACHE_TTL = 60;

    /** 만료 임박 기준 일수. */
    private const int EXPIRING_DAYS = 15;

    /** 최근 라이선스 표시 건수. */
    private const int RECENT_LIMIT = 8;

    /** 발급 추이 차트 표시 개월 수. */
    private const int CHART_MONTHS = 6;

    /**
     * @param AiClient|null $ai 자연어 질의용 AI 클라이언트. 런타임에는 service('aiClient') 지연 조회,
     *                          테스트에서는 fake 를 주입한다. 미설정(NullAiClient) 시 질의는 no-op.
     */
    public function __construct(
        private readonly ?AiClient $ai = null,
    ) {
    }

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
     * 자연어 질의 → 화이트리스트 집계 + AI 인사이트 요약.
     *
     * 흐름: (1) AI 추론 등급으로 질문을 화이트리스트 지표·기간(enum)으로만 매핑,
     * (2) 매핑값을 안전한 Query Builder 집계로 변환(AI 문자열이 SQL 에 직접 들어가지 않음),
     * (3) 집계 수치를 AI 저비용 등급으로 한국어 인사이트 요약.
     * AI 미설정 시 no-op(기존 대시보드는 그대로 동작). 성공 결과만 짧게 캐시한다.
     *
     * @return QueryResult
     */
    public function queryInsight(string $question): array
    {
        $question = trim($question);
        $ai       = $this->ai ?? service('aiClient');

        if (! $ai->isConfigured()) {
            return $this->queryError($question, 'AI_NOT_CONFIGURED', 'AI 질의 기능이 비활성화되어 있습니다.');
        }
        if ($question === '') {
            return $this->queryError($question, 'EMPTY_QUESTION', '질문을 입력해 주세요.');
        }

        $cacheKey = self::QUERY_CACHE_KEY . md5($question);
        $cached   = cache()->get($cacheKey);
        if (is_array($cached)) {
            /** @var QueryResult $cached */
            return $cached;
        }

        try {
            $intent = $this->resolveIntent($ai, $question);
        } catch (Throwable $e) {
            log_message('error', 'AI 대시보드 질의 해석 실패: ' . $e->getMessage());

            return $this->queryError($question, 'AI_ERROR', '일시적인 오류로 질의를 처리하지 못했습니다.');
        }

        if ($intent === null) {
            return $this->queryError(
                $question,
                'UNRECOGNIZED',
                '질문을 이해하지 못했습니다. 라이선스 발급·만료·부정사용 등 통계를 물어봐 주세요.',
            );
        }

        $metric = $intent['metric'];
        $period = $intent['period'];
        $value  = $this->aggregateMetric($metric, $period);

        try {
            $insight = $this->summarizeInsight($ai, $metric, $period, $value);
        } catch (Throwable $e) {
            log_message('error', 'AI 대시보드 인사이트 생성 실패: ' . $e->getMessage());
            $insight = $this->fallbackInsight($metric, $period, $value);
        }

        $result = [
            'ok'       => true,
            'question' => $question,
            'metric'   => $metric->label(),
            'period'   => $period->label(),
            'value'    => $value,
            'insight'  => $insight,
            'error'    => null,
        ];

        cache()->save($cacheKey, $result, self::QUERY_CACHE_TTL);

        return $result;
    }

    /**
     * 실패 결과(캐시하지 않음).
     *
     * @return QueryResult
     */
    private function queryError(string $question, string $error, string $insight): array
    {
        return [
            'ok'       => false,
            'question' => $question,
            'metric'   => null,
            'period'   => null,
            'value'    => null,
            'insight'  => $insight,
            'error'    => $error,
        ];
    }

    /**
     * AI 추론 등급으로 질문을 화이트리스트 지표·기간으로 매핑한다.
     * 화이트리스트 밖 값·비JSON 응답은 null(질의 거부) 로 처리한다.
     *
     * @return array{metric:DashboardMetric, period:DashboardPeriod}|null
     */
    private function resolveIntent(AiClient $ai, string $question): ?array
    {
        $raw = $ai->complete(AiModelTier::Reasoning, $this->intentSystemPrompt(), $question, 200);

        if (preg_match('/\{.*\}/s', $raw, $m) !== 1) {
            return null;
        }
        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return null;
        }

        $metric = DashboardMetric::tryFrom(strtolower(trim((string) ($decoded['metric'] ?? ''))));
        if ($metric === null) {
            return null; // 화이트리스트 밖 — 거부
        }

        $period = DashboardPeriod::tryFrom(strtolower(trim((string) ($decoded['period'] ?? 'all_time'))))
            ?? DashboardPeriod::AllTime;

        return ['metric' => $metric, 'period' => $period];
    }

    /**
     * 화이트리스트 지표·기간을 안전한 Query Builder 집계로 변환한다.
     * AI 가 만든 문자열은 여기 도달하지 않는다(enum 으로만 분기).
     */
    private function aggregateMetric(DashboardMetric $metric, DashboardPeriod $period): int
    {
        [$start, $end] = $metric->isPeriodic() ? $period->range() : [null, null];

        return match ($metric) {
            DashboardMetric::ActiveLicenses     => $this->activeLicenseCount(),
            DashboardMetric::IssuedLicenses     => $this->issuedCount($start, $end),
            DashboardMetric::ExpiringSoon       => $this->expiringSoonCount(),
            DashboardMetric::AbuseDetected      => $this->abuseCount($start, $end),
            DashboardMetric::SuspendedLicenses  => $this->statusCount(LicenseStatus::Suspended),
            DashboardMetric::TerminatedLicenses => $this->statusCount(LicenseStatus::Terminated),
        };
    }

    /**
     * 집계 수치를 AI 저비용 등급으로 한국어 인사이트 요약한다.
     * 빈 응답이면 결정적 폴백 문장으로 대체한다.
     */
    private function summarizeInsight(AiClient $ai, DashboardMetric $metric, DashboardPeriod $period, int $value): string
    {
        $payload = json_encode([
            'metric' => $metric->label(),
            'period' => $period->label(),
            'value'  => $value,
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        $insight = trim($ai->complete(AiModelTier::Cheap, $this->insightSystemPrompt(), $payload, 300));

        return $insight !== '' ? mb_substr($insight, 0, 500) : $this->fallbackInsight($metric, $period, $value);
    }

    /** AI 요약 실패·공백 시 사용할 결정적 요약 문장. */
    private function fallbackInsight(DashboardMetric $metric, DashboardPeriod $period, int $value): string
    {
        return sprintf('%s(%s): %s건', $metric->label(), $period->label(), number_format($value));
    }

    private function intentSystemPrompt(): string
    {
        $metrics = implode(', ', array_map(static fn (DashboardMetric $m): string => $m->value, DashboardMetric::cases()));
        $periods = implode(', ', array_map(static fn (DashboardPeriod $p): string => $p->value, DashboardPeriod::cases()));

        return '너는 라이선스 관리 대시보드의 질의 해석기다. '
            . '운영자의 한국어 질문을 아래 화이트리스트 지표·기간 중 하나로만 매핑하라. '
            . 'metric 후보: ' . $metrics . '. '
            . 'period 후보: ' . $periods . '. '
            . '질문이 어느 지표에도 해당하지 않으면 metric 을 빈 문자열로 둔다. '
            . '반드시 {"metric":"<후보 중 하나 또는 빈 문자열>","period":"<후보 중 하나>"} 형식의 JSON 만 출력하라. '
            . 'SQL·코드·설명을 절대 출력하지 마라.';
    }

    private function insightSystemPrompt(): string
    {
        return '너는 라이선스 관리 대시보드의 데이터 분석 요약가다. '
            . '주어진 지표·기간·수치(JSON)를 보고 운영자가 이해하기 쉬운 한국어 인사이트를 한두 문장으로 요약하라. '
            . '수치를 지어내지 말고 주어진 값만 사용하라. JSON·코드 없이 자연어 문장만 출력하라.';
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
            if (! isset($map[$id]) && $sn !== '') {
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

    /**
     * 발급(issue_date)된 라이선스 수. 경계가 null 이면 해당 방향 제한 없음(전체 기간).
     * 자연어 질의(issued_licenses)용 — 화이트리스트 기간만 넘어온다.
     */
    private function issuedCount(?string $start, ?string $end): int
    {
        $query = model(LicenseModel::class)
            ->where('deleted_at', null)
            ->where('issue_date IS NOT NULL');
        if ($start !== null) {
            $query->where('issue_date >=', $start);
        }
        if ($end !== null) {
            $query->where('issue_date <', $end);
        }

        return $query->countAllResults();
    }

    /**
     * 감지된 부정사용(audit_logs) 수. 경계가 null 이면 해당 방향 제한 없음(전체 기간).
     * 자연어 질의(abuse_detected)용.
     */
    private function abuseCount(?string $start, ?string $end): int
    {
        $query = model(AuditLogModel::class);
        if ($start !== null) {
            $query->where('created_at >=', $start);
        }
        if ($end !== null) {
            $query->where('created_at <', $end);
        }

        return $query->countAllResults();
    }

    /** 특정 상태의 (미삭제) 라이선스 수 — 자연어 질의(suspended/terminated)용. */
    private function statusCount(LicenseStatus $status): int
    {
        return model(LicenseModel::class)
            ->where('status', $status->value)
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
