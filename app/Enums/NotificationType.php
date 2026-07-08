<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 인앱 메시지 종류.
 *
 * - LicenseExpiring: 라이센스 만료 임박(30/7/1일 전) 안내
 * - LicenseExpired : 라이센스 만료 종료 처리 안내
 */
enum NotificationType: string
{
    case LicenseExpiring = 'license_expiring';
    case LicenseExpired  = 'license_expired';

    public function label(): string
    {
        return match ($this) {
            self::LicenseExpiring => '만료 임박',
            self::LicenseExpired  => '만료 종료',
        };
    }
}
