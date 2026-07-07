<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * customer_license — 고객·라이센스 매핑(피벗).
 *
 * 레거시 licenseMap. 네이밍 규칙(두 테이블 알파벳순·단수)에 따라 customer_license.
 */
final class CreateCustomerLicenseTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'license_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],        // 일반/세그플러스 등
            'created_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['customer_id', 'license_id'], 'uniq_customer_license_customer_id_license_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE', 'fk_customer_license_customer');
        $this->forge->addForeignKey('license_id', 'licenses', 'id', 'CASCADE', 'CASCADE', 'fk_customer_license_license');

        $this->forge->createTable('customer_license', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('customer_license', true);
    }
}
