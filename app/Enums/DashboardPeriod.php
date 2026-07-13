<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 대시보드 자연어 질의의 집계 기간(화이트리스트).
 *
 * AI 가 매핑한 기간 문자열을 이 enum 으로 검증한 뒤, range() 로 안전한 [start, end) 경계를 얻어
 * Query Builder 조건에만 사용한다(AI 문자열이 쿼리에 직접 들어가지 않음).
 */
enum DashboardPeriod: string
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear  = 'this_year';
    case AllTime   = 'all_time';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => '이번 달',
            self::LastMonth => '지난 달',
            self::ThisYear  => '올해',
            self::AllTime   => '전체 기간',
        };
    }

    /**
     * 집계 대상 [start, end) 반개구간. null 은 경계 없음(전체 기간).
     *
     * @return array{0:?string, 1:?string} [start, end)
     */
    public function range(): array
    {
        return match ($this) {
            self::ThisMonth => [date('Y-m-01'), date('Y-m-01', strtotime('first day of next month'))],
            self::LastMonth => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-01')],
            self::ThisYear  => [date('Y-01-01'), date('Y-01-01', strtotime('+1 year'))],
            self::AllTime   => [null, null],
        };
    }
}
