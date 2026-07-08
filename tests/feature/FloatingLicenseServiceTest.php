<?php

declare(strict_types=1);

use App\DTO\FloatingIssueRequest;
use App\Enums\HistoryType;
use App\Enums\LicenseType;
use App\Libraries\JwtLibrary;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use App\Services\FloatingLicenseService;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * 플로팅 라이센스 발급·검증 준비 DB 통합 테스트.
 *
 * @internal
 */
final class FloatingLicenseServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = 'App';

    private const string SECRET = 'floating-test-secret-0123456789-ab';

    private FloatingLicenseService $service;
    private JwtLibrary $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtLibrary(self::SECRET);
        Services::injectMock('licenseToken', $this->jwt);
        $this->service = new FloatingLicenseService();
    }

    private function seedProduct(): int
    {
        return (int) model(ProductModel::class)->insert([
            'product_code' => 'PT002', 'name' => 'AQUA', 'license_type' => 'floating', 'is_active' => 1,
        ], true);
    }

    private function issue(array $overrides = []): array
    {
        $base = [
            'product_id'   => $this->seedProduct(),
            'period_code'  => 'perpetual_credit',
            'issued_by'    => 3,
            'activate_term' => 24,
            'check_term'   => 30,
            'limits'       => ['credit' => 500],
        ];

        return $this->service->issue(FloatingIssueRequest::fromArray(array_merge($base, $overrides)));
    }

    public function testIssueReturnsKeyAndCreatesFloatingLicense(): void
    {
        $result = $this->issue();

        $this->assertMatchesRegularExpression('/^[0-9A-F]{8}(-[0-9A-F]{8}){3}$/', $result['license_key']);
        $this->seeInDatabase('licenses', [
            'id'            => $result['license_id'],
            'license_type'  => LicenseType::Floating->value,
            'activate_term' => 24,
            'check_term'    => 30,
            'path'          => null, // 파일 없음(키-온리)
        ]);
        $this->seeInDatabase('license_history', [
            'license_id'  => $result['license_id'],
            'type'        => HistoryType::Issue->value,
            'license_key' => $result['license_key'],
        ]);
    }

    public function testFindByKeyReturnsLicense(): void
    {
        $result = $this->issue();

        $found = $this->service->findByKey($result['license_key']);
        $this->assertNotNull($found);
        $this->assertSame($result['license_id'], (int) $found['id']);
    }

    public function testActivationTokenMintAndVerify(): void
    {
        $token = $this->service->mintActivationToken(42, 'HOST-A', 30);

        $verified = $this->service->verifyActivationToken($token);
        $this->assertNotNull($verified);
        $this->assertSame(42, $verified['lic']);
        $this->assertSame('HOST-A', $verified['host']);
    }

    public function testExpiredActivationTokenReturnsNull(): void
    {
        // scope 는 맞지만 만료된 토큰
        $expired = $this->jwt->encode(['scope' => 'floating_activation', 'lic' => 1, 'host' => 'h'], -10);
        $this->assertNull($this->service->verifyActivationToken($expired));
    }

    public function testWrongScopeTokenRejected(): void
    {
        $token = $this->jwt->encode(['scope' => 'other', 'lic' => 1, 'host' => 'h'], 600);
        $this->assertNull($this->service->verifyActivationToken($token));
    }

    public function testEffectivenessSnapshotExposesRemainingCredit(): void
    {
        $result  = $this->issue(['limits' => ['credit' => 500]]);
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->find($result['license_id']);

        $snap = $this->service->effectivenessSnapshot($license);
        $this->assertTrue($snap['valid']);
        $this->assertSame(500, $snap['remaining']['credit']);
        $this->assertSame(30, $snap['check_term']);
    }

    public function testExpiredLicenseSnapshotIsInvalid(): void
    {
        $result = $this->issue(['expire_date' => '2020-01-01']);
        /** @var array<string,mixed> $license */
        $license = model(LicenseModel::class)->find($result['license_id']);

        $snap = $this->service->effectivenessSnapshot($license);
        $this->assertFalse($snap['valid']);
    }

    public function testUnknownProductThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->issue(FloatingIssueRequest::fromArray([
            'product_id' => 999999, 'period_code' => 'period', 'issued_by' => 1,
        ]));
    }
}
