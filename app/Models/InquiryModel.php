<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 고객센터 문의(inquiries) 모델.
 */
final class InquiryModel extends Model
{
    protected $table         = 'inquiries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'customer_id', 'email', 'subject', 'content', 'status', 'reply', 'replied_at',
        'ai_category', 'ai_draft_reply', 'ai_attempts', 'ai_processed_at',
    ];

    protected $validationRules = [
        'email'   => 'required|valid_email|max_length[150]',
        'subject' => 'required|max_length[200]',
        'content' => 'required',
    ];

    /**
     * 고객별 문의 목록(최신순).
     *
     * @return list<array<string, mixed>>
     */
    public function byCustomer(int $customerId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->where('customer_id', $customerId)->orderBy('id', 'DESC')->findAll();

        return $rows;
    }

    /**
     * AI 미처리(ai_processed_at IS NULL) 문의를 오래된 순으로 최대 $limit 건 조회한다.
     * ai:draft-inquiries 배치가 이 큐(=미처리 행)를 소비한다(LogModel 과 대칭).
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
