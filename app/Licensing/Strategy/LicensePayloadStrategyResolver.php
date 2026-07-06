<?php

declare(strict_types=1);

namespace App\Licensing\Strategy;

use RuntimeException;

/**
 * 상품에 맞는 페이로드 전략을 선택한다.
 *
 * 등록 순서대로 supports() 를 검사해 첫 매칭 전략을 반환한다.
 * 특수 전략을 앞에, 표준(폴백) 전략을 뒤에 등록한다.
 */
final class LicensePayloadStrategyResolver
{
    /** @var list<LicensePayloadStrategy> */
    private array $strategies;

    /**
     * @param list<LicensePayloadStrategy>|null $strategies
     */
    public function __construct(?array $strategies = null)
    {
        $this->strategies = $strategies ?? [
            // 특수 전략(제품군/버전별)을 여기 앞쪽에 추가
            new StandardNodeLockStrategy(),
        ];
    }

    /**
     * @param array<string, mixed> $product
     */
    public function resolve(array $product): LicensePayloadStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($product)) {
                return $strategy;
            }
        }

        throw new RuntimeException('상품에 맞는 라이센스 전략이 없습니다.');
    }
}
