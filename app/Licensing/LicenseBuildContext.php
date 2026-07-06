<?php

declare(strict_types=1);

namespace App\Licensing;

use App\DTO\NodeLockIssueRequest;

/**
 * 페이로드 빌드 컨텍스트 — 전략(Strategy)에 전달되는 확정 값 묶음.
 *
 * 요청(DTO) + 조회한 상품 + 서비스가 생성한 식별자(license_sn/key/issue_date)를 합친다.
 */
final readonly class LicenseBuildContext
{
    /**
     * @param array<string, mixed> $product  상품 레코드
     */
    public function __construct(
        public NodeLockIssueRequest $request,
        public array $product,
        public string $licenseSn,
        public string $licenseKey,
        public string $issueDate,
    ) {
    }
}
