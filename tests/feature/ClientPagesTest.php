<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CustomerModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 고객 페이지(가입·스코프·고객센터) feature 테스트.
 *
 * @internal
 */
final class ClientPagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private const int CLIENT_USER = 300;

    private function memberSession(): array
    {
        return ['authUser' => ['id' => self::CLIENT_USER, 'name' => '고객', 'role' => UserRole::Member->value]];
    }

    private function seedMyCustomer(): int
    {
        return (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'user_id' => self::CLIENT_USER, 'company_name' => '내회사',
            'name' => 'x', 'email' => 'me@n.com', 'is_active' => 1,
        ], true);
    }

    public function testSignupPageRendersPublicly(): void
    {
        $result = $this->get('signup');
        $result->assertStatus(200);
        $result->assertSee('회원가입');
    }

    public function testSignupCreatesInactiveCustomer(): void
    {
        $result = $this->post('signup', [
            'company_name' => '뉴로핏', 'name' => '홍길동', 'email' => 'newuser@n.com', 'phone' => '010',
        ]);

        $result->assertStatus(200);
        $result->assertSee('가입 확인');
        $this->seeInDatabase('customers', ['email' => 'newuser@n.com', 'customer_type' => 'client', 'is_active' => 0]);
    }

    public function testVerifyActivatesAccount(): void
    {
        $reg   = service('clientSignupService')->register(['company_name' => 'A', 'name' => 'B', 'email' => 'v@n.com']);
        $result = $this->get('verify?token=' . $reg['token']);

        $result->assertStatus(200);
        $result->assertSee('인증 완료');
        $this->seeInDatabase('customers', ['id' => $reg['id'], 'is_active' => 1]);
    }

    public function testLicensesForbiddenForNonMember(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 1, 'role' => UserRole::Operator->value]])->get('client/licenses');
        $result->assertStatus(403);
    }

    public function testMyLicensesRendersForMember(): void
    {
        $this->seedMyCustomer();
        $result = $this->withSession($this->memberSession())->get('client/licenses');
        $result->assertStatus(200);
        $result->assertSee('내 라이센스');
    }

    public function testNoCustomerMappingShowsNotice(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 777, 'role' => UserRole::Member->value]])->get('client/licenses');
        $result->assertStatus(200);
        $result->assertSee('고객 정보가 연결되지 않았습니다');
    }

    public function testSupportSubmitCreatesInquiry(): void
    {
        $cid    = $this->seedMyCustomer();
        $result = $this->withSession($this->memberSession())->post('client/support', [
            'subject' => '라이센스 문의', 'content' => '설치가 안됩니다',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('inquiries', ['customer_id' => $cid, 'subject' => '라이센스 문의', 'status' => 'open']);
    }

    public function testProfileUpdate(): void
    {
        $cid = $this->seedMyCustomer();
        $result = $this->withSession($this->memberSession())->post('client/profile', [
            'company_name' => '수정회사', 'name' => '수정담당', 'phone' => '010-9999',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('customers', ['id' => $cid, 'company_name' => '수정회사']);
    }
}
