<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CustomerRequest;
use App\Enums\CustomerType;
use App\Models\CustomerModel;
use App\Models\LicenseModel;
use RuntimeException;

/**
 * 대행사 소유권 스코프 유스케이스.
 *
 * 로그인한 대행사(AITessera user_id)에 매핑된 회원 레코드를 찾고, 그 하위 고객·라이센스만
 * 조회·발급하도록 스코프를 강제한다. 타 대행사 데이터 접근은 차단된다.
 */
final class AgencyService
{
    private const int MAX_PER_PAGE = 100;

    /** 로그인 사용자(user_id)에 매핑된 대행사 회원 id. 없으면 null. */
    public function resolveAgencyId(int $userId): ?int
    {
        if ($userId === 0) {
            return null;
        }

        /** @var array<string, mixed>|null $row */
        $row = model(CustomerModel::class)
            ->where('user_id', $userId)
            ->where('customer_type', CustomerType::Agency->value)
            ->first();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * 대행사 하위 고객 id 목록.
     *
     * @return list<int>
     */
    public function customerIds(int $agencyId): array
    {
        /** @var list<array{id:int}> $rows */
        $rows = model(CustomerModel::class)->select('id')->where('parent_id', $agencyId)->findAll();

        return array_map(static fn ($r) => (int) $r['id'], $rows);
    }

    /**
     * 하위 고객 검색·페이징(meta 표준).
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function customersPaginate(int $agencyId, string $search = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $model = model(CustomerModel::class);
        $model->where('parent_id', $agencyId)->orderBy('created_at', 'DESC');
        if ($search !== '') {
            $model->groupStart()->like('company_name', $search)->orLike('name', $search)->orLike('email', $search)->groupEnd();
        }

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return ['items' => $items, 'meta' => $this->meta($page, $perPage, $total)];
    }

    /**
     * 대행사 소유 라이센스 검색·페이징(meta 표준).
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function licensesPaginate(int $agencyId, string $search = '', string $type = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $ids = $this->licenseIds($agencyId);
        if ($ids === []) {
            return ['items' => [], 'meta' => $this->meta($page, $perPage, 0)];
        }

        $model = model(LicenseModel::class);
        $model->select('licenses.*, products.name AS product_name, products.product_code')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->whereIn('licenses.id', $ids)
            ->orderBy('licenses.id', 'DESC');

        if ($type !== '') {
            $model->where('licenses.license_type', $type);
        }
        if ($status !== '') {
            $model->where('licenses.status', $status);
        }
        if ($search !== '') {
            $model->groupStart()->like('licenses.host_id', $search)->orLike('products.name', $search)->groupEnd();
        }

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return ['items' => $items, 'meta' => $this->meta($page, $perPage, $total)];
    }

    /** 대행사 소유 라이센스 id 목록(하위 고객에 매핑된). */
    /**
     * @return list<int>
     */
    public function licenseIds(int $agencyId): array
    {
        $customerIds = $this->customerIds($agencyId);
        if ($customerIds === []) {
            return [];
        }

        /** @var list<array{license_id:int}> $rows */
        $rows = db_connect()->table('customer_license')
            ->distinct()
            ->select('license_id')
            ->whereIn('customer_id', $customerIds)
            ->get()->getResultArray();

        return array_map(static fn ($r) => (int) $r['license_id'], $rows);
    }

    public function ownsCustomer(int $agencyId, int $customerId): bool
    {
        return model(CustomerModel::class)
            ->where('id', $customerId)->where('parent_id', $agencyId)->countAllResults() > 0;
    }

    public function ownsLicense(int $agencyId, int $licenseId): bool
    {
        return in_array($licenseId, $this->licenseIds($agencyId), true);
    }

    /**
     * 하위 고객 생성(유형·소속 강제).
     *
     * @throws RuntimeException 유효성 실패
     */
    public function createClient(int $agencyId, CustomerRequest $dto): int
    {
        $model = model(CustomerModel::class);
        $row   = $dto->toRow();
        $row['customer_type'] = CustomerType::Client->value; // 강제
        $row['parent_id']     = $agencyId;                    // 강제

        $id = (int) ($model->insert($row, true) ?: 0);
        if ($id === 0) {
            throw new RuntimeException($model->errors() === [] ? '고객 저장 실패' : (string) array_values($model->errors())[0]);
        }

        return $id;
    }

    /**
     * 하위 고객 수정(소유권 확인 후).
     *
     * @throws RuntimeException 소유권 없음·유효성 실패
     */
    public function updateClient(int $agencyId, int $customerId, CustomerRequest $dto): void
    {
        if (! $this->ownsCustomer($agencyId, $customerId)) {
            throw new RuntimeException('권한이 없는 고객입니다.');
        }

        $model = model(CustomerModel::class);
        $row   = $dto->toRow();
        $row['customer_type'] = CustomerType::Client->value;
        $row['parent_id']     = $agencyId;
        $row['id']            = $customerId;

        if ($model->update($customerId, $row) === false) {
            throw new RuntimeException($model->errors() === [] ? '고객 수정 실패' : (string) array_values($model->errors())[0]);
        }
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
