<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 고객센터 문의 상태.
 */
enum InquiryStatus: string
{
    case Open     = 'open';
    case Answered = 'answered';
    case Closed   = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open     => '접수',
            self::Answered => '답변완료',
            self::Closed   => '종료',
        };
    }
}
