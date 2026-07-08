<?php

declare(strict_types=1);

namespace App\DTO;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * 상품 생성·수정 요청 DTO.
 *
 * moduleIds 는 모듈 마스터에서 선택한 모듈 ID 목록. (수동 입력이 아니라 선택)
 */
final readonly class ProductRequest
{
    /**
     * @param list<int> $moduleIds
     */
    public function __construct(
        public string $productCode,
        public string $name,
        public string $licenseType,
        public ?string $productFamily,
        public ?string $version,
        public ?string $periodCode,
        public bool $isActive,
        public array $moduleIds,
    ) {
    }

    public static function fromRequest(IncomingRequest $request): self
    {
        /** @var array<int|string, mixed> $rawIds */
        $rawIds = (array) $request->getPost('module_ids');

        $moduleIds = [];
        foreach ($rawIds as $rawId) {
            $moduleId = (int) $rawId;
            if ($moduleId > 0) {
                $moduleIds[] = $moduleId;
            }
        }
        $moduleIds = array_values(array_unique($moduleIds));

        return new self(
            productCode: trim((string) $request->getPost('product_code')),
            name: trim((string) $request->getPost('name')),
            licenseType: (string) $request->getPost('license_type'),
            productFamily: self::nullable($request->getPost('product_family')),
            version: self::nullable($request->getPost('version')),
            periodCode: self::nullable($request->getPost('period_code')),
            isActive: (string) $request->getPost('is_active') === '1',
            moduleIds: $moduleIds,
        );
    }

    /**
     * 상품 테이블 저장용 배열(모듈 제외).
     *
     * @return array{product_code:string, name:string, product_family:?string, license_type:string, version:?string, period_code:?string, is_active:int}
     */
    public function toProductRow(): array
    {
        return [
            'product_code'   => $this->productCode,
            'name'           => $this->name,
            'product_family' => $this->productFamily,
            'license_type'   => $this->licenseType,
            'version'        => $this->version,
            'period_code'    => $this->periodCode,
            'is_active'      => $this->isActive ? 1 : 0,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
