<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * logs — AI 자동 분류·요약 결과 컬럼 추가.
 *
 * ai:classify-logs 배치가 미분류(ai_processed_at IS NULL) 로그를 골라
 * 분류(ai_category)·요약(ai_summary)을 채우고 처리 시각(ai_processed_at)을 기록한다.
 * ai_attempts 는 실패 재시도 횟수로, 한도 초과 시 dead-letter(ai_processed_at 마킹)해
 * head-of-line 블로킹을 막는다. ai_processed_at 인덱스로 미분류분 조회를 빠르게 한다.
 */
final class AddAiClassificationToLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('logs', [
            'ai_category'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'context'],
            'ai_summary'      => ['type' => 'TEXT', 'null' => true, 'after' => 'ai_category'],
            'ai_attempts'     => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0, 'after' => 'ai_summary'],
            'ai_processed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'ai_attempts'],
        ]);
        // 미분류분(ai_processed_at IS NULL) 조회용 인덱스 — 기존 마이그레이션 관례(raw ALTER) 준수.
        $this->db->query('ALTER TABLE logs ADD INDEX idx_logs_ai_processed_at (ai_processed_at)');
    }

    public function down(): void
    {
        // ai_processed_at 컬럼을 드롭하면 단일 컬럼 인덱스 idx_logs_ai_processed_at 는
        // MySQL 이 자동 제거하므로 별도 DROP INDEX 는 하지 않는다(상태 의존 실패 방지).
        $this->forge->dropColumn('logs', ['ai_category', 'ai_summary', 'ai_attempts', 'ai_processed_at']);
    }
}
