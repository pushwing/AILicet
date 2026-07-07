<?php

declare(strict_types=1);

use App\Models\CustomerModel;
use App\Services\ClientService;
use App\Services\ClientSignupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 고객 서비스(가입·스코프·문의) DB 통합 테스트.
 *
 * @internal
 */
final class ClientServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private ClientService $client;
    private ClientSignupService $signup;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new ClientService();
        $this->signup = new ClientSignupService();
    }

    private function seedClient(int $userId): int
    {
        return (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'user_id' => $userId, 'company_name' => '고객',
            'name' => 'x', 'email' => 'c' . (++$this->seq) . '@n.com', 'is_active' => 1,
        ], true);
    }

    private function mapLicense(int $customerId): int
    {
        $pid = (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT' . (++$this->seq), 'name' => '상품', 'license_type' => 'floating', 'is_active' => 1,
        ], true);
        $lid = (int) model(\App\Models\LicenseModel::class)->insert([
            'product_id' => $pid, 'license_type' => 'floating', 'period_code' => 'perpetual', 'status' => 'active',
        ], true);
        model(\App\Models\CustomerLicenseModel::class)->insert(['customer_id' => $customerId, 'license_id' => $lid]);

        return $lid;
    }

    public function testSignupCreatesInactiveThenVerifyActivates(): void
    {
        $result = $this->signup->register(['company_name' => '뉴로핏', 'name' => '홍', 'email' => 'signup@n.com']);

        $this->seeInDatabase('customers', ['id' => $result['id'], 'is_active' => 0, 'email' => 'signup@n.com']);
        $this->assertNotSame('', $result['token']);

        $this->assertTrue($this->signup->verify($result['token']));
        $this->seeInDatabase('customers', ['id' => $result['id'], 'is_active' => 1, 'verify_token' => null]);
    }

    public function testVerifyInvalidTokenFails(): void
    {
        $this->assertFalse($this->signup->verify('nope'));
        $this->assertFalse($this->signup->verify(''));
    }

    public function testResolveCustomerId(): void
    {
        $id = $this->seedClient(300);
        $this->assertSame($id, $this->client->resolveCustomerId(300));
        $this->assertNull($this->client->resolveCustomerId(999));
    }

    public function testLicensesScopedToCustomer(): void
    {
        $a = $this->seedClient(300);
        $b = $this->seedClient(400);
        $la = $this->mapLicense($a);
        $this->mapLicense($b);

        $result = $this->client->licensesPaginate($a);
        $this->assertSame(1, $result['meta']['total']);
        $this->assertTrue($this->client->ownsLicense($a, $la));
        $this->assertFalse($this->client->ownsLicense($b, $la)); // 타인 라이센스 차단
    }

    public function testCreateAndListInquiries(): void
    {
        $id = $this->seedClient(300);
        $this->client->createInquiry($id, 'c@n.com', ['subject' => '설치 문의', 'content' => '도와주세요']);

        $list = $this->client->myInquiries($id);
        $this->assertCount(1, $list);
        $this->assertSame('설치 문의', $list[0]['subject']);
        $this->assertSame('open', $list[0]['status']);
    }
}
