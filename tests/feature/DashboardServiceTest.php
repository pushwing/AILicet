<?php

declare(strict_types=1);

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
}
