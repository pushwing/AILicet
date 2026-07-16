<?php

declare(strict_types=1);

use App\Integrations\AitesseraClient;
use App\Libraries\JwtLibrary;
use App\Libraries\JwtVerifier;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Admin 로그인(AITessera 연동) feature 테스트 — 이슈 #109: 로그인 요청에 affiliation 전달 확인.
 *
 * @internal
 */
final class AuthLoginTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const string SECRET = 'auth-login-test-secret-0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();
        // 로컬 .env 는 커밋되지 않아 CI 에는 aitessera.baseURL 이 비어 있다(AITessera 미설정) —
        // 실제 로그인 분기(authenticateWithAitessera)를 항상 태우도록 테스트에서 명시 설정한다.
        putenv('aitessera.baseURL=http://aitessera');
        $_ENV['aitessera.baseURL']    = 'http://aitessera';
        $_SERVER['aitessera.baseURL'] = 'http://aitessera';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('aitessera.baseURL');
        unset($_ENV['aitessera.baseURL'], $_SERVER['aitessera.baseURL']);
        \App\Libraries\Auth::clear();
    }

    public function testLoginSendsAffiliationAndSetsSession(): void
    {
        $token = (new JwtLibrary(self::SECRET))->encode(['sub' => 7, 'role' => 1, 'aff' => AitesseraClient::AFFILIATION]);

        $client = new class ('http://aitessera', $token) extends AitesseraClient {
            /** @var list<array{0:string,1:string,2:string}> */
            public array $calls = [];

            public function __construct(string $baseUrl, private readonly string $fixedToken)
            {
                parent::__construct($baseUrl);
            }

            public function login(string $email, string $password, string $affiliation): ?array
            {
                $this->calls[] = [$email, $password, $affiliation];

                return ['access_token' => $this->fixedToken, 'refresh_token' => 'rt-1'];
            }
        };
        Services::injectMock('aitesseraClient', $client);
        Services::injectMock('aitesseraToken', new JwtVerifier(['HS256'], self::SECRET));

        $result = $this->post('admin/login', ['email' => 'op@n.com', 'password' => 'secret12']);

        $result->assertRedirectTo('/admin');
        $this->assertCount(1, $client->calls);
        $this->assertSame(['op@n.com', 'secret12', AitesseraClient::AFFILIATION], $client->calls[0]);

        $session = session()->get('authUser');
        $this->assertSame(7, $session['id']);
        $this->assertSame(AitesseraClient::AFFILIATION, $session['aff']);
        $this->assertSame($token, $session['token']);
        $this->assertSame('rt-1', $session['refresh']);
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $client = new class ('http://aitessera') extends AitesseraClient {
            public function login(string $email, string $password, string $affiliation): ?array
            {
                return null;
            }
        };
        Services::injectMock('aitesseraClient', $client);

        $result = $this->post('admin/login', ['email' => 'op@n.com', 'password' => 'wrong123']);

        $result->assertStatus(200);
        $result->assertSee('이메일 또는 비밀번호가 올바르지 않습니다.');
    }
}
