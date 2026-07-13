<?php

declare(strict_types=1);

use App\Exceptions\AiException;
use App\Integrations\AiClient;
use App\Integrations\AiModelTier;
use App\Integrations\NullAiClient;
use App\Models\AuditLogModel;
use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Services\DashboardService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 대시보드 집계 서비스 DB 통합 테스트.
 *
 * @internal
 */
final class DashboardServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private DashboardService $service;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean(); // 이전 테스트의 캐시 잔여 제거

        $this->service   = new DashboardService();
        $this->productId = (int) model(ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => 'tES LAB', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedLicense(array $overrides = []): int
    {
        return (int) model(LicenseModel::class)->insert(array_merge([
            'product_id'   => $this->productId,
            'license_type' => 'nodelock',
            'period_code'  => 'period',
            'status'       => 'active',
            'host_id'      => 'H',
            'issue_date'   => date('Y-m-d'),
            'expire_date'  => null,
        ], $overrides), true);
    }

    public function testActiveAndIssuedThisMonthStats(): void
    {
        $this->seedLicense(['status' => 'active', 'issue_date' => date('Y-m-d')]);
        $this->seedLicense(['status' => 'active', 'issue_date' => date('Y-m-d')]);
        $this->seedLicense(['status' => 'suspended', 'issue_date' => date('Y-m-01', strtotime('first day of last month'))]);

        $stats = $this->service->summary()['stats'];

        // 활성 라이선스 = 2
        $this->assertSame('활성 라이선스', $stats[0]['label']);
        $this->assertSame('2', $stats[0]['value']);
        // 이번 달 발급 = 2 (지난 달 발급 1건은 제외)
        $this->assertSame('이번 달 발급', $stats[1]['label']);
        $this->assertSame('2', $stats[1]['value']);
    }

    public function testExpiringSoonStat(): void
    {
        $this->seedLicense(['expire_date' => date('Y-m-d', strtotime('+10 days'))]);  // 임박
        $this->seedLicense(['expire_date' => date('Y-m-d', strtotime('+20 days'))]);  // 범위 밖
        $this->seedLicense(['expire_date' => date('Y-m-d', strtotime('-1 day'))]);    // 이미 만료

        $stats = $this->service->summary()['stats'];

        $this->assertSame('만료 임박(15일)', $stats[2]['label']);
        $this->assertSame('1', $stats[2]['value']);
        $this->assertSame('down', $stats[2]['dir']);
    }

    public function testAbuseDetectionStatCountsThisMonth(): void
    {
        model(AuditLogModel::class)->insert([
            'license_id' => $this->seedLicense(),
            'event_type' => 'illegal_host',
            'license_key' => 'K1',
            'client_host_id' => 'H2',
        ]);

        $stats = $this->service->summary()['stats'];

        $this->assertSame('부정사용 감지', $stats[3]['label']);
        $this->assertSame('1', $stats[3]['value']);
        $this->assertSame('down', $stats[3]['dir']);
    }

    public function testChartCountsIssuanceByMonth(): void
    {
        $this->seedLicense(['issue_date' => date('Y-m-d')]);
        $this->seedLicense(['issue_date' => date('Y-m-d')]);

        $chart = $this->service->summary()['chart'];

        $this->assertCount(6, $chart['labels']);
        $this->assertCount(6, $chart['values']);
        // 마지막 버킷(이번 달) = 2
        $this->assertSame(2, $chart['values'][5]);
    }

    public function testRecentLicensesJoinProductCustomerAndSerial(): void
    {
        $licenseId  = $this->seedLicense(['status' => 'active']);
        $customerId = (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'company_name' => '뉴로핏', 'name' => '담당자', 'email' => 'a@b.com',
        ], true);
        db_connect()->table('customer_license')->insert([
            'customer_id' => $customerId, 'license_id' => $licenseId,
        ]);
        model(LicenseHistoryModel::class)->insert([
            'license_id' => $licenseId, 'type' => 'issue', 'license_sn' => 'PT001-260701-01',
        ]);

        $rows = $this->service->summary()['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('PT001-260701-01', $rows[0]['sn']);
        $this->assertSame('tES LAB', $rows[0]['product']);
        $this->assertSame('노드락', $rows[0]['type']);
        $this->assertSame('뉴로핏', $rows[0]['customer']);
        $this->assertSame('active', $rows[0]['status']);
        $this->assertSame('무기한', $rows[0]['expire']);
    }

    public function testSummaryIsCached(): void
    {
        $this->seedLicense(['status' => 'active']);
        $first = $this->service->summary();

        // 캐시 이후 삽입 → 캐시된 결과가 그대로 반환되어야 함
        $this->seedLicense(['status' => 'active']);
        $second = $this->service->summary();

        $this->assertSame($first['stats'][0]['value'], $second['stats'][0]['value']);
    }

    // ── 자연어 질의(queryInsight) ──

    /**
     * 등급별로 다른 응답을 주는 가짜 AiClient.
     * Reasoning(지표 해석) → intent JSON, Cheap(요약) → 인사이트 문장.
     */
    private function queryAi(string $intentJson, string $insight = '요약 문장입니다.', bool $configured = true): AiClient
    {
        return new class ($intentJson, $insight, $configured) implements AiClient {
            public function __construct(
                private readonly string $intentJson,
                private readonly string $insight,
                private readonly bool $configured,
            ) {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
            {
                return $tier === AiModelTier::Reasoning ? $this->intentJson : $this->insight;
            }
        };
    }

    public function testQueryInsightAggregatesWhitelistedMetric(): void
    {
        $this->seedLicense(['status' => 'active']);
        $this->seedLicense(['status' => 'active']);
        $this->seedLicense(['status' => 'suspended']);

        $ai     = $this->queryAi('{"metric":"active_licenses","period":"all_time"}', '활성 라이선스가 2건입니다.');
        $result = (new DashboardService($ai))->queryInsight('활성 라이선스 몇 개야?');

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['value']);
        $this->assertSame('활성 라이선스 수', $result['metric']);
        $this->assertSame('활성 라이선스가 2건입니다.', $result['insight']);
        $this->assertNull($result['error']);
    }

    public function testQueryInsightRespectsPeriodForIssuedMetric(): void
    {
        $this->seedLicense(['issue_date' => date('Y-m-d')]);                                        // 이번 달
        $this->seedLicense(['issue_date' => date('Y-m-01', strtotime('first day of last month'))]); // 지난 달

        $ai     = $this->queryAi('{"metric":"issued_licenses","period":"this_month"}');
        $result = (new DashboardService($ai))->queryInsight('이번 달 발급 건수 알려줘');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['value']);
        $this->assertSame('이번 달', $result['period']);
    }

    public function testQueryInsightRejectsUnknownMetric(): void
    {
        // 화이트리스트 밖 지표(SQL 시도 등)는 거부 — 인젝션 방어의 핵심.
        $ai     = $this->queryAi('{"metric":"drop_table","period":"all_time"}');
        $result = (new DashboardService($ai))->queryInsight('테이블 다 지워');

        $this->assertFalse($result['ok']);
        $this->assertSame('UNRECOGNIZED', $result['error']);
        $this->assertNull($result['value']);
    }

    public function testQueryInsightHandlesNonJsonResponse(): void
    {
        $ai     = $this->queryAi('죄송하지만 답할 수 없습니다.');
        $result = (new DashboardService($ai))->queryInsight('의미없는 질문');

        $this->assertFalse($result['ok']);
        $this->assertSame('UNRECOGNIZED', $result['error']);
    }

    public function testQueryInsightNoopWhenUnconfigured(): void
    {
        $result = (new DashboardService(new NullAiClient()))->queryInsight('활성 라이선스');

        $this->assertFalse($result['ok']);
        $this->assertSame('AI_NOT_CONFIGURED', $result['error']);
    }

    public function testQueryInsightIsolatesAiFailure(): void
    {
        $ai = new class () implements AiClient {
            public function isConfigured(): bool
            {
                return true;
            }

            public function complete(AiModelTier $tier, string $system, string $prompt, int $maxTokens = 1024): string
            {
                throw new AiException('통신 실패', 'AI_TIMEOUT', 504);
            }
        };

        $result = (new DashboardService($ai))->queryInsight('활성 라이선스');

        $this->assertFalse($result['ok']);
        $this->assertSame('AI_ERROR', $result['error']);
    }
}
