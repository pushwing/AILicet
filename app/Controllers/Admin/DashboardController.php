<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;

/**
 * 대시보드 — 공통 레이아웃/그리드/차트 렌더 확인용 샘플 화면.
 *
 * 실데이터 연동(집계 쿼리·캐시)은 후속 이슈에서 서비스 레이어로 대체한다.
 */
final class DashboardController extends BaseAdminController
{
    public function index(): string
    {
        return $this->render('admin/dashboard', [
            'title'      => '대시보드',
            'activeMenu' => 'dashboard',
            'stats'      => $this->sampleStats(),
            'chart'      => $this->sampleChart(),
            'rows'       => $this->sampleRows(),
        ]);
    }

    /**
     * @return list<array{label:string, value:string, delta:string, dir:string}>
     */
    private function sampleStats(): array
    {
        return [
            ['label' => '활성 라이선스', 'value' => '1,284', 'delta' => '▲ 3.2% 이번 달', 'dir' => 'up'],
            ['label' => '이번 달 발급', 'value' => '86',    'delta' => '▲ 12건',        'dir' => 'up'],
            ['label' => '만료 임박(15일)', 'value' => '9',   'delta' => '▼ 2건',         'dir' => 'down'],
            ['label' => '부정사용 감지', 'value' => '2',     'delta' => '주의 필요',      'dir' => 'down'],
        ];
    }

    /**
     * @return array{labels:list<string>, values:list<int>}
     */
    private function sampleChart(): array
    {
        return [
            'labels' => ['2월', '3월', '4월', '5월', '6월', '7월'],
            'values' => [42, 55, 48, 63, 71, 86],
        ];
    }

    /**
     * @return list<array{sn:string, product:string, type:string, customer:string, status:string, expire:string}>
     */
    private function sampleRows(): array
    {
        return [
            ['sn' => 'PT001-260701-01', 'product' => 'tES LAB',  'type' => '노드락',   'customer' => '뉴로핏',    'status' => 'active',     'expire' => '2027-06-30'],
            ['sn' => 'PT002-260628-04', 'product' => 'AQUA',     'type' => '플로팅',   'customer' => '메디컬AI',  'status' => 'active',     'expire' => '2026-12-31'],
            ['sn' => 'PT001-260615-02', 'product' => 'tES LAB',  'type' => '노드락',   'customer' => '한빛의료',  'status' => 'suspended',  'expire' => '2026-09-15'],
            ['sn' => 'PT003-260610-07', 'product' => 'TMS LAB',  'type' => '플로팅',   'customer' => '서울성형',  'status' => 'active',     'expire' => '2027-01-20'],
            ['sn' => 'PT001-260520-03', 'product' => 'tES LAB',  'type' => '노드락',   'customer' => '강남클리닉', 'status' => 'terminated', 'expire' => '2026-05-19'],
            ['sn' => 'PT006-260505-01', 'product' => 'SCALE',    'type' => '플로팅',   'customer' => '토탈뷰티',  'status' => 'active',     'expire' => '2026-11-30'],
        ];
    }
}
