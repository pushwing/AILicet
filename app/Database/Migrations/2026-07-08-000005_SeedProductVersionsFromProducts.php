<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 기존 products.version 값을 product_versions 마스터로 이관.
 *
 * 각 상품의 단일 version 문자열을 해당 상품의 첫 버전으로 승격한다.
 * version 이 비어 있는 상품은 건너뛰고, 이미 같은 (product_id, version) 이 있으면 제외(재실행 안전).
 * products.version 컬럼은 하위호환·기본값 표시용으로 유지한다.
 */
final class SeedProductVersionsFromProducts extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "INSERT INTO product_versions (product_id, version, is_active, created_at, updated_at)
             SELECT p.id, p.version, 1, NOW(), NOW()
             FROM products p
             WHERE p.version IS NOT NULL AND p.version <> ''
               AND p.deleted_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM product_versions pv
                   WHERE pv.product_id = p.id AND pv.version = p.version
               )"
        );
    }

    public function down(): void
    {
        // 테이블 자체는 CreateProductVersionsTable 의 down 에서 제거되므로 별도 처리 없음.
    }
}
