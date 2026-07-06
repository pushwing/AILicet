<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * customers — 회원(대행사/고객).
 *
 * 대행사(agency)와 고객(client)을 customer_type 으로 구분하고,
 * 고객은 parent_id 로 소속 대행사를 참조한다(자기참조).
 */
final class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_type' => ['type' => 'VARCHAR', 'constraint' => 20],                       // agency / client
            'parent_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'company_name'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 50],                        // 담당자명
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'phone'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email', 'uniq_customers_email');
        $this->forge->addKey('parent_id', false, false, 'idx_customers_parent_id');
        $this->forge->addKey('customer_type', false, false, 'idx_customers_customer_type');
        // 대행사 삭제 시 하위 고객의 parent_id 는 NULL 처리(고객 자체는 보존)
        $this->forge->addForeignKey('parent_id', 'customers', 'id', 'SET NULL', 'CASCADE', 'fk_customers_parent');

        $this->forge->createTable('customers', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('customers', true);
    }
}
