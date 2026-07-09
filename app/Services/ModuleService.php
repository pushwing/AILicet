<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ModuleRequest;
use App\Models\ModuleModel;
use RuntimeException;

/**
 * 모듈 마스터 유스케이스.
 *
 * 상품과 독립적인 모듈 카탈로그 CRUD. 이미 상품에 연결된 모듈은 code/name 수정과 삭제가 금지되고
 * 활성상태 토글만 허용된다. 상품 폼 셀렉트용 활성 목록은 캐시로 서빙하고 쓰기 시 무효화한다.
 */
final class ModuleService
{
    /** 활성 모듈 목록 캐시 키. */
    private const string CACHE_ACTIVE = 'modules.active.list';

    /** 코드성 데이터 캐시 TTL(초) — 1시간. */
    private const int CACHE_TTL = 3600;

    private ModuleModel $modules;

    public function __construct()
    {
        $this->modules = model(ModuleModel::class);
    }

    /**
     * 모듈 목록(관리 화면용, 사용 상품 수 포함).
     *
     * @return list<array{id:int, code:string, name:string, is_active:int, product_count:int}>
     */
    public function list(): array
    {
        return $this->modules->listWithUsage();
    }

    /**
     * 상품 폼 셀렉트용 활성 모듈 목록(캐시).
     *
     * @return list<array{id:int, code:string, name:string}>
     */
    public function activeForSelect(): array
    {
        $cache  = cache();
        $cached = $cache->get(self::CACHE_ACTIVE);
        if (is_array($cached)) {
            /** @var list<array{id:int, code:string, name:string}> $cached */
            return $cached;
        }

        $rows = $this->modules->activeForSelect();
        $cache->save(self::CACHE_ACTIVE, $rows, self::CACHE_TTL);

        return $rows;
    }

    /**
     * 모듈 생성. 성공 시 모듈 ID 반환.
     *
     * @throws RuntimeException 유효성·저장 실패
     */
    public function create(ModuleRequest $dto): int
    {
        $id = (int) ($this->modules->insert($dto->toRow(), true) ?: 0);
        if ($id === 0) {
            throw new RuntimeException($this->firstError($this->modules->errors()));
        }
        $this->invalidateCache();

        return $id;
    }

    /**
     * 모듈 수정. 사용 중이면 code/name 은 잠기고 활성상태만 반영된다.
     *
     * @throws RuntimeException 대상 없음·유효성·저장 실패
     */
    public function update(int $id, ModuleRequest $dto): void
    {
        if ($this->modules->find($id) === null) {
            throw new RuntimeException('모듈을 찾을 수 없습니다.');
        }

        // 사용 중 모듈: code/name 변경 불가 → 활성상태만 갱신
        if ($this->modules->isUsed($id)) {
            $this->modules->update($id, ['is_active' => $dto->isActive ? 1 : 0]);
            $this->invalidateCache();

            return;
        }

        $row       = $dto->toRow();
        $row['id'] = $id; // is_unique {id} 플레이스홀더 치환용
        if ($this->modules->update($id, $row) === false) {
            throw new RuntimeException($this->firstError($this->modules->errors()));
        }
        $this->invalidateCache();
    }

    /**
     * 모듈 삭제. 이미 상품에 연결돼 있으면 금지(비활성화만 가능).
     *
     * @throws RuntimeException 사용 중일 때
     */
    public function delete(int $id): void
    {
        if ($this->modules->isUsed($id)) {
            throw new RuntimeException('이미 상품에 연결된 모듈은 삭제할 수 없습니다. 비활성화만 가능합니다.');
        }
        $this->modules->delete($id);
        $this->invalidateCache();
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
        return $errors === [] ? '모듈 저장에 실패했습니다.' : (string) array_values($errors)[0];
    }
}
