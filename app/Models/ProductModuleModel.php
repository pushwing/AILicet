<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 상품 모듈(product_modules) 모델.
 */
final class ProductModuleModel extends Model
{
    protected $table         = 'product_modules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['product_id', 'module_id', 'code', 'name'];

    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'module_id'  => 'required|is_natural_no_zero',
        'code'       => 'required|max_length[30]',
        'name'       => 'required|max_length[100]',
    ];

    /**
     * 상품별 모듈 목록(code/name 스냅샷).
     *
     * @return list<array{id:int, product_id:int, code:string, name:string}>
     */
    public function byProduct(int $productId): array
    {
        /** @var list<array{id:int, product_id:int, code:string, name:string}> $rows */
        $rows = $this->select('id, product_id, code, name')
            ->where('product_id', $productId)
            ->orderBy('code', 'ASC')
            ->findAll();

        return $rows;
    }
}
