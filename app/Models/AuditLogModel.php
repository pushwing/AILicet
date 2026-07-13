<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 감사 로그(audit_logs) 모델 — 부정사용 등 이벤트 기록. created_at 만 사용.
 *
 * ai_* 컬럼은 감사 로그 AI 설명 배치가 사후 UPDATE 로 채운다(ai_processed_at 이 처리 마커).
 */
final class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['license_id', 'event_type', 'host_id', 'client_host_id', 'license_key', 'ip', 'detail', 'ai_explanation', 'ai_attempts', 'ai_processed_at'];

    /** 특정 이벤트가 이미 기록되었는지(중복 방지). */
    public function exists(string $eventType, string $licenseKey, ?string $clientHostId): bool
    {
        $builder = $this->where('event_type', $eventType)->where('license_key', $licenseKey);
        if ($clientHostId !== null) {
            $builder->where('client_host_id', $clientHostId);
        }

        return $builder->countAllResults() > 0;
    }

    /** 특정 이벤트가 지정 시각 이후 이미 기록되었는지(일자 단위 중복 방지 — 예: 같은 날 재실행). */
    public function existsSince(string $eventType, string $licenseKey, string $sinceDatetime): bool
    {
        return $this->where('event_type', $eventType)
            ->where('license_key', $licenseKey)
            ->where('created_at >=', $sinceDatetime)
            ->countAllResults() > 0;
    }

    /**
     * 아직 AI 설명이 생성되지 않은(미처리) 감사 로그를 오래된 순으로 조회한다.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingAiExplanation(int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->where('ai_processed_at', null)
            ->orderBy('id', 'ASC')
            ->findAll(max(1, $limit));

        return $rows;
    }
}
