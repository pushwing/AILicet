<?php

declare(strict_types=1);

use App\Exceptions\AitesseraException;
use App\Integrations\AitesseraClient;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * AITessera 클라이언트 단위 테스트 — curlrequest 를 가짜 응답으로 주입.
 *
 * @internal
 */
final class AitesseraClientTest extends CIUnitTestCase
{
    /**
     * 지정한 상태코드·본문을 반환하는 가짜 curlrequest 를 주입한다.
     */
    private function fakeCurl(int $status, string $body): void
    {
        $response = (new Response(Factories::config(App::class)))
            ->setStatusCode($status)
            ->setBody($body);

        $fake = new class ($response) {
            public function __construct(private readonly Response $response)
            {
            }

            /**
             * @param array<string, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): Response
            {
                return $this->response;
            }
        };

        Services::injectMock('curlrequest', $fake);
    }

    public function testListUsersReturnsItemsAndMeta(): void
    {
        $this->fakeCurl(200, json_encode([
            'status' => 'success',
            'data'   => [['id' => 1, 'email' => 'a@n.com', 'role' => 3]],
            'meta'   => ['page' => 1, 'per_page' => 20, 'total' => 1, 'last_page' => 1],
        ]) ?: '');

        $result = (new AitesseraClient('http://aitessera'))->listUsers('tok', ['page' => 1]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('a@n.com', $result['items'][0]['email']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function testGetUser(): void
    {
        $this->fakeCurl(200, json_encode(['status' => 'success', 'data' => ['id' => 10, 'email' => 'x@n.com']]) ?: '');

        $user = (new AitesseraClient('http://aitessera'))->getUser('tok', 10);
        $this->assertSame(10, $user['id']);
    }

    public function testCreateAccountReturnsData(): void
    {
        $this->fakeCurl(201, json_encode(['status' => 'success', 'data' => ['id' => 99, 'email' => 'new@n.com', 'role' => 2]]) ?: '');

        $created = (new AitesseraClient('http://aitessera'))->createAccount('tok', ['email' => 'new@n.com']);
        $this->assertSame(99, $created['id']);
    }

    public function testErrorResponseThrowsWithRemoteCode(): void
    {
        $this->fakeCurl(409, json_encode(['status' => 'error', 'code' => 'ALREADY_EXISTS', 'message' => '이미 가입된 이메일']) ?: '');

        try {
            (new AitesseraClient('http://aitessera'))->createAccount('tok', ['email' => 'dup@n.com']);
            $this->fail('예외가 발생해야 한다');
        } catch (AitesseraException $e) {
            $this->assertSame('ALREADY_EXISTS', $e->errorCode());
            $this->assertSame(409, $e->httpStatusCode());
        }
    }

    public function testUnconfiguredThrows(): void
    {
        $this->expectException(AitesseraException::class);
        (new AitesseraClient(''))->listUsers('tok', []);
    }
}
