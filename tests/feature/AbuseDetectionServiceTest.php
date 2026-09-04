<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Services\AbuseDetectionService;
use Tests\Support\DatabaseTestCase;

/**
 * 부정사용 감지 서비스 DB 통합 테스트.
 *
 * @internal
 */
final class AbuseDetectionServiceTest extends DatabaseTestCase
{
    private AbuseDetectionService $service;
    private int $licenseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbuseDetectionService();

        $pid = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => '상품', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
        $this->licenseId = (int) model(LicenseModel::class)->insert([
            'product_id' => $pid, 'license_type' => 'nodelock', 'period_code' => 'perpetual',
            'status' => 'active', 'host_id' => 'HOST-A',
        ], true);
        // 발급(KEY-OLD) → 재발급(KEY-NEW): KEY-OLD 는 폐기됨
        model(LicenseHistoryModel::class)->insert(['license_id' => $this->licenseId, 'type' => 'issue', 'license_key' => 'KEY-OLD']);
        model(LicenseHistoryModel::class)->insert(['license_id' => $this->licenseId, 'type' => 'reissue', 'license_key' => 'KEY-NEW']);
    }

    public function testDetectsRevokedKeyUse(): void
    {
        $detected = $this->service->detect([['license_key' => 'KEY-OLD', 'host_id' => 'HOST-A', 'ip' => '1.1.1.1']]);

        $this->assertCount(1, $detected);
        $this->assertSame(AuditEventType::RevokedKeyUse->value, $detected[0]['event_type']);
        $this->seeInDatabase('audit_logs', [
            'license_id'  => $this->licenseId,
            'event_type'  => AuditEventType::RevokedKeyUse->value,
            'license_key' => 'KEY-OLD',
        ]);
    }

    public function testDetectsHostMismatch(): void
    {
        $detected = $this->service->detect([['license_key' => 'KEY-NEW', 'host_id' => 'WRONG-HOST']]);

        $this->assertCount(1, $detected);
        $this->assertSame(AuditEventType::IllegalHost->value, $detected[0]['event_type']);
    }

    public function testValidUsageNotFlagged(): void
    {
        $detected = $this->service->detect([['license_key' => 'KEY-NEW', 'host_id' => 'HOST-A']]);
        $this->assertCount(0, $detected);
    }

    public function testUnknownKeyIgnored(): void
    {
        $detected = $this->service->detect([['license_key' => 'NOPE', 'host_id' => 'X']]);
        $this->assertCount(0, $detected);
    }

    public function testDuplicateDetectionNotRecordedTwice(): void
    {
        $entry = [['license_key' => 'KEY-OLD', 'host_id' => 'HOST-A']];
        $this->service->detect($entry);
        $again = $this->service->detect($entry);

        $this->assertCount(0, $again); // 이미 기록 → 신규 없음
        $this->assertSame(1, model(\App\Models\AuditLogModel::class)
            ->where('event_type', AuditEventType::RevokedKeyUse->value)->countAllResults());
    }
}
