<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 회원(고객) 유형.
 *
 * - Agency: 대행사(대리점) — 하위 고객을 관리한다.
 * - Client: 일반 고객 — 대행사(parent)에 소속될 수 있다.
 */
enum CustomerType: string
{
    case Agency = 'agency';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Agency => '대행사',
            self::Client => '고객',
        };
    }
}
