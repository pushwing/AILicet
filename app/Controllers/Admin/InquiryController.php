<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * 고객 문의 관리 UI (운영자).
 *
 * AI 가 생성한 분류·답변 초안을 표시하고, 운영자가 초안을 검토·수정해 확정 발송한다(human-in-the-loop).
 */
final class InquiryController extends BaseAdminController
{
    /** GET /admin/inquiries — 목록. */
    public function index(): string
    {
        return $this->render('admin/inquiries/index', [
            'title'      => '문의관리',
            'activeMenu' => 'inquiries',
            'statuses'   => InquiryStatus::cases(),
            'categories' => InquiryCategory::cases(),
        ]);
    }

    /** GET /admin/inquiries/data — 검색·필터·페이징 데이터(JSON). */
    public function data(): ResponseInterface
    {
        $result = service('inquiryService')->paginate(
            trim((string) $this->request->getGet('search')),
            (string) $this->request->getGet('status'),
            (string) $this->request->getGet('category'),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /admin/inquiries/{id} — 상세(AI 초안 표시·편집). */
    public function show(int $id): string|RedirectResponse
    {
        $inquiry = service('inquiryService')->find($id);
        if ($inquiry === null) {
            return redirect()->to('/admin/inquiries')->with('error', '문의를 찾을 수 없습니다.');
        }

        return $this->render('admin/inquiries/show', [
            'title'      => '문의 상세',
            'activeMenu' => 'inquiries',
            'inquiry'    => $inquiry,
        ]);
    }

    /** POST /admin/inquiries/{id}/reply — 답변 확정 발송. */
    public function reply(int $id): RedirectResponse
    {
        try {
            service('inquiryService')->sendReply($id, (string) $this->request->getPost('reply'));
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/inquiries/' . $id)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/inquiries/' . $id)->with('message', '답변을 발송했습니다.');
    }
}
