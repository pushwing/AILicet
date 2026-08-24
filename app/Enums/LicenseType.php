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

    /**
     * 해당 라이선스 유형에 적용되는 클라이언트 인증 방식을 설명한다.
     *
     * 인증 경로는 발급·검증 서비스가 license_type 으로 분기하므로 별도 값으로
     * 저장하지 않는다. 이렇게 하면 관리 화면의 안내와 실제 검증 방식이 항상 일치한다.
     */
    public function authenticationMethodLabel(): string
    {
        return match ($this) {
            self::NodeLock => '서명 파일·온라인 검증',
            self::Floating => '온라인 활성화·잔여 검증',
        };
    }
}
