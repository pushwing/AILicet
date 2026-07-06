<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 라이센스 이력(license_history) 유형.
 *
 * 레거시 history.type 매핑: 1=발급, 4=재발급, 5=상태변경
 */
enum HistoryType: string
{
    case Issue        = 'issue';
    case Reissue      = 'reissue';
    case StatusChange = 'status_change';

    public function label(): string
    {
        return match ($this) {
            self::Issue        => '발급',
            self::Reissue      => '재발급',
            self::StatusChange => '상태 변경',
        };
    }
}
