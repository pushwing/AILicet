<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filters\JwtAuthFilter;
use App\Libraries\Auth;
use App\Libraries\JwtLibrary;
use App\Libraries\JwtVerifier;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * JwtAuthFilter 인증·역할 분기 테스트 (DB 불필요).
 *
 * @internal
 */
final class JwtAuthFilterTest extends CIUnitTestCase
{
    private const string SECRET = 'filter-test-secret-0123456789-abcd';

    private JwtLibrary $jwt;
    private JwtAuthFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::clear();
        // 토큰 서명은 HS256(JwtLibrary), 검증은 동일 시크릿의 JwtVerifier(HS256 허용)로 수행.
        $this->jwt = new JwtLibrary(self::SECRET);
        Services::injectMock('aitesseraToken', new JwtVerifier(['HS256'], self::SECRET));
        $this->filter = new JwtAuthFilter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Auth::clear();
    }

    private function request(?string $token): \CodeIgniter\HTTP\IncomingRequest
    {
        $request = Services::incomingrequest(null, false);
        if ($token !== null) {
            $request->setHeader('Authorization', 'Bearer ' . $token);
        }

        return $request;
    }

    private function codeOf(ResponseInterface $response): string
    {
        $body = json_decode((string) $response->getBody(), true);

        return is_array($body) && isset($body['code']) ? (string) $body['code'] : '';
    }

    public function testMissingTokenReturns401(): void
    {
        $result = $this->filter->before($this->request(null));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(401, $result->getStatusCode());
        $this->assertSame('UNAUTHORIZED', $this->codeOf($result));
    }

    public function testValidTokenAuthenticatesAndSetsHolder(): void
    {
        $token  = $this->jwt->encode(['sub' => 7, 'role' => UserRole::Operator->value, 'aff' => 'ailicet'], 3600);
        $result = $this->filter->before($this->request($token));

        $this->assertNull($result); // 통과
        $this->assertSame(7, Auth::userId());
        $this->assertSame(UserRole::Operator, Auth::role());
        $this->assertTrue(Auth::isOperator());
    }

    public function testRoleAuthorizationAllowsMatchingRole(): void
    {
        $token  = $this->jwt->encode(['sub' => 1, 'role' => UserRole::Operator->value], 3600);
        $result = $this->filter->before($this->request($token), ['operator']);

        $this->assertNull($result);
    }

    public function testRoleAuthorizationDeniesMismatchedRole(): void
    {
        $token  = $this->jwt->encode(['sub' => 1, 'role' => UserRole::Member->value], 3600);
        $result = $this->filter->before($this->request($token), ['operator']);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(403, $result->getStatusCode());
        $this->assertSame('FORBIDDEN', $this->codeOf($result));
    }

    public function testExpiredTokenReturns401TokenExpired(): void
    {
        $token  = $this->jwt->encode(['sub' => 1, 'role' => 1], -10);
        $result = $this->filter->before($this->request($token));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame('TOKEN_EXPIRED', $this->codeOf($result));
    }

    public function testTokenMissingRoleReturns401InvalidToken(): void
    {
        $token  = $this->jwt->encode(['sub' => 1], 3600); // role 없음
        $result = $this->filter->before($this->request($token));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame('INVALID_TOKEN', $this->codeOf($result));
    }
}
