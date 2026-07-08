<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * product_modules.module_id — 상품↔모듈 마스터 연결.
 *
 * product_modules 는 마스터 참조 피벗이 된다. code/name 은 발급 이력·다운스트림 호환을 위한
 * 스냅샷으로 유지한다. 사용 중 모듈 마스터 삭제를 막기 위해 FK 는 RESTRICT.
 */
final class AddModuleIdToProductModules extends Migration
{
    public function up(): void
    {
        // 1. module_id 컬럼 추가(기존 행을 채우기 전이라 우선 nullable).
        $this->forge->addColumn('product_modules', [
            'module_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'product_id',
            ],
        ]);

        // 2. 기존 행: code 매칭으로 마스터 module_id 채움.
        $this->db->query(
            'UPDATE product_modules pm JOIN modules m ON pm.code = m.code SET pm.module_id = m.id'
        );

        // 2-1. 백필 무결성 검증 — 매칭 실패로 NULL 이 남으면 isUsed() 잠금이 무력화되므로 마이그레이션을 중단한다.
        $orphans = (int) $this->db->table('product_modules')
            ->where('module_id', null)
            ->countAllResults();
        if ($orphans > 0) {
            throw new RuntimeException(
                "product_modules.module_id 백필 실패: 마스터와 매칭되지 않은 행 {$orphans}건. code 정합성을 확인하세요."
            );
        }

        // 3. FK(RESTRICT) + 유니크(product_id, module_id) 추가.
        $this->db->query(
            'ALTER TABLE product_modules
                ADD CONSTRAINT fk_product_modules_module
                FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
        $this->db->query(
            'ALTER TABLE product_modules
                ADD UNIQUE KEY uniq_product_modules_product_id_module_id (product_id, module_id)'
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE product_modules DROP FOREIGN KEY fk_product_modules_module');
        $this->db->query('ALTER TABLE product_modules DROP INDEX uniq_product_modules_product_id_module_id');
        $this->forge->dropColumn('product_modules', 'module_id');
    }
}
