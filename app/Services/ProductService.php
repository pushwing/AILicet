<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ProductRequest;
use App\Models\LicenseModel;
use App\Models\ModuleModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;
use RuntimeException;

/**
 * 상품·모듈 유스케이스.
 *
 * 상품+모듈 저장은 트랜잭션으로 원자성을 보장하고, 코드성 데이터(활성 상품 목록)는
 * 캐시(Config\Cache 핸들러 — 운영 Redis)로 서빙한다. 쓰기 시 캐시를 무효화한다.
 */
final class ProductService
{
    /** 활성 상품 목록 캐시 키. (CI4 캐시 키는 `:` 등 예약문자 불가 → `.` 구분) */
    private const string CACHE_ACTIVE = 'products.active.list';

    /** 코드성 데이터 캐시 TTL(초) — 1시간. */
    private const int CACHE_TTL = 3600;

    private ProductModel $products;
    private ProductModuleModel $modules;
    private ModuleModel $moduleMaster;

    public function __construct()
    {
        $this->products     = model(ProductModel::class);
        $this->modules      = model(ProductModuleModel::class);
        $this->moduleMaster = model(ModuleModel::class);
    }

    /**
     * 상품 목록(관리 화면용, 모듈 수 포함).
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->products->orderBy('created_at', 'DESC')->findAll();

        return $rows;
    }

    /**
     * 상품 단건 + 모듈.
     *
     * @return array{product: array<string, mixed>, modules: list<array{id:int, product_id:int, code:string, name:string}>}|null
     */
    public function find(int $id): ?array
    {
        /** @var array<string, mixed>|null $product */
        $product = $this->products->find($id);
        if ($product === null) {
            return null;
        }

        return [
            'product' => $product,
            'modules' => $this->modules->byProduct($id),
        ];
    }

    /**
     * 발급 폼용 활성 상품 목록(캐시).
     *
     * @return list<array{id:int, product_code:string, name:string, license_type:string, version:?string}>
     */
    public function activeForSelect(): array
    {
        $cache  = cache();
        $cached = $cache->get(self::CACHE_ACTIVE);
        if (is_array($cached)) {
            /** @var list<array{id:int, product_code:string, name:string, license_type:string, version:?string}> $cached */
            return $cached;
        }

        $rows = $this->products->activeForSelect();
        $cache->save(self::CACHE_ACTIVE, $rows, self::CACHE_TTL);

        return $rows;
    }

    /**
     * 상품 생성(+모듈). 성공 시 상품 ID 반환.
     *
     * @throws RuntimeException 유효성·저장 실패
     */
    public function create(ProductRequest $dto): int
    {
        $db = db_connect();
        $db->transStart();

        $productId = (int) ($this->products->insert($dto->toProductRow(), true) ?: 0);
        if ($productId === 0) {
            throw new RuntimeException($this->firstError($this->products->errors()));
        }
        $this->syncModules($productId, $dto->moduleIds);

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('상품 저장에 실패했습니다.');
        }

        $this->invalidateCache();

        return $productId;
    }

    /**
     * 상품 기본정보 수정.
     *
     * 모듈 구성은 생성 시 확정되며 이후 변경할 수 없다(이미 판매된 상품 보호). 여기서는 건드리지 않는다.
     *
     * @throws RuntimeException 유효성·저장 실패
     */
    public function update(int $id, ProductRequest $dto): void
    {
        // is_unique[...,{id}] 플레이스홀더 치환용으로 id 포함(allowedFields 밖이라 실제 SET 에는 미반영)
        $row       = $dto->toProductRow();
        $row['id'] = $id;
        if ($this->products->update($id, $row) === false) {
            throw new RuntimeException($this->firstError($this->products->errors()));
        }

        $this->invalidateCache();
    }

    /**
     * 상품 소프트 삭제. 발급 이력이 있으면 거부한다.
     *
     * @throws RuntimeException 발급 이력이 있을 때
     */
    public function delete(int $id): void
    {
        $issued = model(LicenseModel::class)->withDeleted()->where('product_id', $id)->countAllResults() > 0;
        if ($issued) {
            throw new RuntimeException('이미 발급 이력이 있는 상품은 삭제할 수 없습니다.');
        }

        $this->products->delete($id);
        $this->invalidateCache();
    }

    /**
     * 선택한 모듈 마스터를 상품에 연결(code/name 스냅샷).
     *
     * @param list<int> $moduleIds
     */
    private function syncModules(int $productId, array $moduleIds): void
    {
        if ($moduleIds === []) {
            return;
        }

        /** @var list<array{id:int, code:string, name:string}> $masters */
        $masters = $this->moduleMaster
            ->select('id, code, name')
            ->whereIn('id', $moduleIds)
            ->where('is_active', 1)
            ->findAll();

        foreach ($masters as $master) {
            $this->modules->insert([
                'product_id' => $productId,
                'module_id'  => (int) $master['id'],
                'code'       => $master['code'],
                'name'       => $master['name'],
            ]);
        }
    }

    private function invalidateCache(): void
    {
        cache()->delete(self::CACHE_ACTIVE);
    }

    /**
     * @param array<string, string> $errors
     */
    private function firstError(array $errors): string
    {
        return $errors === [] ? '상품 저장에 실패했습니다.' : (string) array_values($errors)[0];
    }
}
