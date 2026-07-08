<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ProductRequest;
use App\Models\LicenseModel;
use App\Models\ModuleModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;
use App\Models\ProductVersionModel;
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
    private ProductVersionModel $versions;

    public function __construct()
    {
        $this->products     = model(ProductModel::class);
        $this->modules      = model(ProductModuleModel::class);
        $this->moduleMaster = model(ModuleModel::class);
        $this->versions     = model(ProductVersionModel::class);
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
     * 상품 단건 + 모듈 + 활성 버전.
     *
     * @return array{
     *     product: array<string, mixed>,
     *     modules: list<array{id:int, product_id:int, code:string, name:string}>,
     *     versions: list<array{id:int, product_id:int, version:string}>
     * }|null
     */
    public function find(int $id): ?array
    {
        /** @var array<string, mixed>|null $product */
        $product = $this->products->find($id);
        if ($product === null) {
            return null;
        }

        return [
            'product'  => $product,
            'modules'  => $this->modules->byProduct($id),
            'versions' => $this->versions->byProduct($id),
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
        $this->syncVersions($productId, $dto->versions);

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
     * 버전은 시간이 지나며 추가되는 성격이라 수정 시에도 관리할 수 있다(추가·비활성).
     *
     * @throws RuntimeException 유효성·저장 실패
     */
    public function update(int $id, ProductRequest $dto): void
    {
        $db = db_connect();
        $db->transStart();

        // is_unique[...,{id}] 플레이스홀더 치환용으로 id 포함(allowedFields 밖이라 실제 SET 에는 미반영)
        $row       = $dto->toProductRow();
        $row['id'] = $id;
        if ($this->products->update($id, $row) === false) {
            throw new RuntimeException($this->firstError($this->products->errors()));
        }
        $this->syncVersions($id, $dto->versions);

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('상품 저장에 실패했습니다.');
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

    /**
     * 상품 버전 목록을 원하는 활성 집합에 맞춰 동기화한다.
     *
     * - 목록에 있으나 없는 버전 → 신규 삽입(활성)
     * - 목록에 있고 비활성 상태였던 버전 → 재활성
     * - 목록에서 빠진 기존 활성 버전 → 비활성(하드 삭제 대신 소프트 숨김, 발급 이력 보존)
     *
     * @param list<string> $versions
     */
    private function syncVersions(int $productId, array $versions): void
    {
        $existing = $this->versions->allByProduct($productId);

        /** @var array<string, array{id:int, product_id:int, version:string, is_active:int}> $existingByVersion */
        $existingByVersion = [];
        foreach ($existing as $row) {
            $existingByVersion[$row['version']] = $row;
        }

        foreach ($versions as $version) {
            if (isset($existingByVersion[$version])) {
                if ((int) $existingByVersion[$version]['is_active'] !== 1) {
                    $this->versions->update((int) $existingByVersion[$version]['id'], ['is_active' => 1]);
                }
            } else {
                $this->versions->insert([
                    'product_id' => $productId,
                    'version'    => $version,
                    'is_active'  => 1,
                ]);
            }
        }

        $desired = array_flip($versions);
        foreach ($existing as $row) {
            if (! isset($desired[$row['version']]) && (int) $row['is_active'] === 1) {
                $this->versions->update((int) $row['id'], ['is_active' => 0]);
            }
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
