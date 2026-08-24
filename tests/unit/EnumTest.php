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
        $this->assertSame('서명 파일·온라인 검증', LicenseType::NodeLock->authenticationMethodLabel());
        $this->assertSame('온라인 활성화·잔여 검증', LicenseType::Floating->authenticationMethodLabel());
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

    /**
     * 이슈 #48: 기간정책별 필드 요구 매트릭스.
     *
     * @return list<array{PeriodCode, array{expire:bool, count:bool, credit:bool}}>
     */
    public static function fieldRulesProvider(): array
    {
        return [
            [PeriodCode::Perpetual,       ['expire' => false, 'count' => false, 'credit' => false]],
            [PeriodCode::Period,          ['expire' => true,  'count' => false, 'credit' => false]],
            [PeriodCode::PeriodCount,     ['expire' => true,  'count' => true,  'credit' => false]],
            [PeriodCode::PerpetualCount,  ['expire' => false, 'count' => true,  'credit' => false]],
            [PeriodCode::PerpetualCredit, ['expire' => false, 'count' => false, 'credit' => true]],
        ];
    }

    /**
     * @param array{expire:bool, count:bool, credit:bool} $expected
     *
     * @dataProvider fieldRulesProvider
     */
    public function testPeriodCodeFieldRules(PeriodCode $code, array $expected): void
    {
        $this->assertSame($expected, $code->fieldRules());
        $this->assertSame($expected['expire'], $code->requiresExpireDate());
        $this->assertSame($expected['count'], $code->requiresCount());
        $this->assertSame($expected['credit'], $code->requiresCredit());
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
