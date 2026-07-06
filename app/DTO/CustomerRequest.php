<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\CustomerType;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * 회원 생성·수정 요청 DTO.
 */
final readonly class CustomerRequest
{
    public function __construct(
        public string $customerType,
        public string $companyName,
        public string $name,
        public string $email,
        public ?int $parentId,
        public ?string $phone,
        public bool $isActive,
    ) {
    }

    public static function fromRequest(IncomingRequest $request): self
    {
        $type   = (string) $request->getPost('customer_type');
        $parent = $request->getPost('parent_id');

        return new self(
            customerType: $type,
            companyName: trim((string) $request->getPost('company_name')),
            name: trim((string) $request->getPost('name')),
            email: trim((string) $request->getPost('email')),
            // 대행사는 소속(parent) 없음
            parentId: ($type === CustomerType::Client->value && $parent !== null && $parent !== '')
                ? (int) $parent : null,
            phone: self::nullable($request->getPost('phone')),
            isActive: (string) $request->getPost('is_active') !== '0',
        );
    }

    /**
     * @return array{customer_type:string, company_name:string, name:string, email:string, parent_id:?int, phone:?string, is_active:int}
     */
    public function toRow(): array
    {
        return [
            'customer_type' => $this->customerType,
            'company_name'  => $this->companyName,
            'name'          => $this->name,
            'email'         => $this->email,
            'parent_id'     => $this->parentId,
            'phone'         => $this->phone,
            'is_active'     => $this->isActive ? 1 : 0,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
