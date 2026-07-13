<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * audit_logs — AI 사람용 설명 자동 생성 결과 컬럼 추가.
 *
 * ai:explain-audit-logs 배치(별도 슬라이스)가 미설명(ai_processed_at IS NULL) 감사 로그를 골라
 * 사람용 한국어 설명(ai_explanation)을 채우고 처리 시각(ai_processed_at)을 기록한다.
 * ai_attempts 는 실패 재시도 횟수로, 한도 초과 시 dead-letter(ai_processed_at 마킹)해
 * head-of-line 블로킹을 막는다. ai_processed_at 인덱스로 미설명분 조회를 빠르게 한다.
 * (logs/inquiries AI 분류 마이그레이션과 동일 패턴)
 */
final class AddAiExplanationToAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('audit_logs', [
            'ai_explanation'  => ['type' => 'TEXT', 'null' => true, 'after' => 'detail'],
            'ai_attempts'     => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0, 'after' => 'ai_explanation'],
            'ai_processed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'ai_attempts'],
        ]);
        // 미설명분(ai_processed_at IS NULL) 조회용 인덱스 — 기존 마이그레이션 관례(raw ALTER) 준수.
        $this->db->query('ALTER TABLE audit_logs ADD INDEX idx_audit_logs_ai_processed_at (ai_processed_at)');
    }

    public function down(): void
    {
        // ai_processed_at 컬럼을 드롭하면 단일 컬럼 인덱스 idx_audit_logs_ai_processed_at 는
        // MySQL 이 자동 제거하므로 별도 DROP INDEX 는 하지 않는다(상태 의존 실패 방지).
        $this->forge->dropColumn('audit_logs', ['ai_explanation', 'ai_attempts', 'ai_processed_at']);
    }
}
