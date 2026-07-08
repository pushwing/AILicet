<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;

/**
 * 대시보드 — 라이선스 발급·사용 현황 요약.
 *
 * 집계는 DashboardService(집계 쿼리·캐시)에 위임하고, 컨트롤러는 렌더만 담당한다.
 */
final class DashboardController extends BaseAdminController
{
    public function index(): string
    {
        $summary = service('dashboardService')->summary();

        return $this->render('admin/dashboard', [
            'title'      => '대시보드',
            'activeMenu' => 'dashboard',
            'stats'      => $summary['stats'],
            'chart'      => $summary['chart'],
            'rows'       => $summary['rows'],
        ]);
    }
}
