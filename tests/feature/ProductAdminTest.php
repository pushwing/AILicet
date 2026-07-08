<?php

declare(strict_types=1);

use App\Enums\UserRole;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 상품 관리 컨트롤러 feature 테스트.
 *
 * @internal
 */
final class ProductAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operatorSession(): array
    {
        return ['authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value]];
    }

    public function testIndexRendersForOperator(): void
    {
        $result = $this->withSession($this->operatorSession())->get('admin/products');

        $result->assertStatus(200);
        $result->assertSee('상품·모듈 관리');
        $result->assertSeeElement('#productGrid');
    }

    public function testNonOperatorIsForbidden(): void
    {
        $result = $this->withSession([
            'authUser' => ['id' => 9, 'name' => '고객', 'role' => UserRole::Member->value],
        ])->get('admin/products');

        $result->assertStatus(403);
    }

    public function testCreateProductPersistsAndRedirects(): void
    {
        // 모듈 마스터 선등록 후, 상품 폼에서 선택한다.
        $mid = (int) model(\App\Models\ModuleModel::class)->insert([
            'code' => 'MD001', 'name' => '정량분석', 'is_active' => 1,
        ], true);

        $result = $this->withSession($this->operatorSession())->post('admin/products', [
            'product_code' => 'PT900',
            'name'         => 'AQUA',
            'license_type' => 'floating',
            'period_code'  => 'perpetual_credit',
            'version'      => '2.1.3',
            'is_active'    => '1',
            'module_ids'   => [(string) $mid],
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('products', ['product_code' => 'PT900', 'license_type' => 'floating']);
        $this->seeInDatabase('product_modules', ['module_id' => $mid, 'code' => 'MD001', 'name' => '정량분석']);
    }

    public function testEditShowsExistingProduct(): void
    {
        $id = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT901', 'name' => 'TMS LAB', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);

        $result = $this->withSession($this->operatorSession())->get("admin/products/{$id}/edit");

        $result->assertStatus(200);
        $result->assertSee('상품 수정');
        $result->assertSee('PT901');
    }
}
