<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogModel;

/**
 * 감사 로그 조회(읽기) 유스케이스 — 관리 화면 목록·상세.
 *
 * 부정사용 등 audit_logs 이벤트를 라이센스·상품과 조인해 조회한다. 읽기 전용.
 */
final class AuditLogQueryService
{
    private const int MAX_PER_PAGE = 100;

    /**
     * 검색·필터·페이징 목록(상품명 조인). meta 표준 반환.
     *
     * @return array{items: list<array<string, mixed>>, meta: array{page:int, per_page:int, total:int, last_page:int}}
     */
    public function paginate(
        string $search = '',
        string $eventType = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $model = model(AuditLogModel::class);
        $model->select('audit_logs.*, products.name AS product_name')
            ->join('licenses', 'licenses.id = audit_logs.license_id', 'left')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->orderBy('audit_logs.id', 'DESC');

        $this->applyFilters($model, $search, $eventType, $dateFrom, $dateTo);

        $total = $model->countAllResults(false);
        /** @var list<array<string, mixed>> $items */
        $items = $model->limit($perPage, ($page - 1) * $perPage)->find();

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * 단건 상세 — 상품·라이센스 조인.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(AuditLogModel::class)
            ->select('audit_logs.*, products.name AS product_name, licenses.license_type AS license_type')
            ->join('licenses', 'licenses.id = audit_logs.license_id', 'left')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->where('audit_logs.id', $id)
            ->first();

        return $row;
    }

    /**
     * 목록·집계 공통 필터 적용.
     *
     * @param AuditLogModel $model
     */
    private function applyFilters(AuditLogModel $model, string $search, string $eventType, string $dateFrom, string $dateTo): void
    {
        if ($eventType !== '') {
            $model->where('audit_logs.event_type', $eventType);
        }
        if ($dateFrom !== '') {
            $model->where('audit_logs.created_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $model->where('audit_logs.created_at <=', $dateTo . ' 23:59:59');
        }
        if ($search !== '') {
            $model->groupStart()
                ->like('audit_logs.license_key', $search)
                ->orLike('audit_logs.host_id', $search)
                ->orLike('audit_logs.client_host_id', $search)
                ->orLike('audit_logs.ip', $search)
                ->groupEnd();
        }
    }
}
