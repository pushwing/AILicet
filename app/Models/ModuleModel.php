<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 모듈 마스터(modules) 모델.
 *
 * 상품과 독립적인 모듈 카탈로그. code 는 전역 유니크.
 *
 * @phpstan-type ModuleRow array{id:int, code:string, name:string, is_active:int}
 */
final class ModuleModel extends Model
{
    protected $table         = 'modules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'name', 'is_active'];

    protected $validationRules = [
        'id'        => 'permit_empty|is_natural_no_zero',                             // is_unique {id} 플레이스홀더 요건
        'code'      => 'required|alpha_dash|max_length[30]|is_unique[modules.code,id,{id}]', // 영숫자·_·- 만 허용(XSS·데이터 정합)
        'name'      => 'required|max_length[100]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * 관리 화면용 전체 목록(사용 상품 수 포함).
     *
     * @return list<array{id:int, code:string, name:string, is_active:int, product_count:int}>
     */
    public function listWithUsage(): array
    {
        /** @var list<array{id:int, code:string, name:string, is_active:int, product_count:int}> $rows */
        $rows = $this->select('modules.id, modules.code, modules.name, modules.is_active, COUNT(pm.id) AS product_count')
            ->join('product_modules pm', 'pm.module_id = modules.id', 'left')
            ->groupBy('modules.id')
            ->orderBy('modules.code', 'ASC')
            ->findAll();

        return $rows;
    }

    /**
     * 상품 폼 셀렉트용 활성 모듈 목록.
     *
     * @return list<array{id:int, code:string, name:string}>
     */
    public function activeForSelect(): array
    {
        /** @var list<array{id:int, code:string, name:string}> $rows */
        $rows = $this->select('id, code, name')
            ->where('is_active', 1)
            ->orderBy('code', 'ASC')
            ->findAll();

        return $rows;
    }

    /** 하나 이상의 상품에 연결돼 있으면 true(수정·삭제 불가 판단용). */
    public function isUsed(int $moduleId): bool
    {
        return $this->db->table('product_modules')
            ->where('module_id', $moduleId)
            ->countAllResults() > 0;
    }
}
