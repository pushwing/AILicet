<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * customers.user_id — 회원 레코드와 AITessera 사용자(로그인 주체)를 연결.
 *
 * 대행사/고객 로그인 시 이 값으로 본인의 회원 레코드를 찾아 소유권 스코프를 강제한다.
 */
final class AddUserIdToCustomers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('customers', [
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'customer_type',
            ],
        ]);
        $this->db->query('CREATE INDEX idx_customers_user_id ON customers (user_id)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_customers_user_id ON customers');
        $this->forge->dropColumn('customers', 'user_id');
    }
}
