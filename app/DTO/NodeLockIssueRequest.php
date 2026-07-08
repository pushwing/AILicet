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
     * 호스트ID 표준 형식(XXXX-XXXX-XXXX-XXXX, 대문자 hex).
     *
     * tools/hostid 유틸리티가 산출하는 형식과 일치한다. 이 형식·규칙은 라이센스 런타임
     * 검증과 맞물려 있으므로 변경 시 tools/hostid 와 함께 조정해야 한다.
     */
    public const string HOST_ID_PATTERN = '/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/';

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
     * 사용자 입력 호스트ID 를 정규화·검증한다.
     *
     * 대문자·trim 정규화 후 표준 형식과 일치하면 정규화값을, 아니면 null 을 반환한다.
     * 발급·재발급 폼 등 **입력 경계에서만** 사용한다. DB 에 저장된 레거시 host_id 를
     * 재구성(재발급 파일 재생성 등)할 때는 호출하지 않는다 — 구형 값은 형식이 다를 수 있다.
     */
    public static function normalizeHostId(string $raw): ?string
    {
        $host = strtoupper(trim($raw));

        return preg_match(self::HOST_ID_PATTERN, $host) === 1 ? $host : null;
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
