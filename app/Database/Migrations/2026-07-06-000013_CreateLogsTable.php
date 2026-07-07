<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * logs — 프론트/클라이언트가 전송한 로그의 가공 저장(큐 소비자가 INSERT).
 *
 * 원시 로그는 파일(writable/logs/raw/)에 보존하고, 가공 데이터를 여기 저장한다.
 */
final class CreateLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'level'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'info'],
            'source'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'message'    => ['type' => 'TEXT', 'null' => true],
            'context'    => ['type' => 'JSON', 'null' => true],
            'client_ip'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'logged_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('level', false, false, 'idx_logs_level');
        $this->forge->addKey('created_at', false, false, 'idx_logs_created_at');

        $this->forge->createTable('logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('logs', true);
    }
}
