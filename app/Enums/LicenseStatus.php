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
}
