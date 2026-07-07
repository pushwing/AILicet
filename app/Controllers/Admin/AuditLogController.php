<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\Enums\AuditEventType;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 감사 로그 조회 UI (운영자).
 *
 * 부정사용 등 audit_logs 이벤트를 조회한다. 읽기 전용 — 생성·수정·삭제 없음.
 */
final class AuditLogController extends BaseAdminController
{
    /** GET /admin/audit-logs — 목록. */
    public function index(): string
    {
        return $this->render('admin/audit-logs/index', [
            'title'      => '감사로그',
            'activeMenu' => 'audit',
            'eventTypes' => AuditEventType::cases(),
        ]);
    }

    /** GET /admin/audit-logs/data — 검색·필터·페이징 데이터(JSON). */
    public function data(): ResponseInterface
    {
        $result = service('auditLogQueryService')->paginate(
            trim((string) $this->request->getGet('search')),
            (string) $this->request->getGet('event_type'),
            (string) $this->request->getGet('date_from'),
            (string) $this->request->getGet('date_to'),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /admin/audit-logs/{id} — 상세. */
    public function show(int $id): string|RedirectResponse
    {
        $log = service('auditLogQueryService')->detail($id);
        if ($log === null) {
            return redirect()->to('/admin/audit-logs')->with('error', '감사로그를 찾을 수 없습니다.');
        }

        return $this->render('admin/audit-logs/show', [
            'title'      => '감사로그 상세',
            'activeMenu' => 'audit',
            'log'        => $log,
        ]);
    }
}
