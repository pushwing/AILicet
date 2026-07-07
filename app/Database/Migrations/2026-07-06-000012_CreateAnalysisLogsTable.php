<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * analysis_logs — 플로팅 라이센스 분석 사용 기록(카운트/크레딧 차감).
 *
 * analysis_key 로 시작/종료를 매칭하고, 종료(completed)에서 사용량을 확정한다.
 * analysis_key 유니크로 중복 차감을 방지한다.
 */
final class CreateAnalysisLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'license_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'analysis_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'host_id'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'started'], // started/completed/failed
            'amount'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],              // 차감 크레딧(카운트제는 1)
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'ended_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('analysis_key', 'uniq_analysis_logs_analysis_key');
        $this->forge->addKey('license_id', false, false, 'idx_analysis_logs_license_id');
        $this->forge->addForeignKey('license_id', 'licenses', 'id', 'CASCADE', 'CASCADE', 'fk_analysis_logs_license');

        $this->forge->createTable('analysis_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('analysis_logs', true);
    }
}
