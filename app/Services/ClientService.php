<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerType;
use App\Enums\InquiryStatus;
use App\Models\CustomerModel;
use App\Models\InquiryModel;
use App\Models\LicenseModel;
use RuntimeException;

/**
 * 고객 셀프서비스 유스케이스(소유권 스코프).
 *
 * 로그인 고객(user_id)에 매핑된 회원 레코드 기준으로 본인 라이센스·문의만 다룬다.
 */
final class ClientService
{
    private const int MAX_PER_PAGE = 100;

    /** 로그인 사용자(user_id)에 매핑된 고객 회원 id. 없으면 null. */
    public function resolveCustomerId(int $userId): ?int
    {
        if ($userId === 0) {
            return null;
        }

        /** @var array<string, mixed>|null $row */
        $row = model(CustomerModel::class)
            ->where('user_id', $userId)
            ->where('customer_type', CustomerType::Client->value)
            ->first();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(int $customerId): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(CustomerModel::class)->find($customerId);

        return $row;
    }

    /**
     * 프로필 수정(회사명·담당자·연락처만). 이메일·유형은 변경 불가.
     *
     * @param array<string, mixed> $data
     *
     * @throws RuntimeException 유효성 실패
     */
    public function updateProfile(int $customerId, array $data): void
    {
        $model = model(CustomerModel::class);
        $ok    = $model->update($customerId, [
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'name'         => trim((string) ($data['name'] ?? '')),
            'phone'        => trim((string) ($data['phone'] ?? '')),
        ]);
        if ($ok === false) {
            throw new RuntimeException($model->errors() === [] ? '수정 실패' : (string) array_values($model->errors())[0]);
        }
    }

    /**
     * 내 라이센스 검색·페이징(meta 표준).
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function licensesPaginate(int $customerId, string $search = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $ids = $this->licenseIds($customerId);
        if ($ids === []) {
            return ['items' => [], 'meta' => $this->meta($page, $perPage, 0)];
        }

        $model = model(LicenseModel::class);
        $model->select('licenses.*, products.name AS product_name, products.product_code')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->whereIn('licenses.id', $ids)
            ->orderBy('licenses.id', 'DESC');
        if ($search !== '') {
            $model->groupStart()->like('licenses.host_id', $search)->orLike('products.name', $search)->groupEnd();
        }

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return ['items' => $items, 'meta' => $this->meta($page, $perPage, $total)];
    }

    /**
     * @return list<int>
     */
    public function licenseIds(int $customerId): array
    {
        /** @var list<array{license_id:int}> $rows */
        $rows = db_connect()->table('customer_license')
            ->distinct()->select('license_id')
            ->where('customer_id', $customerId)
            ->get()->getResultArray();

        return array_map(static fn ($r) => (int) $r['license_id'], $rows);
    }

    public function ownsLicense(int $customerId, int $licenseId): bool
    {
        return in_array($licenseId, $this->licenseIds($customerId), true);
    }

    /**
     * 문의 등록.
     *
     * @param array<string, mixed> $data
     *
     * @throws RuntimeException 유효성 실패
     */
    public function createInquiry(int $customerId, string $email, array $data): int
    {
        $model = model(InquiryModel::class);
        $id    = (int) ($model->insert([
            'customer_id' => $customerId,
            'email'       => $email,
            'subject'     => trim((string) ($data['subject'] ?? '')),
            'content'     => trim((string) ($data['content'] ?? '')),
            'status'      => InquiryStatus::Open->value,
        ], true) ?: 0);

        if ($id === 0) {
            throw new RuntimeException($model->errors() === [] ? '문의 등록 실패' : (string) array_values($model->errors())[0]);
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function myInquiries(int $customerId): array
    {
        return model(InquiryModel::class)->byCustomer($customerId);
    }

    /**
     * @return array{page:int, per_page:int, total:int, last_page:int}
     */
    private function meta(int $page, int $perPage, int $total): array
    {
        return [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }
}
