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
    protected $allowedFields = ['customer_id', 'email', 'subject', 'content', 'status', 'reply', 'replied_at'];

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
}
