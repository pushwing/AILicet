<?php

declare(strict_types=1);

namespace App\Licensing\Strategy;

use App\Licensing\LicenseBuildContext;

/**
 * 라이센스 페이로드 생성 전략.
 *
 * 레거시의 상품/버전별 7갈래 if 분기를 다형성으로 대체한다.
 * 상품 특성(제품군·버전)에 따라 페이로드 형태가 달라질 경우 전략을 추가하고
 * resolver 등록만 하면 된다.
 */
interface LicensePayloadStrategy
{
    /**
     * @param array<string, mixed> $product 상품 레코드
     */
    public function supports(array $product): bool;

    /**
     * 서명 대상 페이로드를 만든다.
     *
     * @return array<string, mixed>
     */
    public function build(LicenseBuildContext $ctx): array;
}
