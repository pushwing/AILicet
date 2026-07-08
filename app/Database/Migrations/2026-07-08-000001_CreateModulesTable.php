<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * modules — 모듈 마스터.
 *
 * 상품과 독립적으로 먼저 등록되는 모듈 카탈로그. 상품은 이 마스터에서 모듈을 선택한다.
 * code 는 전역 유니크. 이미 상품에 연결된 모듈은 수정/삭제할 수 없고 비활성(is_active=0)만 가능하다.
 */
final class CreateModulesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 30],                      // 예: MD001
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code', 'uniq_modules_code');

        $this->forge->createTable('modules', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('modules', true);
    }
}
