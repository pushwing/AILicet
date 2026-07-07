<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * licenses — 라이센스.
 *
 * 노드락/플로팅 공통 테이블. 타입별로 흩어져 있던 사용량 컬럼
 * (limit_count·limit_credit·subduction_credit)은 config(JSON)로 통합한다.
 */
final class CreateLicensesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'license_type'        => ['type' => 'VARCHAR', 'constraint' => 20],                     // nodelock / floating
            'period_code'         => ['type' => 'VARCHAR', 'constraint' => 30],                     // 기간·사용량 정책
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'version'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'host_id'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],     // 노드락 머신 고유값
            'expire_date'         => ['type' => 'DATE', 'null' => true],                            // 기간 제한 시
            'support_end_date'    => ['type' => 'DATE', 'null' => true],
            'activate_term'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],         // 플로팅 활성화 간격(시간)
            'check_term'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],         // 플로팅 유효성 체크 간격(분)
            'activate_history_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'path'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],    // 노드락 서명 파일 S3 경로
            'is_trial'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'config'              => ['type' => 'JSON', 'null' => true],                            // {limit_count, limit_credit, subduction_credit, modules[]}
            'issued_by'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // 발급자(AITessera user id)
            'issue_date'          => ['type' => 'DATE', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status', false, false, 'idx_licenses_status');
        $this->forge->addKey('host_id', false, false, 'idx_licenses_host_id');
        $this->forge->addKey('license_type', false, false, 'idx_licenses_license_type');
        $this->forge->addKey('expire_date', false, false, 'idx_licenses_expire_date');
        // 상품 삭제는 라이센스 발급 이력 보존을 위해 제한(RESTRICT)
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE', 'fk_licenses_product');

        $this->forge->createTable('licenses', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('licenses', true);
    }
}
