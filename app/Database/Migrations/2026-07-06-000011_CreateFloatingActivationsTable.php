<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * floating_activations — 플로팅 라이센스 활성화 이력.
 *
 * 활성화 시점(activated_at)과 만료(expires_at = activated_at + activate_term 시간)를 기록해
 * 이중 활성화(다른 호스트가 활성 구간 내 재활성화)를 차단한다.
 */
final class CreateFloatingActivationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'license_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'host_id'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'activated_at' => ['type' => 'DATETIME'],
            'expires_at'   => ['type' => 'DATETIME'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('license_id', false, false, 'idx_floating_activations_license_id');
        $this->forge->addForeignKey('license_id', 'licenses', 'id', 'CASCADE', 'CASCADE', 'fk_floating_activations_license');

        $this->forge->createTable('floating_activations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('floating_activations', true);
    }
}
