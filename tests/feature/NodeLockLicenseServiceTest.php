<?php

declare(strict_types=1);

use App\DTO\NodeLockIssueRequest;
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Libraries\LicenseSigner;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Models\ProductModel;
use App\Services\NodeLockLicenseService;
use Tests\Support\DatabaseTestCase;

/**
 * 노드락 발급 엔진 DB 통합 테스트.
 *
 * @internal
 */
final class NodeLockLicenseServiceTest extends DatabaseTestCase
{
    private LicenseSigner $signer;
    private LocalLicenseStorage $storage;
    private NodeLockLicenseService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $pair         = LicenseSigner::generateKeypair();
        $this->signer = new LicenseSigner(
            base64_decode($pair['secretKey'], true) ?: '',
            base64_decode($pair['publicKey'], true) ?: '',
        );
        $this->tmpDir  = WRITEPATH . 'licenses-test';
        $this->storage = new LocalLicenseStorage($this->tmpDir);
        $this->service = new NodeLockLicenseService(
            $this->signer,
            $this->storage,
            new LicensePayloadStrategyResolver(),
            'test',
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // 테스트 산출물 정리
        if (is_dir($this->tmpDir)) {
            exec('rm -rf ' . escapeshellarg($this->tmpDir));
        }
    }

    private function seedProduct(): int
    {
        return (int) model(ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => 'tES LAB', 'product_family' => 'teslab',
            'license_type' => 'nodelock', 'version' => '3.0.1', 'is_active' => 1,
        ], true);
    }

    public function testIssueCreatesSignedFileAndRecords(): void
    {
        $productId = $this->seedProduct();

        $result = $this->service->issue(new NodeLockIssueRequest(
            productId: $productId,
            hostId: 'HOST-XYZ-0001',
            periodCode: 'period',
            issuedBy: 7,
            version: '3.0.1',
            expireDate: '2027-12-31',
            modules: ['MD001', 'MD002'],
            isTrial: false,
        ));

        // 반환값
        $this->assertGreaterThan(0, $result['license_id']);
        $this->assertStringContainsString('NLicense.lic', $result['path']);
        $this->assertNotSame('', $result['license_key']);

        // 라이센스 레코드
        $this->seeInDatabase('licenses', [
            'id'           => $result['license_id'],
            'license_type' => LicenseType::NodeLock->value,
            'status'       => LicenseStatus::Active->value,
            'host_id'      => 'HOST-XYZ-0001',
            'path'         => $result['path'],
        ]);

        // 발급 이력
        $this->seeInDatabase('license_history', [
            'license_id'  => $result['license_id'],
            'type'        => HistoryType::Issue->value,
            'license_key' => $result['license_key'],
        ]);
    }

    public function testStoredFileVerifiesWithPublicKey(): void
    {
        $productId = $this->seedProduct();

        $result = $this->service->issue(new NodeLockIssueRequest(
            productId: $productId,
            hostId: 'HOST-VERIFY',
            periodCode: 'perpetual',
            issuedBy: 1,
            modules: ['MD001'],
            companyName: '뉴로핏',
        ));

        // 저장된 파일을 공개키로 검증
        $stored = $this->storage->get($result['path']);
        $this->assertNotNull($stored);

        $payload = $this->signer->verify($stored);
        $this->assertNotNull($payload, '저장된 라이센스 파일이 공개키로 검증되어야 한다');
        $this->assertSame('HOST-VERIFY', $payload['host_id']);
        $this->assertSame('nodelock', $payload['license_type']);
        $this->assertSame(['MD001'], $payload['modules']);
        $this->assertSame('뉴로핏', $payload['company_name']);
        $this->assertTrue($payload['system_id_check']);
    }

    public function testUnknownProductThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->issue(new NodeLockIssueRequest(
            productId: 999999,
            hostId: 'H',
            periodCode: 'period',
            issuedBy: 1,
        ));
    }

    public function testConfigStoresModulesAsJson(): void
    {
        $productId = $this->seedProduct();
        $result    = $this->service->issue(new NodeLockIssueRequest(
            productId: $productId,
            hostId: 'H1',
            periodCode: 'perpetual_count',
            issuedBy: 1,
            modules: ['MD001', 'MD003'],
            limits: ['count' => 100],
        ));

        /** @var array<string,mixed> $row */
        $row    = model(\App\Models\LicenseModel::class)->find($result['license_id']);
        $config = json_decode((string) $row['config'], true);
        $this->assertSame(['MD001', 'MD003'], $config['modules']);
        $this->assertSame(100, $config['limits']['count']);
    }
}
