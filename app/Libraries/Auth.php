<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Enums\UserRole;

/**
 * 요청 컨텍스트 인증 정보 홀더.
 *
 * JwtAuthFilter / AdminAuthFilter 가 검증 후 사용자 정보를 여기에 담고,
 * 컨트롤러·서비스는 정적 접근으로 꺼내 쓴다. 별도 DI 없이 요청 사이클 안에서만 유효하다.
 */
final class Auth
{
    private static ?int $userId = null;

    private static ?UserRole $role = null;

    private static ?string $affiliation = null;

    public static function setUser(int $userId, UserRole $role, ?string $affiliation = null): void
    {
        self::$userId      = $userId;
        self::$role        = $role;
        self::$affiliation = $affiliation;
    }

    /** CLAUDE.md 호환 — 사용자 ID만 설정. */
    public static function setUserId(int $userId): void
    {
        self::$userId = $userId;
    }

    public static function userId(): ?int
    {
        return self::$userId;
    }

    public static function role(): ?UserRole
    {
        return self::$role;
    }

    public static function affiliation(): ?string
    {
        return self::$affiliation;
    }

    public static function check(): bool
    {
        return self::$userId !== null;
    }

    public static function isOperator(): bool
    {
        return self::$role === UserRole::Operator;
    }

    public static function isAgency(): bool
    {
        return self::$role === UserRole::Agency;
    }

    public static function isMember(): bool
    {
        return self::$role === UserRole::Member;
    }

    /** 요청 종료 시 상태 초기화(테스트·롱러닝 워커 안전). */
    public static function clear(): void
    {
        self::$userId      = null;
        self::$role        = null;
        self::$affiliation = null;
    }
}
