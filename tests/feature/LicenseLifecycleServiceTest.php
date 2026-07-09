<?php

declare(strict_types=1);

use App\DTO\FloatingIssueRequest;
use App\DTO\NodeLockIssueRequest;
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Libraries\LicenseSigner;
use App\Licensing\Storage\LicenseStorageInterface;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Services\FloatingLicenseService;
use App\Services\LicenseLifecycleService;
use App\Services\NodeLockLicenseService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 라이센스 생명주기(상태/연장/재발급) DB 통합 테스트.
 *
 * @internal
 */
final class LicenseLifecycleServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private LicenseSigner $signer;
    private LocalLicenseStorage $storage;
    private NodeLockLicenseService $nodeLock;
    private LicenseLifecycleService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $pair         = LicenseSigner::generateKeypair();
        $this->signer = new LicenseSigner(
            base64_decode($pair['secretKey'], true) ?: '',
            base64_decode($pair['publicKey'], true) ?: '',
        );
        $this->tmpDir   = WRITEPATH . 'licenses-lifecycle-test';
        $this->storage  = new LocalLicenseStorage($this->tmpDir);
        $this->nodeLock = new NodeLockLicenseService($this->signer, $this->storage, new LicensePayloadStrategyResolver(), 'test');
        $this->service  = new LicenseLifecycleService($this->nodeLock);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            exec('rm -rf ' . escapeshellarg($this->tmpDir));
        }
    }

    private function seedProduct(): int
    {
        return (int) model(ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => 'tES LAB', 'license_type' => 'nodelock', 'version' => '3.0', 'is_active' => 1,
        ], true);
    }

    private function issueNodeLock(): int
    {
        $result = $this->nodeLock->issue(new NodeLockIssueRequest(
            productId: $this->seedProduct(),
            hostId: 'HOST-ORIG',
            periodCode: 'period',
            issuedBy: 1,
            expireDate: '2027-12-31',
            modules: ['MD001'],
        ));

        return $result['license_id'];
    }

    private function issueFloating(): array
    {
        return (new FloatingLicenseService())->issue(FloatingIssueRequest::fromArray([
            'product_id' => $this->seedProduct(), 'period_code' => 'perpetual', 'issued_by' => 1,
        ]));
    }

    private function historyCount(int $licenseId, string $type): int
    {
        return model(LicenseHistoryModel::class)->where('license_id', $licenseId)->where('type', $type)->countAllResults();
    }

    public function testSuspendAndResume(): void
    {
        $id = $this->issueNodeLock();

        $this->service->suspend($id, 5, '미납');
        $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Suspended->value]);

        $this->service->resume($id, 5);
        $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Active->value]);

        $this->assertSame(2, $this->historyCount($id, HistoryType::StatusChange->value));
    }

    public function testInvalidTransitionThrowsAndKeepsState(): void
    {
        $id = $this->issueNodeLock();
        $this->service->terminate($id, 1);

        try {
            $this->service->resume($id, 1); // Terminated → Active 불가
            $this->fail('예외가 발생해야 한다');
        } catch (InvalidStateTransitionException) {
            // 상태 유지
            $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Terminated->value]);
        }
    }

    public function testTerminateThenArchive(): void
    {
        $id = $this->issueNodeLock();
        $this->service->terminate($id, 1);
        $this->service->archive($id, 1);
        $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Archived->value]);
    }

    public function testExtendUpdatesExpireAndRecordsHistory(): void
    {
        $id = $this->issueNodeLock();
        $this->service->extend($id, '2029-01-01', 1);

        $this->seeInDatabase('licenses', ['id' => $id, 'expire_date' => '2029-01-01']);
        $this->assertSame(1, $this->historyCount($id, HistoryType::StatusChange->value));
    }

    public function testReissueNodeLockRotatesKeyRegeneratesFileAndRevokesOld(): void
    {
        $id      = $this->issueNodeLock();
        $oldKey  = $this->service->currentKey($id);
        $this->assertNotNull($oldKey);

        $newKey = $this->service->reissue($id, 1, 'HOST-NEW');

        // 새 키가 현재 키, 이전 키는 폐기 대상
        $this->assertNotSame($oldKey, $newKey);
        $this->assertSame($newKey, $this->service->currentKey($id));
        $this->assertTrue($this->service->isRevokedKey($id, $oldKey));
        $this->assertFalse($this->service->isRevokedKey($id, $newKey));

        // host_id 변경 + 재발급 이력
        $this->seeInDatabase('licenses', ['id' => $id, 'host_id' => 'HOST-NEW']);
        $this->assertSame(1, $this->historyCount($id, HistoryType::Reissue->value));

        // 재생성된 파일이 공개키로 검증되고 새 키/호스트를 담는다
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->find($id);
        $file    = $this->storage->get((string) $license['path']);
        $this->assertNotNull($file);
        $payload = $this->signer->verify($file);
        $this->assertNotNull($payload);
        $this->assertSame('HOST-NEW', $payload['host_id']);
        $this->assertSame($newKey, $payload['license_key']);
    }

    public function testReissueFloatingRotatesKeyWithoutFile(): void
    {
        $issued = $this->issueFloating();
        $newKey = $this->service->reissue($issued['license_id'], 1);

        $this->assertNotSame($issued['license_key'], $newKey);
        $this->assertSame($newKey, $this->service->currentKey($issued['license_id']));
        $this->seeInDatabase('licenses', ['id' => $issued['license_id'], 'path' => null]);
    }

    public function testReissueTerminatedThrows(): void
    {
        $id = $this->issueNodeLock();
        $this->service->terminate($id, 1);

        $this->expectException(InvalidStateTransitionException::class);
        $this->service->reissue($id, 1, 'HOST-NEW');
    }

    public function testTransactionRollsBackOnStorageFailure(): void
    {
        $id = $this->issueNodeLock();

        // 저장 단계에서 실패하는 스토리지 → 재발급 중 예외
        $failingStorage = new class () implements LicenseStorageInterface {
            public function put(string $relativePath, string $contents): string
            {
                throw new RuntimeException('저장 실패(의도적)');
            }

            public function get(string $relativePath): ?string
            {
                return null;
            }

            public function exists(string $relativePath): bool
            {
                return false;
            }
        };
        $failingNodeLock = new NodeLockLicenseService($this->signer, $failingStorage, new LicensePayloadStrategyResolver(), 'test');
        $service         = new LicenseLifecycleService($failingNodeLock);

        try {
            $service->reissue($id, 1, 'HOST-SHOULD-ROLLBACK');
            $this->fail('예외가 발생해야 한다');
        } catch (RuntimeException) {
            // 롤백 검증: host_id 는 원래대로, 재발급 이력 없음
            $this->seeInDatabase('licenses', ['id' => $id, 'host_id' => 'HOST-ORIG']);
            $this->assertSame(0, $this->historyCount($id, HistoryType::Reissue->value));
        }
    }
}
