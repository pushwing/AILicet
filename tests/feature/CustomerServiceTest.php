<?php

declare(strict_types=1);

use App\DTO\CustomerRequest;
use App\Integrations\AitesseraClient;
use App\Services\CustomerService;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * CustomerService DB 통합 테스트.
 *
 * @internal
 */
final class CustomerServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerService();
    }

    private function req(string $type, string $company, string $email, ?int $parent = null, ?int $userId = null): CustomerRequest
    {
        return new CustomerRequest(
            customerType: $type,
            companyName: $company,
            name: '담당자',
            email: $email,
            parentId: $parent,
            phone: '010-0000-0000',
            isActive: true,
            userId: $userId,
        );
    }

    public function testCreateAndFind(): void
    {
        $id = $this->service->create($this->req('agency', '뉴로핏', 'a@n.com'));

        $found = $this->service->find($id);
        $this->assertNotNull($found);
        $this->assertSame('뉴로핏', $found['company_name']);
        $this->assertSame('agency', $found['customer_type']);
    }

    public function testDuplicateEmailThrows(): void
    {
        $this->service->create($this->req('client', 'A', 'dup@n.com'));

        $this->expectException(RuntimeException::class);
        $this->service->create($this->req('client', 'B', 'dup@n.com'));
    }

    public function testUpdateKeepsSameEmail(): void
    {
        $id = $this->service->create($this->req('client', 'A', 'keep@n.com'));

        $this->service->update($id, $this->req('client', 'A-renamed', 'keep@n.com'));
        $this->assertSame('A-renamed', $this->service->find($id)['company_name']);
    }

    public function testDeleteSoftDeletes(): void
    {
        $id = $this->service->create($this->req('client', 'A', 'del@n.com'));
        $this->service->delete($id);

        $this->assertNull($this->service->find($id));
        $this->seeInDatabase('customers', ['id' => $id]);
    }

    public function testPaginateReturnsMetaStandard(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->service->create($this->req('client', "회사{$i}", "c{$i}@n.com"));
        }

        $result = $this->service->paginate('', '', 1, 10);
        $this->assertCount(10, $result['items']);
        $this->assertSame(['page', 'per_page', 'total', 'last_page'], array_keys($result['meta']));
        $this->assertSame(25, $result['meta']['total']);
        $this->assertSame(3, $result['meta']['last_page']);

        $page3 = $this->service->paginate('', '', 3, 10);
        $this->assertCount(5, $page3['items']); // 마지막 페이지 잔여
    }

    public function testChildClientsReturnsOnlyOwnClients(): void
    {
        $agencyA = $this->service->create($this->req('agency', '대행A', 'a@n.com'));
        $agencyB = $this->service->create($this->req('agency', '대행B', 'b@n.com'));

        $this->service->create($this->req('client', '고객1', 'c1@n.com', $agencyA));
        $this->service->create($this->req('client', '고객2', 'c2@n.com', $agencyA));
        $this->service->create($this->req('client', '타대행고객', 'c3@n.com', $agencyB));
        $this->service->create($this->req('client', '무소속고객', 'c4@n.com'));

        $clients = $this->service->childClients($agencyA);

        $this->assertCount(2, $clients);
        $this->assertSame(['고객1', '고객2'], array_column($clients, 'company_name'));
    }

    public function testChildClientsEmptyWhenNone(): void
    {
        $agency = $this->service->create($this->req('agency', '대행', 'solo@n.com'));

        $this->assertSame([], $this->service->childClients($agency));
    }

    public function testLinkedAccountReturnsNullWhenNoUserIdOrToken(): void
    {
        $this->assertNull($this->service->linkedAccount(null, 'token'));
        $this->assertNull($this->service->linkedAccount(0, 'token'));
        $this->assertNull($this->service->linkedAccount(5, null));
    }

    public function testLinkedAccountFetchesFromAitessera(): void
    {
        $client = new class ('http://aitessera') extends AitesseraClient {
            public function getUser(string $token, int $id): array
            {
                return ['id' => $id, 'email' => 'link@n.com', 'name' => '연동회원', 'is_active' => true];
            }
        };
        Services::injectMock('aitesseraClient', $client);

        $result = $this->service->linkedAccount(7, 'token');

        $this->assertNotNull($result);
        $this->assertSame('found', $result['status']);
        $this->assertSame(7, $result['id']);
        $this->assertSame('link@n.com', $result['email']);
        $this->assertSame('연동회원', $result['name']);
        $this->assertTrue($result['is_active']);
    }

    public function testLinkedAccountMissingWhenNotFound(): void
    {
        $client = new class ('http://aitessera') extends AitesseraClient {
            public function getUser(string $token, int $id): array
            {
                throw new \App\Exceptions\AitesseraException('회원을 찾을 수 없습니다.', 'NOT_FOUND', 404);
            }
        };
        Services::injectMock('aitesseraClient', $client);

        $result = $this->service->linkedAccount(7, 'token');
        $this->assertSame('missing', $result['status']);
        $this->assertSame(7, $result['id']);
    }

    public function testLinkedAccountErrorOnOtherFailure(): void
    {
        $client = new class ('http://aitessera') extends AitesseraClient {
            public function getUser(string $token, int $id): array
            {
                throw new \App\Exceptions\AitesseraException('만료', 'TOKEN_EXPIRED', 401);
            }
        };
        Services::injectMock('aitesseraClient', $client);

        $result = $this->service->linkedAccount(7, 'token');
        $this->assertSame('error', $result['status']);
    }

    public function testPaginateSearchAndTypeFilter(): void
    {
        $this->service->create($this->req('agency', '알파대행', 'alpha@n.com'));
        $this->service->create($this->req('client', '베타고객', 'beta@n.com'));
        $this->service->create($this->req('client', '감마고객', 'gamma@n.com'));

        // 검색어
        $byName = $this->service->paginate('베타', '', 1, 20);
        $this->assertSame(1, $byName['meta']['total']);
        $this->assertSame('베타고객', $byName['items'][0]['company_name']);

        // 유형 필터
        $byType = $this->service->paginate('', 'agency', 1, 20);
        $this->assertSame(1, $byType['meta']['total']);
        $this->assertSame('agency', $byType['items'][0]['customer_type']);
    }
}
