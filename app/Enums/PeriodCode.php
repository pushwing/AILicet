<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 라이센스 기간·사용량 정책.
 *
 * 레거시 수치 코드(1~5)를 의미 기반 문자열로 매핑한다.
 * - Perpetual(1)        : 영구
 * - Period(2)           : 기간 제한
 * - PeriodCount(3)      : 기간 제한 + 사용 횟수
 * - PerpetualCount(4)   : 영구 + 사용 횟수
 * - PerpetualCredit(5)  : 영구 + 크레딧
 */
enum PeriodCode: string
{
    case Perpetual       = 'perpetual';
    case Period          = 'period';
    case PeriodCount     = 'period_count';
    case PerpetualCount  = 'perpetual_count';
    case PerpetualCredit = 'perpetual_credit';

    public function label(): string
    {
        return match ($this) {
            self::Perpetual       => '영구',
            self::Period          => '기간 제한',
            self::PeriodCount     => '기간 제한 + 횟수',
            self::PerpetualCount  => '영구 + 횟수',
            self::PerpetualCredit => '영구 + 크레딧',
        };
    }

    /** 만료일(expire_date)이 필요한 정책인가. */
    public function hasExpireDate(): bool
    {
        return match ($this) {
            self::Period, self::PeriodCount => true,
            default                         => false,
        };
    }

    /** 사용 횟수/크레딧 제한이 있는 정책인가(config 필요). */
    public function hasUsageLimit(): bool
    {
        return match ($this) {
            self::PeriodCount, self::PerpetualCount, self::PerpetualCredit => true,
            default                                                        => false,
        };
    }
}
