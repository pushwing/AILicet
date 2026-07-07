<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 라이센스 종류.
 *
 * - NodeLock: 오프라인 설치형(머신 고유 host_id 바인딩, 서명 파일 배포)
 * - Floating: 온라인 체크형(관리키만 배포, 서버 활성화/유효성 검증)
 */
enum LicenseType: string
{
    case NodeLock = 'nodelock';
    case Floating = 'floating';

    public function label(): string
    {
        return match ($this) {
            self::NodeLock => '노드락',
            self::Floating => '플로팅',
        };
    }
}
