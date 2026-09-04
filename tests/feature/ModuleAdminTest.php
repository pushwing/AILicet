<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ModuleModel;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\DatabaseTestCase;

/**
 * 모듈 마스터 관리 컨트롤러 feature 테스트.
 *
 * @internal
 */
final class ModuleAdminTest extends DatabaseTestCase
{
    use FeatureTestTrait;


    /**
     * @return array<string, array<string, mixed>>
     */
    private function operatorSession(): array
    {
        return ['authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value]];
    }

    public function testModuleTabRendersInProductsPage(): void
    {
        $result = $this->withSession($this->operatorSession())->get('admin/products');

        $result->assertStatus(200);
        $result->assertSee('모듈 관리');
        $result->assertSeeElement('#moduleGrid');
    }

    public function testCreateModulePersistsAndRedirects(): void
    {
        $result = $this->withSession($this->operatorSession())->post('admin/modules', [
            'code' => 'MD001', 'name' => '정량분석', 'is_active' => '1',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('modules', ['code' => 'MD001', 'name' => '정량분석']);
    }

    public function testNonOperatorIsForbidden(): void
    {
        $result = $this->withSession([
            'authUser' => ['id' => 9, 'name' => '고객', 'role' => UserRole::Member->value],
        ])->post('admin/modules', ['code' => 'MD999', 'name' => 'X']);

        $result->assertStatus(403);
    }

    public function testDeleteUsedModuleIsRejected(): void
    {
        $mid = (int) model(ModuleModel::class)->insert([
            'code' => 'MD500', 'name' => '정량분석', 'is_active' => 1,
        ], true);
        $productId = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT500', 'name' => 'P', 'license_type' => 'nodelock', 'is_active' => 1,
        ], true);
        model(\App\Models\ProductModuleModel::class)->insert([
            'product_id' => $productId, 'module_id' => $mid, 'code' => 'MD500', 'name' => '정량분석',
        ]);

        $result = $this->withSession($this->operatorSession())->post("admin/modules/{$mid}/delete");

        $result->assertRedirect();
        $this->seeInDatabase('modules', ['id' => $mid]); // 삭제되지 않음
    }
}
