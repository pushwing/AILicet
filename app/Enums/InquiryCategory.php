<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 가 부여하는 고객 문의 분류 카테고리.
 *
 * AI 응답을 이 화이트리스트로 제약해 자유 텍스트 오염을 막는다.
 * 매칭 실패·미설정 값은 fromString() 에서 Other 로 폴백한다(App\Enums\LogCategory 패턴).
 */
enum InquiryCategory: string
{
    case Billing   = 'billing';    // 결제·요금·청구
    case Technical = 'technical';  // 기술지원·오류·사용법
    case License   = 'license';    // 라이선스 발급·연장·정지·키
    case Account   = 'account';    // 계정·로그인·정보변경
    case Refund    = 'refund';     // 환불·취소
    case Other     = 'other';      // 분류 불가

    public function label(): string
    {
        return match ($this) {
            self::Billing   => '결제',
            self::Technical => '기술지원',
            self::License   => '라이선스',
            self::Account   => '계정',
            self::Refund    => '환불',
            self::Other     => '기타',
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
