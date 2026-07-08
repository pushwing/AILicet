<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * notifications — 인앱 메시지(수신함).
 *
 * 운영자/대행사/회원에게 라이센스 만료 임박·종료를 알린다.
 * 수신함 스코프 키는 recipient_user_id(AITessera user id)이며, 운영자 공용
 * 메시지는 recipient_user_id 를 NULL 로 두어 모든 운영자가 함께 본다.
 * license_id 는 라이센스가 삭제돼도 메시지를 보존하기 위해 FK 없이 인덱스만 둔다.
 */
final class CreateNotificationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'recipient_role'    => ['type' => 'INT', 'constraint' => 11],                              // UserRole: 1 운영자 / 2 대행사 / 3 회원
            'recipient_user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // 수신함 스코프(운영자 공용=NULL)
            'customer_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // 회원(customers.id) 참고용
            'license_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // 관련 라이센스(FK 없음)
            'type'              => ['type' => 'VARCHAR', 'constraint' => 30],                           // license_expiring / license_expired
            'level'             => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'info'],      // info / warning / error
            'title'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'              => ['type' => 'TEXT'],
            'dedup_key'         => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],          // 배치 재실행 멱등성
            'is_read'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'read_at'           => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['recipient_role', 'recipient_user_id', 'is_read'], false, false, 'idx_notifications_recipient');
        $this->forge->addKey('license_id', false, false, 'idx_notifications_license_id');
        $this->forge->addUniqueKey('dedup_key', 'uniq_notifications_dedup_key');

        $this->forge->createTable('notifications', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('notifications', true);
    }
}
