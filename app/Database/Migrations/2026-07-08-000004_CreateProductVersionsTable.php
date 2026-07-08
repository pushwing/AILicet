<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * product_versions — 상품 버전 마스터.
 *
 * 상품 1:N 버전. 발급 시 상품을 선택하면 이 목록에서 버전을 고른다(이슈 #48).
 * 상품과 함께 소멸(CASCADE)하며, (product_id, version) 조합은 유니크하다.
 */
final class CreateProductVersionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'version'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['product_id', 'version'], 'uniq_product_versions_product_version');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_product_versions_product');

        $this->forge->createTable('product_versions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('product_versions', true);
    }
}
