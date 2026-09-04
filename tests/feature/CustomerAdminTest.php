<?php

declare(strict_types=1);

use App\Enums\UserRole;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\DatabaseTestCase;

/**
 * 회원관리 컨트롤러 feature 테스트.
 *
 * @internal
 */
final class CustomerAdminTest extends DatabaseTestCase
{
    use FeatureTestTrait;


    /**
     * @return array<string, array<string, mixed>>
     */
    private function operator(): array
    {
        return ['authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value]];
    }

    public function testIndexRendersForOperator(): void
    {
        $result = $this->withSession($this->operator())->get('admin/members');

        $result->assertStatus(200);
        $result->assertSee('회원관리');
        $result->assertSeeElement('#memberGrid');
    }

    public function testNonOperatorForbidden(): void
    {
        $result = $this->withSession([
            'authUser' => ['id' => 9, 'name' => '대행', 'role' => UserRole::Agency->value],
        ])->get('admin/members');

        $result->assertStatus(403);
    }

    public function testCreatePersists(): void
    {
        $result = $this->withSession($this->operator())->post('admin/members', [
            'customer_type' => 'agency',
            'company_name'  => '뉴로핏',
            'name'          => '홍길동',
            'email'         => 'hong@neurophet.com',
            'phone'         => '010-1234-5678',
            'is_active'     => '1',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('customers', ['email' => 'hong@neurophet.com', 'customer_type' => 'agency']);
    }

    public function testDataEndpointReturnsMetaAndSearch(): void
    {
        model(\App\Models\CustomerModel::class)->insert([
            'customer_type' => 'client', 'company_name' => '검색대상회사', 'name' => 'A',
            'email' => 'target@n.com', 'is_active' => 1,
        ]);

        $result = $this->withSession($this->operator())->get('admin/members/data?search=검색대상&page=1&per_page=20');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('success', $json['status']);
        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame(['page', 'per_page', 'total', 'last_page'], array_keys($json['meta']));
        $this->assertSame('검색대상회사', $json['data'][0]['company_name']);
    }
}
