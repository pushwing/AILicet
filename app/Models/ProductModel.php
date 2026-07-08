<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseType;
use App\Enums\PeriodCode;
use CodeIgniter\Model;

/**
 * 상품(products) 모델.
 *
 * @phpstan-type ProductRow array{
 *     id:int, product_code:string, name:string, description:?string, product_family:?string,
 *     license_type:string, version:?string, period_code:?string, is_active:int
 * }
 */
final class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'product_code', 'name', 'description', 'product_family', 'license_type',
        'version', 'period_code', 'is_active',
    ];

    protected $validationRules = [];

    public function __construct()
    {
        parent::__construct();

        $licenseTypes = implode(',', array_column(LicenseType::cases(), 'value'));
        $periodCodes  = implode(',', array_column(PeriodCode::cases(), 'value'));

        $this->validationRules = [
            'id'             => 'permit_empty|is_natural_no_zero', // is_unique {id} 플레이스홀더 요건
            'product_code'   => "required|max_length[30]|is_unique[products.product_code,id,{id}]",
            'name'           => 'required|max_length[100]',
            'description'    => 'permit_empty|max_length[20000]',
            'product_family' => 'permit_empty|max_length[50]',
            'license_type'   => "required|in_list[{$licenseTypes}]",
            'version'        => 'permit_empty|max_length[30]',
            'period_code'    => "permit_empty|in_list[{$periodCodes}]",
            'is_active'      => 'permit_empty|in_list[0,1]',
        ];
    }

    /**
     * 발급 폼 등에서 쓰는 활성 상품 목록(간략 필드).
     *
     * @return list<array{id:int, product_code:string, name:string, license_type:string, version:?string}>
     */
    public function activeForSelect(): array
    {
        /** @var list<array{id:int, product_code:string, name:string, license_type:string, version:?string}> $rows */
        $rows = $this->select('id, product_code, name, license_type, version')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $rows;
    }
}
