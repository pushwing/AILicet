<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * audit_logs — 통합 감사 로그.
 *
 * 레거시 invalidLicense(부정사용)를 일반화. 호스트 불일치·만료 사용·이중 활성화 등
 * event_type 으로 분류하고, 상세는 detail(JSON)에 담는다.
 */
final class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'license_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'event_type'     => ['type' => 'VARCHAR', 'constraint' => 40],                        // illegal_host / expired_use …
            'host_id'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],        // 등록 호스트
            'client_host_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],        // 실제 사용 호스트
            'license_key'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'ip'             => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'detail'         => ['type' => 'JSON', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('license_id', false, false, 'idx_audit_logs_license_id');
        $this->forge->addKey('event_type', false, false, 'idx_audit_logs_event_type');
        $this->forge->addKey('created_at', false, false, 'idx_audit_logs_created_at');
        // 라이센스 삭제돼도 감사 기록은 보존(license_id 만 NULL)
        $this->forge->addForeignKey('license_id', 'licenses', 'id', 'SET NULL', 'CASCADE', 'fk_audit_logs_license');

        $this->forge->createTable('audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
