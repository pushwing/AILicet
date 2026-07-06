<?php

declare(strict_types=1);

namespace App\Licensing\Strategy;

use App\Enums\LicenseType;
use App\Licensing\LicenseBuildContext;

/**
 * 표준 노드락 페이로드 전략 (기본).
 *
 * 신규 상품은 모두 동일한 서명 페이로드 구조를 사용한다.
 * 예외적인 레거시 상품/버전이 생기면 별도 전략을 추가해 resolver 우선순위로 매칭한다.
 */
final class StandardNodeLockStrategy implements LicensePayloadStrategy
{
    private const string MAGIC   = 'AILICET';
    private const int PAYLOAD_V  = 1;

    public function supports(array $product): bool
    {
        return true; // 폴백 기본 전략
    }

    public function build(LicenseBuildContext $ctx): array
    {
        $req = $ctx->request;

        return [
            'magic'            => self::MAGIC,
            'payload_version'  => self::PAYLOAD_V,
            'license_type'     => LicenseType::NodeLock->value,
            'product_code'     => (string) ($ctx->product['product_code'] ?? ''),
            'product_name'     => (string) ($ctx->product['name'] ?? ''),
            'product_family'   => $ctx->product['product_family'] ?? null,
            'host_id'          => $req->hostId,
            'system_id_check'  => true,
            'version'          => $req->version ?? ($ctx->product['version'] ?? null),
            'period_code'      => $req->periodCode,
            'expire_date'      => $req->expireDate,
            'support_end_date' => $req->supportEndDate,
            'modules'          => $req->modules,
            'company_name'     => $req->companyName,
            'charge_name'      => $req->chargeName,
            'charge_phone'     => $req->chargePhone,
            'charge_email'     => $req->chargeEmail,
            'license_sn'       => $ctx->licenseSn,
            'license_key'      => $ctx->licenseKey,
            'issue_date'       => $ctx->issueDate,
            'is_trial'         => $req->isTrial,
            'limits'           => $req->limits,
        ];
    }
}
