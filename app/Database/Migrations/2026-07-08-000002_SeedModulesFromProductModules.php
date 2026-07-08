<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 기존 product_modules 의 코드를 모듈 마스터로 승격(dedup).
 *
 * 상품별로 직접 입력돼 있던 (code, name) 을 code 기준으로 중복 제거해 modules 마스터에 적재한다.
 * 같은 code 에 이름이 여러 개면 대표로 하나(MIN)를 취한다. 재실행 안전을 위해 이미 존재하는 code 는 건너뛴다.
 */
final class SeedModulesFromProductModules extends Migration
{
    public function up(): void
    {
        // code 기준 dedup 하여 마스터로 적재. 이미 있는 code 는 NOT EXISTS 로 제외(재실행 안전).
        $this->db->query(
            'INSERT INTO modules (code, name, is_active, created_at, updated_at)
             SELECT pm.code, MIN(pm.name), 1, NOW(), NOW()
             FROM product_modules pm
             WHERE NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = pm.code)
             GROUP BY pm.code'
        );
    }

    public function down(): void
    {
        // 마스터 데이터 정리는 CreateModulesTable 의 down 에서 테이블 자체가 삭제되므로 별도 처리 없음.
    }
}
