<?php

declare(strict_types=1);

use App\DTO\ProductRequest;
use App\Models\LicenseModel;
use App\Models\ModuleModel;
use App\Services\ProductService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * ProductService DB 통합 테스트.
 *
 * 모듈은 마스터에서 선택하며, 상품에 저장된 모듈 구성은 수정 시 잠긴다.
 *
 * @internal
 */
final class ProductServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->service = new ProductService();
    }

    /** 모듈 마스터 하나 생성 후 ID 반환. */
    private function makeModule(string $code = 'MD001', string $name = '정량분석', int $active = 1): int
    {
        return (int) model(ModuleModel::class)->insert([
            'code' => $code, 'name' => $name, 'is_active' => $active,
        ], true);
    }

    /**
     * @param list<int>    $moduleIds
     * @param list<string> $versions
     */
    private function req(string $code = 'PT001', bool $active = true, array $moduleIds = [], array $versions = []): ProductRequest
    {
        return new ProductRequest(
            productCode: $code,
            name: 'tES LAB',
            licenseType: 'nodelock',
            productFamily: 'teslab',
            version: $versions[0] ?? '3.0.1',
            periodCode: 'period',
            isActive: $active,
            moduleIds: $moduleIds,
            versions: $versions,
        );
    }

    public function testCreateSnapshotsSelectedMasterModules(): void
    {
        $mid = $this->makeModule('MD001', '정량분석');
        $id  = $this->service->create($this->req('PT001', true, [$mid]));

        $this->assertGreaterThan(0, $id);
        $found = $this->service->find($id);
        $this->assertNotNull($found);
        $this->assertSame('PT001', $found['product']['product_code']);
        $this->assertCount(1, $found['modules']);
        $this->assertSame('MD001', $found['modules'][0]['code']);
        $this->assertSame('정량분석', $found['modules'][0]['name']);
        // module_id 로 마스터와 연결됐는지 확인
        $this->seeInDatabase('product_modules', ['product_id' => $id, 'module_id' => $mid, 'code' => 'MD001']);
    }

    public function testCreateIgnoresInactiveModule(): void
    {
        $active   = $this->makeModule('MD001', '정량분석', 1);
        $inactive = $this->makeModule('MD002', '비활성', 0);

        $id    = $this->service->create($this->req('PT010', true, [$active, $inactive]));
        $found = $this->service->find($id);

        $this->assertNotNull($found);
        $this->assertCount(1, $found['modules']); // 비활성 모듈은 스냅샷에서 제외
        $this->assertSame('MD001', $found['modules'][0]['code']);
    }

    public function testDuplicateProductCodeThrows(): void
    {
        $this->service->create($this->req('PT100'));

        $this->expectException(RuntimeException::class);
        $this->service->create($this->req('PT100'));
    }

    public function testUpdateKeepsModulesLocked(): void
    {
        $mid = $this->makeModule('MD001', 'A');
        $id  = $this->service->create($this->req('PT200', true, [$mid]));

        // 수정 시 다른 모듈을 넘겨도 구성은 바뀌지 않아야 한다.
        $other = $this->makeModule('MD002', 'B');
        $this->service->update($id, new ProductRequest(
            productCode: 'PT200',
            name: 'tES LAB v2',
            licenseType: 'floating',
            productFamily: 'teslab',
            version: '4.0',
            periodCode: 'perpetual_count',
            isActive: true,
            moduleIds: [$other],
        ));

        $found = $this->service->find($id);
        $this->assertNotNull($found);
        $this->assertSame('tES LAB v2', $found['product']['name']);       // 기본정보는 수정됨
        $this->assertSame('floating', $found['product']['license_type']);
        $this->assertCount(1, $found['modules']);                          // 모듈은 그대로
        $this->assertSame('MD001', $found['modules'][0]['code']);          // 잠금 유지
    }

    public function testCreateSyncsVersions(): void
    {
        // 이슈 #48: 상품 생성 시 버전 목록이 활성으로 저장된다.
        $id    = $this->service->create($this->req('PT500', true, [], ['2.0.1', '1.0.1']));
        $found = $this->service->find($id);

        $this->assertNotNull($found);
        $this->assertCount(2, $found['versions']);
        // byProduct 는 version DESC 정렬
        $this->assertSame('2.0.1', $found['versions'][0]['version']);
        $this->assertSame('1.0.1', $found['versions'][1]['version']);
        $this->seeInDatabase('product_versions', ['product_id' => $id, 'version' => '1.0.1', 'is_active' => 1]);
    }

    public function testUpdateAddsNewAndDeactivatesRemovedVersions(): void
    {
        $id = $this->service->create($this->req('PT510', true, [], ['1.0.1', '2.0.1']));

        // 2.0.1 제거 + 3.0.0 추가
        $this->service->update($id, $this->req('PT510', true, [], ['1.0.1', '3.0.0']));

        $found = $this->service->find($id);
        $this->assertNotNull($found);

        // 활성 버전은 1.0.1, 3.0.0 두 개
        $activeVersions = array_map(static fn (array $v): string => $v['version'], $found['versions']);
        sort($activeVersions);
        $this->assertSame(['1.0.1', '3.0.0'], $activeVersions);

        // 제거된 2.0.1 은 하드 삭제가 아니라 비활성으로 보존
        $this->seeInDatabase('product_versions', ['product_id' => $id, 'version' => '2.0.1', 'is_active' => 0]);
    }

    public function testUpdateReactivatesReaddedVersion(): void
    {
        $id = $this->service->create($this->req('PT520', true, [], ['1.0.1']));
        $this->service->update($id, $this->req('PT520', true, [], []));          // 1.0.1 비활성
        $this->seeInDatabase('product_versions', ['product_id' => $id, 'version' => '1.0.1', 'is_active' => 0]);

        $this->service->update($id, $this->req('PT520', true, [], ['1.0.1']));   // 다시 추가 → 재활성
        $this->seeInDatabase('product_versions', ['product_id' => $id, 'version' => '1.0.1', 'is_active' => 1]);
        // 중복 삽입 없이 단일 레코드 유지
        $this->assertSame(1, model(\App\Models\ProductVersionModel::class)->where('product_id', $id)->countAllResults());
    }

    public function testDeleteSoftDeletesProduct(): void
    {
        $id = $this->service->create($this->req('PT300'));
        $this->service->delete($id);

        $this->assertNull($this->service->find($id));
        $this->seeInDatabase('products', ['id' => $id]);          // 레코드는 남고
        $this->dontSeeInDatabase('products', ['id' => $id, 'deleted_at' => null]); // deleted_at 채워짐
    }

    public function testDeleteBlockedWhenLicenseIssued(): void
    {
        $id = $this->service->create($this->req('PT310'));
        model(LicenseModel::class)->insert([
            'product_id' => $id, 'license_type' => 'nodelock', 'period_code' => 'period', 'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->delete($id);
    }

    public function testActiveForSelectReturnsActiveAndCaches(): void
    {
        $this->service->create($this->req('PT400', true));
        $this->service->create($this->req('PT401', false)); // 비활성

        $rows = $this->service->activeForSelect();
        $this->assertCount(1, $rows);
        $this->assertSame('PT400', $rows[0]['product_code']);

        // 캐시 적재 확인
        $this->assertIsArray(cache()->get('products.active.list'));
    }
}
