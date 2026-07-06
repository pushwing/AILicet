<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\NodeLockIssueRequest;
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Libraries\LicenseSigner;
use App\Licensing\LicenseBuildContext;
use App\Licensing\Storage\LicenseStorageInterface;
use App\Licensing\Strategy\LicensePayloadStrategyResolver;
use App\Models\CustomerLicenseModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use RuntimeException;

/**
 * 노드락 라이센스 발급 엔진.
 *
 * 흐름: 상품 조회 → 식별자 생성 → license 레코드 생성 → 페이로드 빌드(전략) →
 * Ed25519 서명 → 저장소 저장 → path 기록 → 이력·고객매핑. 전 과정 트랜잭션.
 *
 * @phpstan-type IssueResult array{license_id:int, path:string, license_key:string, license_sn:string, file:string}
 */
final class NodeLockLicenseService
{
    public function __construct(
        private readonly LicenseSigner $signer,
        private readonly LicenseStorageInterface $storage,
        private readonly LicensePayloadStrategyResolver $resolver,
        private readonly string $deployTarget = 'dev',
    ) {
    }

    /**
     * 노드락 라이센스를 발급한다.
     *
     * @return IssueResult
     *
     * @throws RuntimeException 상품 없음·저장 실패
     */
    public function issue(NodeLockIssueRequest $request): array
    {
        /** @var array<string, mixed>|null $product */
        $product = model(ProductModel::class)->find($request->productId);
        if ($product === null) {
            throw new RuntimeException('상품을 찾을 수 없습니다.');
        }

        $licenses = model(LicenseModel::class);
        $history  = model(LicenseHistoryModel::class);
        $db       = db_connect();

        $licenseSn  = $this->makeLicenseSn((string) ($product['product_code'] ?? 'PT000'));
        $licenseKey = $this->makeLicenseKey();
        $issueDate  = date('Y-m-d');

        $db->transStart();

        $licenseId = (int) $licenses->insert([
            'product_id'       => $request->productId,
            'license_type'     => LicenseType::NodeLock->value,
            'period_code'      => $request->periodCode,
            'status'           => LicenseStatus::Active->value,
            'version'          => $request->version ?? ($product['version'] ?? null),
            'host_id'          => $request->hostId,
            'expire_date'      => $request->expireDate,
            'support_end_date' => $request->supportEndDate,
            'is_trial'         => $request->isTrial ? 1 : 0,
            'config'           => $this->encodeConfig($request),
            'issued_by'        => $request->issuedBy,
            'issue_date'       => $issueDate,
        ], true);

        if ($licenseId === 0) {
            throw new RuntimeException('라이센스 레코드 생성에 실패했습니다.');
        }

        // 페이로드 빌드(전략) → 서명 → 저장
        $context  = new LicenseBuildContext($request, $product, $licenseSn, $licenseKey, $issueDate);
        $payload  = $this->resolver->resolve($product)->build($context);
        $file     = $this->signer->sign($payload);
        $relative = sprintf('%s/%d/%d/NLicense.lic', $this->deployTarget, $request->issuedBy, $licenseId);
        $path     = $this->storage->put($relative, $file);

        $licenses->update($licenseId, ['path' => $path]);

        // 발급 이력
        $history->insert([
            'license_id'  => $licenseId,
            'type'        => HistoryType::Issue->value,
            'host_id'     => $request->hostId,
            'license_sn'  => $licenseSn,
            'license_key' => $licenseKey,
            'contents'    => '노드락 라이센스 최초 발급',
            'created_by'  => $request->issuedBy,
        ]);

        // 고객 매핑(선택)
        if ($request->customerId !== null) {
            model(CustomerLicenseModel::class)->insert([
                'customer_id' => $request->customerId,
                'license_id'  => $licenseId,
                'created_by'  => $request->issuedBy,
            ]);
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('라이센스 발급에 실패했습니다.');
        }

        return [
            'license_id'  => $licenseId,
            'path'        => $path,
            'license_key' => $licenseKey,
            'license_sn'  => $licenseSn,
            'file'        => $file,
        ];
    }

    /** 자재코드: 상품코드 + yymmdd + 2자리 일련. */
    private function makeLicenseSn(string $productCode): string
    {
        return $productCode . date('ymd') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    /** 관리키: 랜덤 32 hex + His(6). */
    private function makeLicenseKey(): string
    {
        return bin2hex(random_bytes(16)) . date('His');
    }

    private function encodeConfig(NodeLockIssueRequest $request): string
    {
        $config = ['modules' => $request->modules, 'limits' => $request->limits];
        $json   = json_encode($config, JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }
}
