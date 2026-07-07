<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * customers 에 이메일 인증 필드 추가(고객 자가가입용).
 *
 * verify_token: 인증 링크 토큰(인증 완료 후 NULL), email_verified_at: 인증 시각.
 */
final class AddVerificationToCustomers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('customers', [
            'verify_token'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'is_active'],
            'email_verified_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'verify_token'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', ['verify_token', 'email_verified_at']);
    }
}
