<?php

declare(strict_types=1);

use App\DTO\CustomerRequest;
use App\Models\CustomerModel;
use App\Services\AgencyService;
use Tests\Support\DatabaseTestCase;

/**
 * 대행사 소유권 스코프 DB 통합 테스트.
 *
 * @internal
 */
final class AgencyServiceTest extends DatabaseTestCase
{
    private AgencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AgencyService();
    }

    private int $emailSeq = 0;

    private function seedAgency(int $userId, string $company): int
    {
        return (int) model(CustomerModel::class)->insert([
            'customer_type' => 'agency', 'user_id' => $userId, 'company_name' => $company,
            'name' => '담당', 'email' => 'ag' . (++$this->emailSeq) . '@n.com', 'is_active' => 1,
        ], true);
    }

    private function seedClient(int $agencyId, string $company): int
    {
        return (int) model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'parent_id' => $agencyId, 'company_name' => $company,
            'name' => '담당', 'email' => 'cl' . (++$this->emailSeq) . '@n.com', 'is_active' => 1,
        ], true);
    }

    private function seedProduct(): int
    {
        return (int) model(\App\Models\ProductModel::class)->insert([
            'product_code' => 'PT' . (++$this->emailSeq), 'name' => '상품', 'license_type' => 'floating', 'is_active' => 1,
        ], true);
    }

    private function mapLicense(int $customerId): int
    {
        $lid = (int) model(\App\Models\LicenseModel::class)->insert([
            'product_id' => $this->seedProduct(), 'license_type' => 'floating', 'period_code' => 'perpetual', 'status' => 'active',
        ], true);
        model(\App\Models\CustomerLicenseModel::class)->insert(['customer_id' => $customerId, 'license_id' => $lid]);

        return $lid;
    }

    public function testResolveAgencyId(): void
    {
        $aid = $this->seedAgency(100, '알파');

        $this->assertSame($aid, $this->service->resolveAgencyId(100));
        $this->assertNull($this->service->resolveAgencyId(999));
        $this->assertNull($this->service->resolveAgencyId(0));
    }

    public function testClientIsNotResolvedAsAgency(): void
    {
        $aid = $this->seedAgency(100, '알파');
        // user_id 를 가진 client 를 넣어도 agency 로는 해석되지 않아야 함
        model(CustomerModel::class)->insert([
            'customer_type' => 'client', 'user_id' => 200, 'parent_id' => $aid,
            'company_name' => '고객', 'name' => 'x', 'email' => 'c@n.com', 'is_active' => 1,
        ]);

        $this->assertNull($this->service->resolveAgencyId(200));
    }

    public function testCustomersScopedToAgency(): void
    {
        $a = $this->seedAgency(100, '알파');
        $b = $this->seedAgency(200, '베타');
        $this->seedClient($a, 'A고객1');
        $this->seedClient($a, 'A고객2');
        $this->seedClient($b, 'B고객1');

        $this->assertSame(2, $this->service->customersPaginate($a)['meta']['total']);
        $this->assertSame(1, $this->service->customersPaginate($b)['meta']['total']);
    }

    public function testOwnsCustomerAcrossAgencies(): void
    {
        $a  = $this->seedAgency(100, '알파');
        $b  = $this->seedAgency(200, '베타');
        $ca = $this->seedClient($a, 'A고객');
        $cb = $this->seedClient($b, 'B고객');

        $this->assertTrue($this->service->ownsCustomer($a, $ca));
        $this->assertFalse($this->service->ownsCustomer($a, $cb)); // 타 대행사 고객 차단
    }

    public function testOwnsLicenseScoped(): void
    {
        $a  = $this->seedAgency(100, '알파');
        $b  = $this->seedAgency(200, '베타');
        $ca = $this->seedClient($a, 'A고객');
        $cb = $this->seedClient($b, 'B고객');
        $la = $this->mapLicense($ca);
        $lb = $this->mapLicense($cb);

        $this->assertTrue($this->service->ownsLicense($a, $la));
        $this->assertFalse($this->service->ownsLicense($a, $lb)); // 타 대행사 라이센스 차단
        $this->assertSame([$la], $this->service->licenseIds($a));
    }

    public function testCreateClientForcesTypeAndParent(): void
    {
        $a  = $this->seedAgency(100, '알파');
        $id = $this->service->createClient($a, new CustomerRequest(
            customerType: 'agency', // 무시되고 client 로 강제
            companyName: '신규고객',
            name: '홍',
            email: 'new@n.com',
            parentId: 99999, // 무시되고 대행사로 강제
            phone: null,
            isActive: true,
        ));

        $row = model(CustomerModel::class)->find($id);
        $this->assertSame('client', $row['customer_type']);
        $this->assertSame($a, (int) $row['parent_id']);
    }

    public function testUpdateClientRejectsNonOwned(): void
    {
        $a  = $this->seedAgency(100, '알파');
        $b  = $this->seedAgency(200, '베타');
        $cb = $this->seedClient($b, 'B고객');

        $this->expectException(RuntimeException::class);
        $this->service->updateClient($a, $cb, new CustomerRequest(
            customerType: 'client',
            companyName: 'x',
            name: 'y',
            email: 'z@n.com',
            parentId: null,
            phone: null,
            isActive: true,
        ));
    }
}
