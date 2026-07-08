<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * products.description — 상품 설명(리치 텍스트 HTML).
 *
 * 운영자가 리치 에디터(Tiptap)로 입력한 설명을 저장한다. 저장 전 화이트리스트
 * 정화를 거친 안전한 HTML 만 보관하므로 출력 시 그대로 렌더한다.
 */
final class AddDescriptionToProducts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('products', [
            'description' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'name',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('products', 'description');
    }
}
