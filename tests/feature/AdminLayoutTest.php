<?php

declare(strict_types=1);

use App\Enums\UserRole;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 공통 레이아웃·렌더링 feature 테스트 (DB 불필요).
 *
 * 로그인 화면 렌더, 미인증 접근 리다이렉트, 인증 시 대시보드(그리드/차트) 렌더를 확인한다.
 *
 * @internal
 */
final class AdminLayoutTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testLoginPageRenders(): void
    {
        $result = $this->get('admin/login');

        $result->assertStatus(200);
        $result->assertSee('로그인');
        $result->assertSeeElement('form');
        $result->assertSee('AILicet');
    }

    public function testDashboardRedirectsWhenNotAuthenticated(): void
    {
        $result = $this->get('admin');

        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/login');
    }

    public function testDashboardRendersForAuthenticatedOperator(): void
    {
        $result = $this->withSession([
            'authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value, 'aff' => 'ailicet'],
        ])->get('admin');

        $result->assertStatus(200);
        $result->assertSee('대시보드');
        // 공통 레이아웃(사이드바) + authUser 병합 확인
        $result->assertSee('운영자');
        $result->assertSee('관리자');
        // 그리드·차트 컨테이너 렌더
        $result->assertSeeElement('#licenseGrid');
        $result->assertSeeElement('#issueChart');
    }

    public function testOperatorSeesOperatorMenus(): void
    {
        $result = $this->withSession([
            'authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value],
        ])->get('admin');

        $result->assertSee('회원관리');
        $result->assertSee('상품·모듈');
        $result->assertSee('감사로그');
    }
}
