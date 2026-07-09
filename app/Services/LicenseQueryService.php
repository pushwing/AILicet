<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;

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
     * 라이센스 상세 — 상품·모듈·고객·이력 포함.
     *
     * productModules 는 상품이 보유한 전체 모듈(code/name), licenseModules 는
     * 이 라이센스 발급 시 실제 선택된 모듈 코드(config.modules 스냅샷)다.
     * 뷰는 두 목록을 대조해 발급/미발급 모듈을 구분 표시한다.
     *
     * @return array{license: array<string, mixed>, product: array<string, mixed>|null, productModules: list<array{id:int, product_id:int, code:string, name:string}>, licenseModules: list<string>, limits: array<string, int>, customers: list<array<string, mixed>>, history: list<array<string, mixed>>, current_key: ?string}|null
     */
    public function detail(int $id): ?array
    {
        /** @var array<string, mixed>|null $license */
        $license = model(LicenseModel::class)->find($id);
        if ($license === null) {
            return null;
        }

        $productId = (int) $license['product_id'];

        /** @var array<string, mixed>|null $product */
        $product = model(ProductModel::class)->find($productId);

        // config(JSON)에서 발급 시 선택된 모듈 코드·사용량 한도 복원.
        $config         = json_decode((string) ($license['config'] ?? '{}'), true);
        $licenseModules = is_array($config) && isset($config['modules']) && is_array($config['modules'])
            ? array_values(array_map('strval', $config['modules'])) : [];
        $limits = is_array($config) && isset($config['limits']) && is_array($config['limits'])
            ? array_map('intval', $config['limits']) : [];

        $productModules = model(ProductModuleModel::class)->byProduct($productId);

        /** @var list<array<string, mixed>> $customers */
        $customers = model(CustomerModel::class)
            ->select('customers.*')
            ->join('customer_license', 'customer_license.customer_id = customers.id')
            ->where('customer_license.license_id', $id)
            ->findAll();

        $history = model(LicenseHistoryModel::class)->byLicense($id);

        return [
            'license'        => $license,
            'product'        => $product,
            'productModules' => $productModules,
            'licenseModules' => $licenseModules,
            'limits'         => $limits,
            'customers'      => $customers,
            'history'        => $history,
            'current_key'    => service('licenseLifecycleService')->currentKey($id),
        ];
    }
}
