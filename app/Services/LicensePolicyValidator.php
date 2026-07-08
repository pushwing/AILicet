<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PeriodCode;
use RuntimeException;

/**
 * 기간정책(period_code)별 발급 입력 검증·정규화.
 *
 * 이슈 #48: 정책에 따라 만료일/기술지원 종료일/사용횟수/크레딧의 필수 여부가 달라진다.
 * 정책상 필요한 필드가 비어 있으면 발급을 거부하고, 정책과 무관한(잠금) 필드 값은
 * 강제로 제거(null)해 잘못된 값이 저장되지 않도록 한다.
 *
 * 노드락·플로팅 발급 서비스가 공통으로 호출하는 단일 검증 지점이다.
 */
final class LicensePolicyValidator
{
    /**
     * 정책 검증 후 정규화된 발급 값을 반환한다.
     *
     * @param array<string, int> $limits {count?, credit?}
     *
     * @return array{expire_date: ?string, support_end_date: ?string, limits: array<string, int>}
     *
     * @throws RuntimeException 유효하지 않은 정책 코드 또는 필수 필드 누락
     */
    public function normalize(
        string $periodCode,
        ?string $expireDate,
        ?string $supportEndDate,
        array $limits,
    ): array {
        $policy = PeriodCode::tryFrom($periodCode);
        if ($policy === null) {
            throw new RuntimeException('유효하지 않은 기간정책입니다.');
        }

        $expireDate     = $this->blankToNull($expireDate);
        $supportEndDate = $this->blankToNull($supportEndDate);
        $count          = $limits['count']  ?? null;
        $credit         = $limits['credit'] ?? null;

        if ($policy->requiresExpireDate()) {
            if ($expireDate === null) {
                throw new RuntimeException('해당 기간정책은 만료일이 필수입니다.');
            }
            if ($supportEndDate === null) {
                throw new RuntimeException('해당 기간정책은 기술지원 종료일이 필수입니다.');
            }
        } else {
            // 잠금 필드: 값 무시
            $expireDate     = null;
            $supportEndDate = null;
        }

        if ($policy->requiresCount()) {
            if ($count === null) {
                throw new RuntimeException('해당 기간정책은 사용횟수 제한이 필수입니다.');
            }
        } else {
            $count = null;
        }

        if ($policy->requiresCredit()) {
            if ($credit === null) {
                throw new RuntimeException('해당 기간정책은 크레딧 제한이 필수입니다.');
            }
        } else {
            $credit = null;
        }

        $normalizedLimits = [];
        if ($count !== null) {
            $normalizedLimits['count'] = $count;
        }
        if ($credit !== null) {
            $normalizedLimits['credit'] = $credit;
        }

        return [
            'expire_date'      => $expireDate,
            'support_end_date' => $supportEndDate,
            'limits'           => $normalizedLimits,
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
