<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * licenses.issue_date 인덱스 추가.
 *
 * 대시보드 집계('이번 달 발급' 카운트·월별 발급 추이 GROUP BY)가 issue_date 를
 * 조건·집계 컬럼으로 사용한다. 인덱스 없는 컬럼 WHERE/GROUP BY 를 피하기 위해 추가.
 */
final class AddIssueDateIndexToLicenses extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE licenses ADD INDEX idx_licenses_issue_date (issue_date)'
        );
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE licenses DROP INDEX idx_licenses_issue_date');
    }
}
