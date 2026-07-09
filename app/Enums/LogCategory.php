<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 가 부여하는 수집 로그 분류 카테고리.
 *
 * AI 응답을 이 화이트리스트로 제약해 자유 텍스트 오염을 막는다.
 * 매칭 실패·미설정 값은 fromString() 에서 Other 로 폴백한다.
 */
enum LogCategory: string
{
    case Error       = 'error';        // 오류·예외·실패
    case Security    = 'security';     // 인증 실패·권한·부정사용 의심
    case Performance = 'performance';  // 지연·타임아웃·리소스 부족
    case UserAction  = 'user_action';  // 사용자 행위(로그인·설정 변경 등)
    case System      = 'system';       // 배치·스케줄·시스템 이벤트
    case Other       = 'other';        // 분류 불가

    public function label(): string
    {
        return match ($this) {
            self::Error       => '오류',
            self::Security    => '보안',
            self::Performance => '성능',
            self::UserAction  => '사용자 행위',
            self::System      => '시스템',
            self::Other       => '기타',
        };
    }

    /**
     * 임의 문자열을 카테고리로 변환한다. 매칭 실패 시 Other 로 폴백한다.
     */
    public static function fromString(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Other;
    }

    /**
     * AI 프롬프트에 넣을 허용 카테고리 값 목록(콤마 구분).
     */
    public static function allowedValuesCsv(): string
    {
        return implode(', ', array_map(static fn (self $c): string => $c->value, self::cases()));
    }
}
