<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * 노드락 라이센스 발급 요청.
 *
 * 발급에 필요한 최소 입력. 상품 정보는 productId 로 조회해 채운다.
 */
final readonly class NodeLockIssueRequest
{
    /**
     * @param list<string>      $modules 모듈 코드 목록
     * @param array<string,int> $limits  사용량 제한 {count?, credit?}
     */
    public function __construct(
        public int $productId,
        public string $hostId,
        public string $periodCode,
        public int $issuedBy,
        public ?string $version = null,
        public ?string $expireDate = null,
        public ?string $supportEndDate = null,
        public array $modules = [],
        public ?int $customerId = null,
        public ?string $companyName = null,
        public ?string $chargeName = null,
        public ?string $chargePhone = null,
        public ?string $chargeEmail = null,
        public bool $isTrial = false,
        public array $limits = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) ($data['product_id'] ?? 0),
            hostId: trim((string) ($data['host_id'] ?? '')),
            periodCode: (string) ($data['period_code'] ?? ''),
            issuedBy: (int) ($data['issued_by'] ?? 0),
            version: isset($data['version']) ? (string) $data['version'] : null,
            expireDate: isset($data['expire_date']) ? (string) $data['expire_date'] : null,
            supportEndDate: isset($data['support_end_date']) ? (string) $data['support_end_date'] : null,
            modules: array_values(array_map('strval', (array) ($data['modules'] ?? []))),
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            companyName: isset($data['company_name']) ? (string) $data['company_name'] : null,
            chargeName: isset($data['charge_name']) ? (string) $data['charge_name'] : null,
            chargePhone: isset($data['charge_phone']) ? (string) $data['charge_phone'] : null,
            chargeEmail: isset($data['charge_email']) ? (string) $data['charge_email'] : null,
            isTrial: (bool) ($data['is_trial'] ?? false),
            limits: array_map('intval', (array) ($data['limits'] ?? [])),
        );
    }
}
