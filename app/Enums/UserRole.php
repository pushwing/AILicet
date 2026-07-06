<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 회원구분 — 접근 권한 등급.
 *
 * AITessera 의 `role` 클레임(정수)과 값을 일치시킨다.
 * - Operator(1): 운영자 → AILicet Admin 페이지
 * - Agency(2)  : 대행사 → 하위 고객·라이센스 관리
 * - Member(3)  : 일반회원(고객) → 내 라이센스 조회 (자가가입 기본값)
 */
enum UserRole: int
{
    case Operator = 1;
    case Agency   = 2;
    case Member   = 3;

    public function label(): string
    {
        return match ($this) {
            self::Operator => '운영자',
            self::Agency   => '대행사',
            self::Member   => '고객',
        };
    }

    /** 라우트 필터 인자(문자열 슬러그) → 회원구분 매핑. */
    public static function fromSlug(string $slug): ?self
    {
        return match (strtolower(trim($slug))) {
            'operator', 'admin' => self::Operator,
            'agency'            => self::Agency,
            'member', 'client'  => self::Member,
            default             => null,
        };
    }
}
