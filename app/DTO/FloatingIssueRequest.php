<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * 플로팅(온라인) 라이센스 발급 요청.
 *
 * 파일·서명 없이 관리키만 발급한다. activate_term/check_term 은 온라인 검증 주기 정책이고,
 * limits(세그플러스 크레딧/카운트)는 config 에 담긴다.
 */
final readonly class FloatingIssueRequest
{
    /**
     * @param list<string>      $modules 모듈 코드 목록
     * @param array<string,int> $limits  사용량 제한 {count?, credit?}
     */
    public function __construct(
        public int $productId,
        public string $periodCode,
        public int $issuedBy,
        public ?string $version = null,
        public ?string $expireDate = null,
        public ?string $supportEndDate = null,
        public array $modules = [],
        public ?int $customerId = null,
        public int $activateTerm = 24,   // 활성화 간격(시간)
        public int $checkTerm = 30,      // 유효성 체크 간격(분)
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
            periodCode: (string) ($data['period_code'] ?? ''),
            issuedBy: (int) ($data['issued_by'] ?? 0),
            version: isset($data['version']) ? (string) $data['version'] : null,
            expireDate: isset($data['expire_date']) ? (string) $data['expire_date'] : null,
            supportEndDate: isset($data['support_end_date']) ? (string) $data['support_end_date'] : null,
            modules: array_values(array_map('strval', (array) ($data['modules'] ?? []))),
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            activateTerm: (int) ($data['activate_term'] ?? 24),
            checkTerm: (int) ($data['check_term'] ?? 30),
            isTrial: (bool) ($data['is_trial'] ?? false),
            limits: array_map('intval', (array) ($data['limits'] ?? [])),
        );
    }
}
