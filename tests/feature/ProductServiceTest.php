<?php

declare(strict_types=1);

use App\DTO\ProductRequest;
use App\Services\ProductService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * ProductService DB 통합 테스트.
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

    private function req(string $code = 'PT001', bool $active = true, array $modules = [['code' => 'MD001', 'name' => '정량분석']]): ProductRequest
    {
        return new ProductRequest(
            productCode: $code,
            name: 'tES LAB',
            licenseType: 'nodelock',
            productFamily: 'teslab',
            version: '3.0.1',
            periodCode: 'period',
            isActive: $active,
            modules: $modules,
        );
    }

    public function testCreateInsertsProductWithModules(): void
    {
        $id = $this->service->create($this->req());

        $this->assertGreaterThan(0, $id);
        $found = $this->service->find($id);
        $this->assertNotNull($found);
        $this->assertSame('PT001', $found['product']['product_code']);
        $this->assertCount(1, $found['modules']);
        $this->assertSame('MD001', $found['modules'][0]['code']);
    }

    public function testDuplicateProductCodeThrows(): void
    {
        $this->service->create($this->req('PT100'));

        $this->expectException(RuntimeException::class);
        $this->service->create($this->req('PT100'));
    }

    public function testUpdateResyncsModules(): void
    {
        $id = $this->service->create($this->req('PT200', true, [['code' => 'MD001', 'name' => 'A']]));

        $this->service->update($id, new ProductRequest(
            productCode: 'PT200',
            name: 'tES LAB v2',
            licenseType: 'floating',
            productFamily: 'teslab',
            version: '4.0',
            periodCode: 'perpetual_count',
            isActive: true,
            modules: [['code' => 'MD002', 'name' => 'B'], ['code' => 'MD003', 'name' => 'C']],
        ));

        $found = $this->service->find($id);
        $this->assertNotNull($found);
        $this->assertSame('tES LAB v2', $found['product']['name']);
        $this->assertSame('floating', $found['product']['license_type']);
        $this->assertCount(2, $found['modules']); // 기존 1개 → 2개로 재동기화
    }

    public function testDeleteSoftDeletesProduct(): void
    {
        $id = $this->service->create($this->req('PT300'));
        $this->service->delete($id);

        $this->assertNull($this->service->find($id));
        $this->seeInDatabase('products', ['id' => $id]);          // 레코드는 남고
        $this->dontSeeInDatabase('products', ['id' => $id, 'deleted_at' => null]); // deleted_at 채워짐
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
