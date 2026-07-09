<?php

declare(strict_types=1);

use App\Services\LicensePolicyValidator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 기간정책별 발급 입력 검증·정규화 단위 테스트 (DB 불필요). 이슈 #48.
 *
 * @internal
 */
final class LicensePolicyValidatorTest extends CIUnitTestCase
{
    private LicensePolicyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LicensePolicyValidator();
    }

    public function testInvalidPeriodCodeRejected(): void
    {
        $this->expectExceptionMessage('유효하지 않은 기간정책입니다.');
        $this->validator->normalize('bogus', null, null, []);
    }

    public function testPerpetualStripsAllLockedFields(): void
    {
        // 영구: 만료일/기술지원종료일/횟수/크레딧 모두 잠금 → 값이 들어와도 제거된다.
        $result = $this->validator->normalize(
            'perpetual',
            '2030-01-01',
            '2030-06-01',
            ['count' => 10, 'credit' => 500],
        );

        $this->assertNull($result['expire_date']);
        $this->assertNull($result['support_end_date']);
        $this->assertSame([], $result['limits']);
    }

    public function testPeriodRequiresBothDates(): void
    {
        $result = $this->validator->normalize('period', '2030-01-01', '2030-06-01', ['count' => 10]);

        $this->assertSame('2030-01-01', $result['expire_date']);
        $this->assertSame('2030-06-01', $result['support_end_date']);
        // 기간 제한(횟수 없음)은 limits 잠금 → 제거
        $this->assertSame([], $result['limits']);
    }

    public function testPeriodMissingExpireDateRejected(): void
    {
        $this->expectExceptionMessage('만료일이 필수입니다.');
        $this->validator->normalize('period', '', '2030-06-01', []);
    }

    public function testPeriodMissingSupportEndDateRejected(): void
    {
        $this->expectExceptionMessage('기술지원 종료일이 필수입니다.');
        $this->validator->normalize('period', '2030-01-01', null, []);
    }

    public function testPeriodCountKeepsDatesAndCountOnly(): void
    {
        $result = $this->validator->normalize(
            'period_count',
            '2030-01-01',
            '2030-06-01',
            ['count' => 30, 'credit' => 999],
        );

        $this->assertSame('2030-01-01', $result['expire_date']);
        $this->assertSame('2030-06-01', $result['support_end_date']);
        $this->assertSame(['count' => 30], $result['limits']);
    }

    public function testPeriodCountMissingCountRejected(): void
    {
        $this->expectExceptionMessage('사용횟수 제한이 필수입니다.');
        $this->validator->normalize('period_count', '2030-01-01', '2030-06-01', []);
    }

    public function testPerpetualCountKeepsCountOnly(): void
    {
        $result = $this->validator->normalize('perpetual_count', '2030-01-01', '2030-06-01', ['count' => 5]);

        $this->assertNull($result['expire_date']);
        $this->assertNull($result['support_end_date']);
        $this->assertSame(['count' => 5], $result['limits']);
    }

    public function testPerpetualCreditKeepsCreditOnly(): void
    {
        $result = $this->validator->normalize('perpetual_credit', null, null, ['count' => 5, 'credit' => 500]);

        $this->assertNull($result['expire_date']);
        $this->assertSame(['credit' => 500], $result['limits']);
    }

    public function testPerpetualCreditMissingCreditRejected(): void
    {
        $this->expectExceptionMessage('크레딧 제한이 필수입니다.');
        $this->validator->normalize('perpetual_credit', null, null, []);
    }
}
