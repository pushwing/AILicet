<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Integrations\AitesseraClient;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 운영자 회원 계정 관리(AITessera 연동) feature 테스트.
 *
 * @internal
 */
final class AccountAdminTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private object $client;

    protected function setUp(): void
    {
        parent::setUp();

        // AITessera 클라이언트 더블 주입(호출 기록)
        $this->client = new class ('http://aitessera') extends AitesseraClient {
            /** @var list<array<string, mixed>> */
            public array $created = [];
            /** @var list<array{0:int, 1:array<string, mixed>}> */
            public array $updated = [];

            public function listUsers(string $token, array $query): array
            {
                return [
                    'items' => [['id' => 1, 'email' => 'op@n.com', 'name' => '운영', 'role' => 1, 'is_active' => true]],
                    'meta'  => ['page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ];
            }

            public function getUser(string $token, int $id): array
            {
                return ['id' => $id, 'email' => 'x@n.com', 'name' => '홍', 'role' => 2, 'is_active' => true, 'company' => 'A'];
            }

            public function createAccount(string $token, array $data): array
            {
                $this->created[] = $data;

                return ['id' => 99, 'email' => (string) $data['email']];
            }

            public function updateUser(string $token, int $id, array $data): void
            {
                $this->updated[] = [$id, $data];
            }
        };
        Services::injectMock('aitesseraClient', $this->client);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operator(bool $withToken = true): array
    {
        $user = ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value, 'aff' => 'ailicet'];
        if ($withToken) {
            $user['token'] = 'operator-access-token';
        }

        return ['authUser' => $user];
    }

    public function testUnavailableWithoutToken(): void
    {
        $result = $this->withSession($this->operator(false))->get('admin/accounts');
        $result->assertStatus(200);
        $result->assertSee('AITessera 연동이 필요합니다');
    }

    public function testIndexRendersWithToken(): void
    {
        $result = $this->withSession($this->operator())->get('admin/accounts');
        $result->assertStatus(200);
        $result->assertSee('운영자 관리');
        $result->assertSeeElement('#grid');
    }

    public function testNonOperatorForbidden(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 9, 'role' => UserRole::Agency->value]])->get('admin/accounts');
        $result->assertStatus(403);
    }

    public function testDataProxyReturnsUsers(): void
    {
        $result = $this->withSession($this->operator())->get('admin/accounts/data?page=1');
        $json   = json_decode($result->getJSON() ?? '', true);

        $this->assertSame('success', $json['status']);
        $this->assertSame('op@n.com', $json['data'][0]['email']);
        $this->assertSame(1, $json['meta']['total']);
    }

    public function testDataWithoutTokenReturns401(): void
    {
        $result = $this->withSession($this->operator(false))->get('admin/accounts/data');
        $result->assertStatus(401);
    }

    public function testCreateCallsClient(): void
    {
        // 운영자 관리 화면 — 요청의 role 값과 무관하게 운영자(role=1)로 고정 생성한다.
        $result = $this->withSession($this->operator())->post('admin/accounts', [
            'email' => 'new@n.com', 'password' => 'secret12', 'role' => '2', 'name' => '김운영', 'contact' => '010',
        ]);

        $result->assertRedirect();
        $this->assertCount(1, $this->client->created);
        $this->assertSame('new@n.com', $this->client->created[0]['email']);
        $this->assertSame(UserRole::Operator->value, $this->client->created[0]['role']);
        $this->assertSame('ailicet', $this->client->created[0]['affiliation']);
    }

    public function testUpdateCallsClient(): void
    {
        $result = $this->withSession($this->operator())->post('admin/accounts/5', [
            'name' => '수정됨', 'is_active' => '0',
        ]);

        $result->assertRedirect();
        $this->assertCount(1, $this->client->updated);
        $this->assertSame(5, $this->client->updated[0][0]);
        $this->assertSame('수정됨', $this->client->updated[0][1]['name']);
    }

    /** 토큰 만료 예외를 던지는 클라이언트 더블을 주입한다. */
    private function injectExpiredClient(): void
    {
        $expired = new class ('http://aitessera') extends AitesseraClient {
            public function listUsers(string $token, array $query): array
            {
                throw new \App\Exceptions\AitesseraException('토큰이 만료되었습니다.', 'TOKEN_EXPIRED', 401);
            }

            public function getUser(string $token, int $id): array
            {
                throw new \App\Exceptions\AitesseraException('토큰이 만료되었습니다.', 'TOKEN_EXPIRED', 401);
            }
        };
        Services::injectMock('aitesseraClient', $expired);
    }

    public function testExpiredTokenOnDataReturnsSessionExpired(): void
    {
        $this->injectExpiredClient();

        $result = $this->withSession($this->operator())->get('admin/accounts/data?page=1');
        $result->assertStatus(401);

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('SESSION_EXPIRED', $json['code']);
        $this->assertSame('/admin/login', $json['redirect']);
    }

    public function testExpiredTokenOnEditRedirectsToLogin(): void
    {
        $this->injectExpiredClient();

        $result = $this->withSession($this->operator())->get('admin/accounts/5/edit');
        $result->assertRedirectTo('/admin/login');
    }

    /** 리프레시 토큰이 있으면 만료 시 자동 갱신 후 재시도로 정상 응답한다. */
    public function testExpiredTokenAutoRefreshesAndRetries(): void
    {
        $refreshable = new class ('http://aitessera') extends AitesseraClient {
            public int $listCalls    = 0;
            public int $refreshCalls  = 0;

            public function listUsers(string $token, array $query): array
            {
                $this->listCalls++;
                // 첫 호출(만료 토큰)은 만료 예외, 갱신된 토큰으로 온 재시도는 성공.
                if ($token === 'operator-access-token') {
                    throw new \App\Exceptions\AitesseraException('토큰이 만료되었습니다.', 'TOKEN_EXPIRED', 401);
                }

                return [
                    'items' => [['id' => 1, 'email' => 'op@n.com', 'name' => '운영', 'role' => 1, 'is_active' => true]],
                    'meta'  => ['page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
                ];
            }

            public function refresh(string $refreshToken): array
            {
                $this->refreshCalls++;

                return ['access_token' => 'refreshed-access-token', 'refresh_token' => 'rotated-refresh-token'];
            }
        };
        Services::injectMock('aitesseraClient', $refreshable);

        $session                     = $this->operator();
        $session['authUser']['refresh'] = 'stored-refresh-token';

        $result = $this->withSession($session)->get('admin/accounts/data?page=1');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('success', $json['status']);
        $this->assertSame('op@n.com', $json['data'][0]['email']);
        $this->assertSame(1, $refreshable->refreshCalls);
        $this->assertSame(2, $refreshable->listCalls); // 만료 → 갱신 → 재시도
    }

    /** 리프레시 토큰이 없으면 자동 갱신 없이 재로그인으로 안내한다. */
    public function testExpiredTokenWithoutRefreshFallsBackToRelogin(): void
    {
        $this->injectExpiredClient();

        // operator() 세션에는 refresh 토큰이 없다.
        $result = $this->withSession($this->operator())->get('admin/accounts/data?page=1');
        $result->assertStatus(401);

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('SESSION_EXPIRED', $json['code']);
    }
}
