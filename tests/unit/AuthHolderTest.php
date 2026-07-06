<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Libraries\Auth;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Auth 홀더 및 UserRole 매핑 테스트 (DB 불필요).
 *
 * @internal
 */
final class AuthHolderTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Auth::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Auth::clear();
    }

    public function testDefaultIsUnauthenticated(): void
    {
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::userId());
        $this->assertNull(Auth::role());
    }

    public function testSetUserPopulatesHolder(): void
    {
        Auth::setUser(15, UserRole::Agency, 'ailicet');

        $this->assertTrue(Auth::check());
        $this->assertSame(15, Auth::userId());
        $this->assertSame(UserRole::Agency, Auth::role());
        $this->assertSame('ailicet', Auth::affiliation());
        $this->assertTrue(Auth::isAgency());
        $this->assertFalse(Auth::isOperator());
        $this->assertFalse(Auth::isMember());
    }

    public function testClearResetsHolder(): void
    {
        Auth::setUser(1, UserRole::Operator);
        Auth::clear();

        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::role());
    }

    public function testUserRoleValuesMatchAitessera(): void
    {
        $this->assertSame(1, UserRole::Operator->value);
        $this->assertSame(2, UserRole::Agency->value);
        $this->assertSame(3, UserRole::Member->value);
        $this->assertSame('운영자', UserRole::Operator->label());
    }

    public function testUserRoleFromSlug(): void
    {
        $this->assertSame(UserRole::Operator, UserRole::fromSlug('operator'));
        $this->assertSame(UserRole::Operator, UserRole::fromSlug('admin'));
        $this->assertSame(UserRole::Agency, UserRole::fromSlug('agency'));
        $this->assertSame(UserRole::Member, UserRole::fromSlug('client'));
        $this->assertNull(UserRole::fromSlug('unknown'));
    }
}
