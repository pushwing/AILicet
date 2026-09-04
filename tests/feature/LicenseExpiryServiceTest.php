<?php

declare(strict_types=1);

use App\Enums\LicenseStatus;
use App\Models\LicenseModel;
use App\Services\LicenseExpiryService;
use Tests\Support\DatabaseTestCase;

/**
 * 만료 배치 서비스 DB 통합 테스트.
 *
 * @internal
 */
final class LicenseExpiryServiceTest extends DatabaseTestCase
{
    private LicenseExpiryService $service;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service   = new LicenseExpiryService();
        $this->productId = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => '상품', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
    }

    private function seedLicense(?string $expire, string $status = 'active'): int
    {
        return (int) model(LicenseModel::class)->insert([
            'product_id' => $this->productId, 'license_type' => 'nodelock', 'period_code' => 'period',
            'status' => $status, 'host_id' => 'H', 'expire_date' => $expire,
        ], true);
    }

    public function testExpiringInDays(): void
    {
        $in15 = $this->seedLicense(date('Y-m-d', strtotime('+15 days')));
        $in10 = $this->seedLicense(date('Y-m-d', strtotime('+10 days')));
        $this->seedLicense(date('Y-m-d', strtotime('+5 days')));
        $this->seedLicense(null); // 무기한

        $r15 = $this->service->expiringInDays(15);
        $this->assertCount(1, $r15);
        $this->assertSame($in15, (int) $r15[0]['id']);

        $r10 = $this->service->expiringInDays(10);
        $this->assertCount(1, $r10);
        $this->assertSame($in10, (int) $r10[0]['id']);
    }

    public function testTerminateExpired(): void
    {
        $past   = $this->seedLicense(date('Y-m-d', strtotime('-1 day')));
        $future = $this->seedLicense(date('Y-m-d', strtotime('+30 days')));

        $terminated = $this->service->terminateExpired();

        $this->assertSame([$past], $terminated);
        $this->seeInDatabase('licenses', ['id' => $past, 'status' => LicenseStatus::Terminated->value]);
        $this->seeInDatabase('licenses', ['id' => $future, 'status' => LicenseStatus::Active->value]);
        // 종료 이력 기록
        $this->seeInDatabase('license_history', ['license_id' => $past, 'type' => 'status_change']);
    }
}
