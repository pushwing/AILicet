<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 대시보드 자연어 질의로 조회 가능한 집계 지표(화이트리스트).
 *
 * AI 는 자유 SQL 을 생성하지 않는다 — 자연어 질문을 이 enum 값 중 하나로만 매핑하고,
 * DashboardService 가 그 값을 안전한 Query Builder 집계로 변환한다(인젝션 차단의 핵심).
 * 목록에 없는 값이 오면 질의는 거부된다.
 */
enum DashboardMetric: string
{
    case ActiveLicenses     = 'active_licenses';
    case IssuedLicenses     = 'issued_licenses';
    case ExpiringSoon       = 'expiring_soon';
    case AbuseDetected      = 'abuse_detected';
    case SuspendedLicenses  = 'suspended_licenses';
    case TerminatedLicenses = 'terminated_licenses';

    public function label(): string
    {
        return match ($this) {
            self::ActiveLicenses     => '활성 라이선스 수',
            self::IssuedLicenses     => '발급 라이선스 수',
            self::ExpiringSoon       => '만료 임박 라이선스 수',
            self::AbuseDetected      => '부정사용 감지 수',
            self::SuspendedLicenses  => '중지 라이선스 수',
            self::TerminatedLicenses => '종료 라이선스 수',
        };
    }

    /**
     * 기간 필터가 의미 있는 지표인가.
     * 발급·부정사용은 기간별 집계, 나머지는 현재 시점 스냅샷(기간 무시).
     */
    public function isPeriodic(): bool
    {
        return match ($this) {
            self::IssuedLicenses, self::AbuseDetected => true,
            default                                   => false,
        };
    }
}
