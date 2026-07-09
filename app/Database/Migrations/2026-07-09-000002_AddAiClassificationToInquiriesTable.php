<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * inquiries — AI 자동 분류·답변 초안 결과 컬럼 추가.
 *
 * ai:draft-inquiries 배치가 미처리(ai_processed_at IS NULL) 문의를 골라
 * 분류(ai_category)와 답변 초안(ai_draft_reply)을 채우고 처리 시각(ai_processed_at)을 기록한다.
 * ai_draft_reply 는 어디까지나 "초안"이며, 실제 발송(reply/status=answered)은 운영자가 확정한다(human-in-the-loop).
 * ai_attempts 는 실패 재시도 횟수로, 한도 초과 시 dead-letter(ai_processed_at 마킹)해 head-of-line 블로킹을 막는다.
 * logs 테이블 선례(AddAiClassificationToLogsTable)와 동일한 컬럼셋·인덱스 관례를 따른다.
 */
final class AddAiClassificationToInquiriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inquiries', [
            'ai_category'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'content'],
            'ai_draft_reply'  => ['type' => 'TEXT', 'null' => true, 'after' => 'ai_category'],
            'ai_attempts'     => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0, 'after' => 'ai_draft_reply'],
            'ai_processed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'ai_attempts'],
        ]);
        // 미처리분(ai_processed_at IS NULL) 조회용 인덱스 — 기존 마이그레이션 관례(raw ALTER) 준수.
        $this->db->query('ALTER TABLE inquiries ADD INDEX idx_inquiries_ai_processed_at (ai_processed_at)');
        // Admin 목록 화면의 카테고리 필터(WHERE ai_category = ?)용 인덱스.
        $this->db->query('ALTER TABLE inquiries ADD INDEX idx_inquiries_ai_category (ai_category)');
    }

    public function down(): void
    {
        // ai_processed_at 컬럼을 드롭하면 단일 컬럼 인덱스 idx_inquiries_ai_processed_at 는
        // MySQL 이 자동 제거하므로 별도 DROP INDEX 는 하지 않는다(상태 의존 실패 방지).
        $this->forge->dropColumn('inquiries', ['ai_category', 'ai_draft_reply', 'ai_attempts', 'ai_processed_at']);
    }
}
