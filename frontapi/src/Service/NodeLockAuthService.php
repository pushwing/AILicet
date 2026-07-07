<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LicenseRepository;
use App\Support\RawLogWriter;

/**
 * 노드락 라이센스 인증 유스케이스(온라인 검증).
 *
 * 관리키(license_key)로 라이센스를 찾아 유효성(상태·만료·호스트·키 폐기 여부)을 판정한다.
 */
final class NodeLockAuthService
{
    private const string TYPE_NODELOCK = 'nodelock';

    public function __construct(
        private readonly LicenseRepository $repo,
        private readonly RawLogWriter $log,
    ) {
    }

    /**
     * 라이센스 상세(민감정보 제외).
     *
     * @return array<string, mixed>|null 없으면 null
     */
    public function info(string $licenseKey): ?array
    {
        $id = $this->repo->licenseIdByKey($licenseKey);
        if ($id === null) {
            return null;
        }
        $license = $this->repo->find($id);
        if ($license === null) {
            return null;
        }

        $config  = json_decode((string) ($license['config'] ?? '{}'), true);
        $modules = is_array($config) && isset($config['modules']) && is_array($config['modules']) ? $config['modules'] : [];

        return [
            'product'          => $license['product_name'],
            'product_code'     => $license['product_code'],
            'license_type'     => $license['license_type'],
            'version'          => $license['version'],
            'period_code'      => $license['period_code'],
            'status'           => $license['status'],
            'expire_date'      => $license['expire_date'],
            'support_end_date' => $license['support_end_date'],
            'modules'          => array_values(array_map('strval', $modules)),
        ];
    }

    /**
     * 유효성 검증(노드락). hostId + licenseKey 조합.
     *
     * @return array{valid:bool, reason:string, status:?string, expire_date:?string}
     */
    public function effectiveness(string $licenseKey, string $hostId): array
    {
        $id = $this->repo->licenseIdByKey($licenseKey);
        if ($id === null) {
            return $this->fail('INVALID_LICENSE_KEY');
        }

        // 폐기된(이전) 키 사용 차단 — 최신 키만 유효
        if ($this->repo->currentKey($id) !== $licenseKey) {
            return $this->fail('REVOKED_KEY');
        }

        $license = $this->repo->find($id);
        if ($license === null) {
            return $this->fail('NOT_FOUND');
        }

        $status = (string) $license['status'];
        $expire = $license['expire_date'] !== null ? (string) $license['expire_date'] : null;

        if ((string) $license['license_type'] !== self::TYPE_NODELOCK) {
            return $this->fail('NOT_NODELOCK', $status, $expire);
        }
        if ($status !== 'active') {
            return $this->fail('INACTIVE', $status, $expire);
        }
        if ($expire !== null && $expire < gmdate('Y-m-d')) {
            return $this->fail('EXPIRED', $status, $expire);
        }
        // 노드락 호스트 바인딩 검증
        if ((string) ($license['host_id'] ?? '') !== $hostId) {
            return $this->fail('HOST_MISMATCH', $status, $expire);
        }

        return ['valid' => true, 'reason' => 'OK', 'status' => $status, 'expire_date' => $expire];
    }

    /**
     * 사용정보 수집 — 원시 로그로 적재.
     *
     * @param array<string, mixed> $payload
     */
    public function bypass(string $licenseKey, string $hostId, array $payload, string $ip): void
    {
        $this->log->append([
            'ts'          => gmdate('c'),
            'ip'          => $ip,
            'license_key' => $licenseKey,
            'host_id'     => $hostId,
            'payload'     => $payload,
        ]);
    }

    /**
     * @return array{valid:bool, reason:string, status:?string, expire_date:?string}
     */
    private function fail(string $reason, ?string $status = null, ?string $expire = null): array
    {
        return ['valid' => false, 'reason' => $reason, 'status' => $status, 'expire_date' => $expire];
    }
}
