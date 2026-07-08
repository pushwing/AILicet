<?php

declare(strict_types=1);

use App\DTO\ModuleRequest;
use App\Services\ModuleService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * ModuleService DB 통합 테스트.
 *
 * 사용 중 모듈은 code/name 수정과 삭제가 금지되고 활성상태만 변경된다.
 *
 * @internal
 */
final class ModuleServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private ModuleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->service = new ModuleService();
    }

    private function req(string $code = 'MD001', string $name = '정량분석', bool $active = true): ModuleRequest
    {
        return new ModuleRequest(code: $code, name: $name, isActive: $active);
    }

    /** 모듈을 상품에 연결(사용 중 상태로 만들기). */
    private function linkToProduct(int $moduleId, string $code, string $name): void
    {
        $productId = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT-' . $code, 'name' => 'P', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
        model(\App\Models\ProductModuleModel::class)->insert([
            'product_id' => $productId, 'module_id' => $moduleId, 'code' => $code, 'name' => $name,
        ]);
    }

    public function testCreateInsertsModule(): void
    {
        $id = $this->service->create($this->req('MD001', '정량분석'));

        $this->assertGreaterThan(0, $id);
        $this->seeInDatabase('modules', ['id' => $id, 'code' => 'MD001', 'name' => '정량분석', 'is_active' => 1]);
    }

    public function testDuplicateCodeThrows(): void
    {
        $this->service->create($this->req('MD100'));

        $this->expectException(RuntimeException::class);
        $this->service->create($this->req('MD100', '다른이름'));
    }

    public function testInvalidCodeCharactersRejected(): void
    {
        // 영숫자·_·- 외 문자(따옴표·꺾쇠 등)는 거부 — JS 컨텍스트 인젝션 방지
        $this->expectException(RuntimeException::class);
        $this->service->create($this->req('MD"><script>', '악성'));
    }

    public function testUpdateUnusedModuleChangesCodeAndName(): void
    {
        $id = $this->service->create($this->req('MD200', 'old'));
        $this->service->update($id, $this->req('MD201', 'new'));

        $this->seeInDatabase('modules', ['id' => $id, 'code' => 'MD201', 'name' => 'new']);
    }

    public function testUpdateUsedModuleLocksCodeAndNameButTogglesActive(): void
    {
        $id = $this->service->create($this->req('MD300', '정량분석'));
        $this->linkToProduct($id, 'MD300', '정량분석');

        // code/name 변경 시도 + 비활성화 → code/name 은 유지, is_active 만 반영
        $this->service->update($id, new ModuleRequest(code: 'CHANGED', name: 'CHANGED', isActive: false));

        $this->seeInDatabase('modules', ['id' => $id, 'code' => 'MD300', 'name' => '정량분석', 'is_active' => 0]);
    }

    public function testDeleteUnusedModule(): void
    {
        $id = $this->service->create($this->req('MD400'));
        $this->service->delete($id);

        $this->dontSeeInDatabase('modules', ['id' => $id]);
    }

    public function testDeleteUsedModuleThrows(): void
    {
        $id = $this->service->create($this->req('MD500'));
        $this->linkToProduct($id, 'MD500', '정량분석');

        $this->expectException(RuntimeException::class);
        $this->service->delete($id);
    }

    public function testActiveForSelectReturnsActiveAndCaches(): void
    {
        $this->service->create($this->req('MD600', 'A', true));
        $this->service->create($this->req('MD601', 'B', false)); // 비활성

        $rows = $this->service->activeForSelect();
        $this->assertCount(1, $rows);
        $this->assertSame('MD600', $rows[0]['code']);
        $this->assertIsArray(cache()->get('modules.active.list'));
    }
}
