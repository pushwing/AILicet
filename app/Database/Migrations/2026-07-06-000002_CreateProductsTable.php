<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * products — 상품(제품).
 *
 * license_type 으로 노드락/플로팅 지원 여부를 구분하고,
 * period_code 는 해당 상품의 기본 기간 정책이다.
 */
final class CreateProductsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_code'   => ['type' => 'VARCHAR', 'constraint' => 30],                        // 예: PT001
            'name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'product_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],        // 제품군 대분류(teslab/aqua/tms…)
            'license_type'   => ['type' => 'VARCHAR', 'constraint' => 20],                        // nodelock / floating
            'version'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'period_code'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],        // 기본 기간 정책
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('product_code', 'uniq_products_product_code');
        $this->forge->addKey('license_type', false, false, 'idx_products_license_type');

        $this->forge->createTable('products', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('products', true);
    }
}
