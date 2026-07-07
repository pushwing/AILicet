<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\LicenseType;
use App\Models\AuditLogModel;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;

/**
 * 부정사용 감지 — 사용 로그(bypass) 엔트리를 검사해 폐기키 사용·호스트 불일치를 audit_logs 에 기록.
 *
 * frontApi 가 남긴 원시 사용 로그(license_key/host_id)를 입력으로 받아 DB 상태와 교차 검증한다.
 */
final class AbuseDetectionService
{
    public function __construct(
        private readonly ?LicenseLifecycleService $lifecycle = null,
    ) {
    }

    /**
     * 로그 엔트리들을 검사해 부정사용을 감지·기록한다.
     *
     * @param list<array{license_key?:string, host_id?:string, ip?:string}> $entries
     *
     * @return list<array{event_type:string, license_key:string, host_id:string}> 새로 감지된 항목
     */
    public function detect(array $entries): array
    {
        $lifecycle = $this->lifecycle ?? service('licenseLifecycleService');
        $audit     = model(AuditLogModel::class);
        $detected  = [];

        foreach ($entries as $entry) {
            $key  = trim((string) ($entry['license_key'] ?? ''));
            $host = trim((string) ($entry['host_id'] ?? ''));
            if ($key === '') {
                continue;
            }

            $licenseId = $this->licenseIdByKey($key);
            if ($licenseId === null) {
                continue; // 알 수 없는 키는 여기서 다루지 않음
            }

            // 1) 재발급으로 폐기된 이전 키 사용
            if ($lifecycle->isRevokedKey($licenseId, $key)) {
                $detected[] = $this->record($audit, AuditEventType::RevokedKeyUse, $licenseId, $key, $host, $entry);
                continue;
            }

            // 2) 노드락 호스트 불일치
            $license = model(LicenseModel::class)->find($licenseId);
            if ($license !== null
                && (string) $license['license_type'] === LicenseType::NodeLock->value
                && $host !== '' && (string) ($license['host_id'] ?? '') !== $host
            ) {
                $detected[] = $this->record($audit, AuditEventType::IllegalHost, $licenseId, $key, $host, $entry);
            }
        }

        return array_values(array_filter($detected));
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{event_type:string, license_key:string, host_id:string}|null 신규 기록이면 반환, 중복이면 null
     */
    private function record(AuditLogModel $audit, AuditEventType $type, int $licenseId, string $key, string $host, array $entry): ?array
    {
        if ($audit->exists($type->value, $key, $host !== '' ? $host : null)) {
            return null; // 이미 기록됨
        }

        $audit->insert([
            'license_id'     => $licenseId,
            'event_type'     => $type->value,
            'client_host_id' => $host !== '' ? $host : null,
            'license_key'    => $key,
            'ip'             => isset($entry['ip']) ? (string) $entry['ip'] : null,
            'detail'         => json_encode(['reason' => $type->label()], JSON_UNESCAPED_UNICODE) ?: null,
        ]);

        return ['event_type' => $type->value, 'license_key' => $key, 'host_id' => $host];
    }

    private function licenseIdByKey(string $key): ?int
    {
        /** @var array{license_id:int}|null $row */
        $row = model(LicenseHistoryModel::class)
            ->select('license_id')
            ->where('license_key', $key)
            ->whereIn('type', ['issue', 'reissue'])
            ->orderBy('id', 'DESC')
            ->first();

        return $row !== null ? (int) $row['license_id'] : null;
    }
}
