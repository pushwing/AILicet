<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * license_history — 라이센스 발급/재발급/상태변경 이력.
 *
 * 발급 시 자재코드(license_sn)와 관리키(license_key)를 함께 기록한다.
 */
final class CreateLicenseHistoryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'license_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20],                        // issue / reissue / status_change
            'host_id'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'license_sn'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],        // 자재코드
            'license_key' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],        // 관리키
            'contents'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('license_id', false, false, 'idx_license_history_license_id');
        $this->forge->addKey('license_key', false, false, 'idx_license_history_license_key');
        $this->forge->addForeignKey('license_id', 'licenses', 'id', 'CASCADE', 'CASCADE', 'fk_license_history_license');

        $this->forge->createTable('license_history', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('license_history', true);
    }
}
