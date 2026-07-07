<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;

/**
 * 라이센스 조회(읽기) 유스케이스 — 관리 화면 목록·상세.
 */
final class LicenseQueryService
{
    private const int MAX_PER_PAGE = 100;

    /**
     * 검색·페이징 목록(상품명 조인). meta 표준 반환.
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function paginate(string $search = '', string $type = '', string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $model = model(LicenseModel::class);
        $model->select('licenses.*, products.name AS product_name, products.product_code')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->orderBy('licenses.id', 'DESC');

        if ($type !== '') {
            $model->where('licenses.license_type', $type);
        }
        if ($status !== '') {
            $model->where('licenses.status', $status);
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('licenses.host_id', $search)
                ->orLike('products.name', $search)
                ->orLike('products.product_code', $search)
                ->groupEnd();
        }

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * 라이센스 상세 — 상품·고객·이력 포함.
     *
     * @return array{license: array<string, mixed>, product: array<string, mixed>|null, customers: list<array<string, mixed>>, history: list<array<string, mixed>>, current_key: ?string}|null
     */
    public function detail(int $id): ?array
    {
        /** @var array<string, mixed>|null $license */
        $license = model(LicenseModel::class)->find($id);
        if ($license === null) {
            return null;
        }

        /** @var array<string, mixed>|null $product */
        $product = model(\App\Models\ProductModel::class)->find((int) $license['product_id']);

        /** @var list<array<string, mixed>> $customers */
        $customers = model(CustomerModel::class)
            ->select('customers.*')
            ->join('customer_license', 'customer_license.customer_id = customers.id')
            ->where('customer_license.license_id', $id)
            ->findAll();

        $history = model(LicenseHistoryModel::class)->byLicense($id);

        return [
            'license'     => $license,
            'product'     => $product,
            'customers'   => $customers,
            'history'     => $history,
            'current_key' => service('licenseLifecycleService')->currentKey($id),
        ];
    }
}
