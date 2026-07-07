<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 라이센스 상태.
 *
 * 레거시 status 코드 매핑: 1=정상, 2=중지, 3=종료, 4=보관
 */
enum LicenseStatus: string
{
    case Active     = 'active';
    case Suspended  = 'suspended';
    case Terminated = 'terminated';
    case Archived   = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active     => '정상',
            self::Suspended  => '중지',
            self::Terminated => '종료',
            self::Archived   => '보관',
        };
    }

    /** 유효한(사용 가능) 상태인가. */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * 현재 상태에서 대상 상태로 전이 가능한가.
     *
     * Active     → Suspended / Terminated / Archived
     * Suspended  → Active / Terminated / Archived
     * Terminated → Archived
     * Archived   → (없음)
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active     => in_array($target, [self::Suspended, self::Terminated, self::Archived], true),
            self::Suspended  => in_array($target, [self::Active, self::Terminated, self::Archived], true),
            self::Terminated => $target === self::Archived,
            self::Archived   => false,
        };
    }
}
