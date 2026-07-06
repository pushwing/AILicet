<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Enums\CustomerType;
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 도메인 Backed Enum 단위 테스트 (DB 불필요).
 *
 * @internal
 */
final class EnumTest extends CIUnitTestCase
{
    public function testLicenseTypeBackingAndLabel(): void
    {
        $this->assertSame('nodelock', LicenseType::NodeLock->value);
        $this->assertSame('floating', LicenseType::Floating->value);
        $this->assertSame(LicenseType::Floating, LicenseType::from('floating'));
        $this->assertSame('노드락', LicenseType::NodeLock->label());
    }

    public function testLicenseStatusUsability(): void
    {
        $this->assertTrue(LicenseStatus::Active->isUsable());
        $this->assertFalse(LicenseStatus::Suspended->isUsable());
        $this->assertFalse(LicenseStatus::Terminated->isUsable());
        $this->assertSame('보관', LicenseStatus::Archived->label());
    }

    public function testPeriodCodeExpireDateRule(): void
    {
        $this->assertTrue(PeriodCode::Period->hasExpireDate());
        $this->assertTrue(PeriodCode::PeriodCount->hasExpireDate());
        $this->assertFalse(PeriodCode::Perpetual->hasExpireDate());
        $this->assertFalse(PeriodCode::PerpetualCredit->hasExpireDate());
    }

    public function testPeriodCodeUsageLimitRule(): void
    {
        $this->assertTrue(PeriodCode::PeriodCount->hasUsageLimit());
        $this->assertTrue(PeriodCode::PerpetualCount->hasUsageLimit());
        $this->assertTrue(PeriodCode::PerpetualCredit->hasUsageLimit());
        $this->assertFalse(PeriodCode::Perpetual->hasUsageLimit());
        $this->assertFalse(PeriodCode::Period->hasUsageLimit());
    }

    public function testAllEnumsExposeLabels(): void
    {
        foreach ([
            CustomerType::Agency,
            HistoryType::Reissue,
            AuditEventType::IllegalHost,
        ] as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
