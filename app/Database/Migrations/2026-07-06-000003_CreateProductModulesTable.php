<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * product_modules — 상품별 모듈.
 *
 * 상품 하나에 여러 모듈(code)이 매핑된다. (product_id, code) 유니크.
 */
final class CreateProductModulesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 30],                         // 예: MD001
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['product_id', 'code'], 'uniq_product_modules_product_id_code');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE', 'fk_product_modules_product');

        $this->forge->createTable('product_modules', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('product_modules', true);
    }
}
