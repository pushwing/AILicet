<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 상품 버전(product_versions) 모델.
 *
 * 상품 1:N 버전 마스터. 발급 폼에서 상품 선택 시 활성 버전 목록을 제공한다.
 * 이미 발급에 사용된 버전은 하드 삭제 대신 비활성(is_active=0)으로 숨긴다.
 */
final class ProductVersionModel extends Model
{
    protected $table         = 'product_versions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['product_id', 'version', 'is_active'];

    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'version'    => 'required|max_length[30]',
        'is_active'  => 'permit_empty|in_list[0,1]',
    ];

    /**
     * 상품별 활성 버전 목록(발급 폼용).
     *
     * @return list<array{id:int, product_id:int, version:string}>
     */
    public function byProduct(int $productId): array
    {
        /** @var list<array{id:int, product_id:int, version:string}> $rows */
        $rows = $this->select('id, product_id, version')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('version', 'DESC')
            ->findAll();

        return $rows;
    }

    /**
     * 상품별 전체 버전 목록(관리 폼용, 비활성 포함).
     *
     * @return list<array{id:int, product_id:int, version:string, is_active:int}>
     */
    public function allByProduct(int $productId): array
    {
        /** @var list<array{id:int, product_id:int, version:string, is_active:int}> $rows */
        $rows = $this->select('id, product_id, version, is_active')
            ->where('product_id', $productId)
            ->orderBy('version', 'DESC')
            ->findAll();

        return $rows;
    }
}
