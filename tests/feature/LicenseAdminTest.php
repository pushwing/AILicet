<?php

declare(strict_types=1);

use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\UserRole;
use App\Libraries\LicenseSigner;
use App\Licensing\Storage\LocalLicenseStorage;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Models\ProductModuleModel;
use App\Services\NodeLockLicenseService;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 라이센스 관리 UI(컨트롤러) feature 테스트.
 *
 * @internal
 */
final class LicenseAdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private string $tmpDir;
    private LicenseSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $pair         = LicenseSigner::generateKeypair();
        $this->signer = new LicenseSigner(
            base64_decode($pair['secretKey'], true) ?: '',
            base64_decode($pair['publicKey'], true) ?: '',
        );
        $this->tmpDir = WRITEPATH . 'licenses-admin-test';
        $storage      = new LocalLicenseStorage($this->tmpDir);

        // 발급 엔진·저장소를 테스트용으로 주입
        Services::injectMock('licenseStorage', $storage);
        Services::injectMock('nodeLockLicenseService', new NodeLockLicenseService(
            $this->signer,
            $storage,
            new LicensePayloadStrategyResolver(),
            'test',
        ));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            exec('rm -rf ' . escapeshellarg($this->tmpDir));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function operator(): array
    {
        return ['authUser' => ['id' => 1, 'name' => '관리자', 'role' => UserRole::Operator->value]];
    }

    private function seedProduct(string $type = 'nodelock'): int
    {
        $id = (int) model(ProductModel::class)->insert([
            'product_code' => 'PT001', 'name' => 'tES LAB', 'license_type' => $type, 'version' => '3.0', 'is_active' => 1,
        ], true);
        $moduleId = (int) model(\App\Models\ModuleModel::class)->insert([
            'code' => 'MD001', 'name' => '정량분석', 'is_active' => 1,
        ], true);
        model(ProductModuleModel::class)->insert([
            'product_id' => $id, 'module_id' => $moduleId, 'code' => 'MD001', 'name' => '정량분석',
        ]);

        return $id;
    }

    public function testIndexRendersForOperator(): void
    {
        $result = $this->withSession($this->operator())->get('admin/licenses');
        $result->assertStatus(200);
        $result->assertSee('라이센스 관리');
        $result->assertSeeElement('#licenseGrid');
    }

    public function testNonOperatorForbidden(): void
    {
        $result = $this->withSession(['authUser' => ['id' => 9, 'role' => UserRole::Member->value]])->get('admin/licenses');
        $result->assertStatus(403);
    }

    public function testProductModulesEndpoint(): void
    {
        $pid    = $this->seedProduct();
        $result = $this->withSession($this->operator())->get("admin/licenses/product-modules/{$pid}");

        $json = json_decode($result->getJSON() ?? '', true);
        $this->assertSame('MD001', $json['data'][0]['code']);
    }

    public function testIssueNodeLockLicense(): void
    {
        $pid = $this->seedProduct('nodelock');

        $result = $this->withSession($this->operator())->post('admin/licenses', [
            'license_type'     => 'nodelock',
            'product_id'       => $pid,
            'period_code'      => 'period',
            'host_id'          => 'HOST-UI-001',
            'expire_date'      => '2027-12-31',
            'support_end_date' => '2028-06-30',
            'modules'          => ['MD001'],
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('licenses', [
            'product_id'   => $pid,
            'license_type' => LicenseType::NodeLock->value,
            'host_id'      => 'HOST-UI-001',
            'status'       => LicenseStatus::Active->value,
        ]);
    }

    public function testIssueFloatingLicense(): void
    {
        $pid = $this->seedProduct('floating');

        $result = $this->withSession($this->operator())->post('admin/licenses', [
            'license_type'  => 'floating',
            'product_id'    => $pid,
            'period_code'   => 'perpetual_credit',
            'activate_term' => '24',
            'check_term'    => '30',
            'limit_credit'  => '500',
        ]);

        $result->assertRedirect();
        $this->seeInDatabase('licenses', ['product_id' => $pid, 'license_type' => LicenseType::Floating->value, 'path' => null]);
    }

    public function testShowAndStatusActions(): void
    {
        $pid = $this->seedProduct('nodelock');
        // 발급
        $this->withSession($this->operator())->post('admin/licenses', [
            'license_type' => 'nodelock', 'product_id' => $pid, 'period_code' => 'period',
            'host_id' => 'HOST-A', 'expire_date' => '2027-12-31', 'support_end_date' => '2028-06-30', 'modules' => ['MD001'],
        ]);
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->orderBy('id', 'DESC')->first();
        $id      = (int) $license['id'];

        // 상세 — 상품 정보·모듈 카드(이슈 #61)
        $show = $this->withSession($this->operator())->get("admin/licenses/{$id}");
        $show->assertStatus(200);
        $show->assertSee('라이센스 정보');
        $show->assertSee('상품 정보');
        $show->assertSee('제품군');
        $show->assertSee('모듈');
        $show->assertSee('정량분석'); // 발급된 모듈명 표기

        // 정지 → 종료
        $this->withSession($this->operator())->post("admin/licenses/{$id}/suspend", ['reason' => '미납']);
        $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Suspended->value]);

        $this->withSession($this->operator())->post("admin/licenses/{$id}/resume");
        $this->withSession($this->operator())->post("admin/licenses/{$id}/terminate");
        $this->seeInDatabase('licenses', ['id' => $id, 'status' => LicenseStatus::Terminated->value]);
    }

    public function testDownloadNodeLockFile(): void
    {
        $pid = $this->seedProduct('nodelock');
        $this->withSession($this->operator())->post('admin/licenses', [
            'license_type' => 'nodelock', 'product_id' => $pid, 'period_code' => 'period',
            'host_id' => 'HOST-DL', 'expire_date' => '2027-12-31', 'support_end_date' => '2028-06-30', 'modules' => ['MD001'],
        ]);
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->orderBy('id', 'DESC')->first();

        $result = $this->withSession($this->operator())->get('admin/licenses/' . $license['id'] . '/download');
        $result->assertStatus(200);
        $result->assertHeader('Content-Disposition');

        // 다운로드된 파일이 Ed25519 서명 봉투이고 발급 정보를 담는다.
        // (테스트 하네스가 octet-stream 을 HTML 로 감싸므로 JSON 구간만 추출)
        $body     = $result->getBody() ?? '';
        $start    = (int) strpos($body, '{');
        $json     = substr($body, $start, (int) strrpos($body, '}') - $start + 1);
        $envelope = json_decode($json, true);
        $this->assertIsArray($envelope);
        $this->assertSame('Ed25519', $envelope['alg']);
        $payload = json_decode(base64_decode((string) $envelope['data'], true) ?: '', true);
        $this->assertSame('HOST-DL', $payload['host_id']);
    }

    public function testReissueGeneratesNewKey(): void
    {
        $pid = $this->seedProduct('nodelock');
        $this->withSession($this->operator())->post('admin/licenses', [
            'license_type' => 'nodelock', 'product_id' => $pid, 'period_code' => 'period',
            'host_id' => 'HOST-R', 'expire_date' => '2027-12-31', 'support_end_date' => '2028-06-30', 'modules' => ['MD001'],
        ]);
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->orderBy('id', 'DESC')->first();
        $id      = (int) $license['id'];

        $this->withSession($this->operator())->post("admin/licenses/{$id}/reissue", ['host_id' => 'HOST-R2']);

        $this->seeInDatabase('licenses', ['id' => $id, 'host_id' => 'HOST-R2']);
        $this->assertSame(1, model(\App\Models\LicenseHistoryModel::class)
            ->where('license_id', $id)->where('type', HistoryType::Reissue->value)->countAllResults());
    }
}
