<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\DTO\ModuleRequest;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * 모듈 마스터 관리 (운영자).
 *
 * 목록은 상품·모듈 관리 화면(ProductController::index)의 '모듈 관리' 탭에서 함께 렌더링되며,
 * 이 컨트롤러는 생성·수정·삭제만 처리한다. 처리 후 모듈 탭으로 되돌아간다.
 */
final class ModuleController extends BaseAdminController
{
    /** 모듈 탭으로 돌아가는 목록 URL. */
    private const string LIST_URL = '/admin/products?tab=modules';

    /** POST /admin/modules — 생성. */
    public function create(): RedirectResponse
    {
        try {
            service('moduleService')->create(ModuleRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->to(self::LIST_URL)->with('error', $e->getMessage());
        }

        return redirect()->to(self::LIST_URL)->with('message', '모듈이 등록되었습니다.');
    }

    /** POST /admin/modules/{id} — 수정. */
    public function update(int $id): RedirectResponse
    {
        try {
            service('moduleService')->update($id, ModuleRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->to(self::LIST_URL)->with('error', $e->getMessage());
        }

        return redirect()->to(self::LIST_URL)->with('message', '모듈이 수정되었습니다.');
    }

    /** POST /admin/modules/{id}/delete — 삭제(미사용 모듈만). */
    public function delete(int $id): RedirectResponse
    {
        try {
            service('moduleService')->delete($id);
        } catch (RuntimeException $e) {
            return redirect()->to(self::LIST_URL)->with('error', $e->getMessage());
        }

        return redirect()->to(self::LIST_URL)->with('message', '모듈이 삭제되었습니다.');
    }
}
