<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerType;
use CodeIgniter\Model;

/**
 * 회원(customers) 모델 — 대행사/고객.
 */
final class CustomerModel extends Model
{
    protected $table          = 'customers';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'customer_type', 'user_id', 'parent_id', 'company_name', 'name', 'email', 'phone', 'is_active',
        'verify_token', 'email_verified_at',
    ];

    protected $validationRules = [];

    public function __construct()
    {
        parent::__construct();

        $types = implode(',', array_column(CustomerType::cases(), 'value'));

        $this->validationRules = [
            'id'            => 'permit_empty|is_natural_no_zero',
            'customer_type' => "required|in_list[{$types}]",
            'user_id'       => 'permit_empty|is_natural_no_zero',
            'parent_id'     => 'permit_empty|is_natural_no_zero',
            'company_name'  => 'required|max_length[100]',
            'name'          => 'required|max_length[50]',
            'email'         => 'required|valid_email|max_length[150]|is_unique[customers.email,id,{id}]',
            'phone'         => 'permit_empty|max_length[30]',
            'is_active'     => 'permit_empty|in_list[0,1]',
        ];
    }

    /**
     * 활성 대행사 목록(고객의 소속 선택용).
     *
     * @return list<array{id:int, company_name:string}>
     */
    public function activeAgencies(): array
    {
        /** @var list<array{id:int, company_name:string}> $rows */
        $rows = $this->select('id, company_name')
            ->where('customer_type', CustomerType::Agency->value)
            ->where('is_active', 1)
            ->orderBy('company_name', 'ASC')
            ->findAll();

        return $rows;
    }
}
