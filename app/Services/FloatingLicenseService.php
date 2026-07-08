<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\FloatingIssueRequest;
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;
use App\Models\CustomerLicenseModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use App\Models\ProductModel;
use RuntimeException;

/**
 * 플로팅(온라인) 라이센스 발급·검증 준비 서비스.
 *
 * 설계 3-4: 상시 온라인이므로 파일 없이 관리키만 배포한다. 서버가 진실의 원천.
 * - 발급: 관리키 생성 + license/history 레코드(파일·서명 없음)
 * - activation 캐시 토큰: 최초 활성화 시 checkTerm 동안 유효한 서명 JWT(순단 대비)
 * - effectiveness 준비: 유효성 스냅샷(잔여 카운트/크레딧 포함) 제공 → frontApi 소비
 *
 * @phpstan-type IssueResult array{license_id:int, license_key:string, license_sn:string}
 * @phpstan-type Snapshot array{valid:bool, status:string, expire_date:?string, activate_term:int, check_term:int, remaining:array<string,int>}
 */
final class FloatingLicenseService
{
    private const string TOKEN_SCOPE = 'floating_activation';

    /**
     * 플로팅 라이센스를 발급하고 관리키를 반환한다.
     *
     * @return IssueResult
     *
     * @throws RuntimeException 상품 없음·저장 실패
     */
    public function issue(FloatingIssueRequest $request): array
    {
        /** @var array<string, mixed>|null $product */
        $product = model(ProductModel::class)->find($request->productId);
        if ($product === null) {
            throw new RuntimeException('상품을 찾을 수 없습니다.');
        }

        $licenses   = model(LicenseModel::class);
        $history    = model(LicenseHistoryModel::class);
        $licenseKey = $this->makeLicenseKey();
        $licenseSn  = $this->makeLicenseSn((string) ($product['product_code'] ?? 'PT000'));
        $issueDate  = date('Y-m-d');

        $db = db_connect();
        $db->transStart();

        $licenseId = (int) $licenses->insert([
            'product_id'       => $request->productId,
            'license_type'     => LicenseType::Floating->value,
            'period_code'      => $request->periodCode,
            'status'           => LicenseStatus::Active->value,
            'version'          => $request->version ?? ($product['version'] ?? null),
            'expire_date'      => $request->expireDate,
            'support_end_date' => $request->supportEndDate,
            'activate_term'    => $request->activateTerm,
            'check_term'       => $request->checkTerm,
            'is_trial'         => $request->isTrial ? 1 : 0,
            'config'           => $this->encodeConfig($request),
            'issued_by'        => $request->issuedBy,
            'issue_date'       => $issueDate,
        ], true);

        if ($licenseId === 0) {
            throw new RuntimeException('라이센스 레코드 생성에 실패했습니다.');
        }

        $history->insert([
            'license_id'  => $licenseId,
            'type'        => HistoryType::Issue->value,
            'license_key' => $licenseKey,
            'license_sn'  => $licenseSn,
            'contents'    => '플로팅 라이센스 발급(키 배포)',
            'created_by'  => $request->issuedBy,
        ]);

        if ($request->customerId !== null) {
            model(CustomerLicenseModel::class)->insert([
                'customer_id' => $request->customerId,
                'license_id'  => $licenseId,
                'created_by'  => $request->issuedBy,
            ]);
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('플로팅 라이센스 발급에 실패했습니다.');
        }

        return ['license_id' => $licenseId, 'license_key' => $licenseKey, 'license_sn' => $licenseSn];
    }

    /**
     * 관리키로 라이센스를 조회한다(플로팅 한정).
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $licenseKey): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(LicenseModel::class)
            ->select('licenses.*')
            ->join('license_history', 'license_history.license_id = licenses.id')
            ->where('license_history.license_key', $licenseKey)
            ->where('licenses.license_type', LicenseType::Floating->value)
            ->first();

        return $row;
    }

    /**
     * 최초 활성화 시 발급하는 짧은 수명 캐시 토큰(순단 대비).
     * checkTerm(분) 동안 유효한 서명 JWT.
     */
    public function mintActivationToken(int $licenseId, string $hostId, int $checkTermMinutes): string
    {
        return service('licenseToken')->encode([
            'scope' => self::TOKEN_SCOPE,
            'lic'   => $licenseId,
            'host'  => $hostId,
        ], max(60, $checkTermMinutes * 60));
    }

    /**
     * 활성화 캐시 토큰을 검증한다.
     *
     * @return array{lic:int, host:string}|null 유효하면 라이센스·호스트, 아니면 null
     */
    public function verifyActivationToken(string $token): ?array
    {
        try {
            $claims = service('licenseToken')->decode($token);
        } catch (InvalidTokenException | TokenExpiredException) {
            return null;
        }

        if (($claims['scope'] ?? null) !== self::TOKEN_SCOPE) {
            return null;
        }

        return ['lic' => (int) ($claims['lic'] ?? 0), 'host' => (string) ($claims['host'] ?? '')];
    }

    /**
     * 유효성 스냅샷 — effectiveness API 가 소비. 잔여 카운트/크레딧 포함.
     *
     * 사용량 차감(analysis 로그)은 frontApi(#16)에서 반영되며, 여기서는 정책상 한도를 노출한다.
     *
     * @param array<string, mixed> $license
     *
     * @return Snapshot
     */
    public function effectivenessSnapshot(array $license): array
    {
        $status  = (string) ($license['status'] ?? '');
        $expire  = $license['expire_date'] !== null ? (string) $license['expire_date'] : null;
        $notExp  = $expire === null || $expire >= date('Y-m-d');
        $valid   = $status === LicenseStatus::Active->value && $notExp;

        $config  = json_decode((string) ($license['config'] ?? '{}'), true);
        $limits  = is_array($config) && isset($config['limits']) && is_array($config['limits']) ? $config['limits'] : [];

        return [
            'valid'         => $valid,
            'status'        => $status,
            'expire_date'   => $expire,
            'activate_term' => (int) ($license['activate_term'] ?? 24),
            'check_term'    => (int) ($license['check_term'] ?? 30),
            'remaining'     => array_map('intval', $limits),
        ];
    }

    /** 관리키: 8자리 4그룹 대문자 hex (배포용). */
    private function makeLicenseKey(): string
    {
        $raw = strtoupper(bin2hex(random_bytes(16))); // 32 hex
        return implode('-', str_split($raw, 8));      // XXXXXXXX-XXXXXXXX-...
    }

    /** 자재코드: 상품코드 + yymmdd + 2자리 일련. */
    private function makeLicenseSn(string $productCode): string
    {
        return $productCode . date('ymd') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }

    private function encodeConfig(FloatingIssueRequest $request): string
    {
        $config = ['modules' => $request->modules, 'limits' => $request->limits];
        $json   = json_encode($config, JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }
}
