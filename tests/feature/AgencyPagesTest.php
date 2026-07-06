<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CustomerModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 대행사 페이지(소유권 스코프) feature 테스트.
 *
 * @internal
 */
final class AgencyPagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private int $agencyId  = 0;
    private int $otherId   = 0;
    private const int AGENCY_USER = 100;

    protected function setUp(): void
    {
        parent::setUp();
        // 로그인 대행사(user_id=100)와 타 대행사
        $this->agencyId = (int) model(CustomerModel::class)->insert([
            'customer_type' => 'agency', 'user_id' => self::AGENCY_USER, 'company_name' => '우리대행',
            'name' => '담당', 'email' => 'us@n.com', 'is_active' => 1,
        ], true);
        $this->otherId = (int) model(CustomerModel::class)->insert([
            'customer_type' => 'agency', 'user_id' => 200, 'company_name' => '타대행',
            'name' => '담당', 'email' => 'other@n.com', 'is_active' => 1,
        ], true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function agencySession(): array
    {
        return ['authUser' => ['id' => self::AGENCY_USER, 'name' => '대행사', 'role' => UserRole::Agency->value]];
    }

    private int $seq = 0;

    private function seedClient(int $agencyId, string $company): int
    {
        return (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'parent_id' => $agencyId, 'company_name' => $company,
            'name' => 'x', 'email' => 'cl' . (++$this->seq) . '@n.com', 'is_active' => 1,
        ], true);
    }

    public function testCustomersPageRendersForAgency(): void
    {
        $result = $this->withSession($this->agencySession())->get('agency/customers');
        $result->assertStatus(200);
        $result->assertSee('고객 관리');
    }

    public function testOperatorForbiddenFromAgencyArea(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 1, 'role' => UserRole::Operator->value]])->get('agency/customers');
        $result->assertStatus(403);
    }

    public function testDataReturnsOnlyOwnCustomers(): void
    {
        $this->seedClient($this->agencyId, '우리고객');
        $this->seedClient($this->otherId, '남의고객');

        $result = $this->withSession($this->agencySession())->get('agency/customers/data?page=1&per_page=20');
        $json   = json_decode($result->getJSON() ?? '', true);

        $this->assertSame(1, $json['meta']['total']);
        $this->assertSame('우리고객', $json['data'][0]['company_name']);
    }

    public function testCreateClientIsScopedToAgency(): void
    {
        $result = $this->withSession($this->agencySession())->post('agency/customers', [
            'company_name' => '신규고객', 'name' => '홍길동', 'email' => 'newc@n.com', 'is_active' => '1',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('customers', [
            'email' => 'newc@n.com', 'customer_type' => 'client', 'parent_id' => $this->agencyId,
        ]);
    }

    public function testCannotEditOtherAgencyCustomer(): void
    {
        $foreign = $this->seedClient($this->otherId, '남의고객');

        $result = $this->withSession($this->agencySession())->get("agency/customers/{$foreign}/edit");
        // 소유권 없음 → 목록으로 리다이렉트
        $result->assertRedirectTo('/agency/customers');
    }

    public function testNoAgencyMappingShowsNotice(): void
    {
        // user_id 매핑이 없는 대행사 계정
        $result = $this->withSession(['authUser' => ['id' => 777, 'role' => UserRole::Agency->value]])->get('agency/customers');
        $result->assertStatus(200);
        $result->assertSee('대행사 정보가 연결되지 않았습니다');
    }
}
