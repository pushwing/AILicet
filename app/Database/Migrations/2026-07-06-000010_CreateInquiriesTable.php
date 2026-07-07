<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * inquiries — 고객센터 이메일 문의.
 */
final class CreateInquiriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'subject'     => ['type' => 'VARCHAR', 'constraint' => 200],
            'content'     => ['type' => 'TEXT'],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'], // open/answered/closed
            'reply'       => ['type' => 'TEXT', 'null' => true],
            'replied_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id', false, false, 'idx_inquiries_customer_id');
        $this->forge->addKey('status', false, false, 'idx_inquiries_status');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE', 'fk_inquiries_customer');

        $this->forge->createTable('inquiries', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inquiries', true);
    }
}
