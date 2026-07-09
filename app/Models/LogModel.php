<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * logs — 큐 소비자가 가공 로그를 저장하는 모델. created_at 만 사용.
 *
 * ai_* 컬럼은 ai:classify-logs 배치가 사후 UPDATE 로 채운다(ai_processed_at 이 처리 마커).
 */
final class LogModel extends Model
{
    protected $table         = 'logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['level', 'source', 'message', 'context', 'client_ip', 'user_id', 'logged_at', 'ai_category', 'ai_summary', 'ai_attempts', 'ai_processed_at'];

    /**
     * 아직 AI 분류가 되지 않은(미처리) 로그를 오래된 순으로 조회한다.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingAiClassification(int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->where('ai_processed_at', null)
            ->orderBy('id', 'ASC')
            ->findAll(max(1, $limit));

        return $rows;
    }
}
