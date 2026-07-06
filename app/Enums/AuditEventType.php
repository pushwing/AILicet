<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 감사 로그(audit_logs) 이벤트 유형.
 *
 * 레거시 invalidLicense(부정사용)를 통합한 감사 로그의 이벤트 분류.
 */
enum AuditEventType: string
{
    case IllegalHost         = 'illegal_host';          // 등록 host_id 와 실제 사용 host_id 불일치
    case ExpiredUse          = 'expired_use';           // 만료된 라이센스 사용
    case RevokedKeyUse       = 'revoked_key_use';       // 재발급 후 이전(폐기) 키 사용
    case DuplicateActivation = 'duplicate_activation';  // 플로팅 이중 활성화 시도
    case UsageOverLimit      = 'usage_over_limit';      // 카운트/크레딧 초과 사용

    public function label(): string
    {
        return match ($this) {
            self::IllegalHost         => '호스트 불일치',
            self::ExpiredUse          => '만료 사용',
            self::RevokedKeyUse       => '폐기 키 사용',
            self::DuplicateActivation => '이중 활성화',
            self::UsageOverLimit      => '사용량 초과',
        };
    }
}
