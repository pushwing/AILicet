<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 고객 — 내 라이센스 조회(소유권 스코프).
 */
final class LicenseController extends BaseClientController
{
    /** GET /client/licenses */
    public function index(): string
    {
        if ($this->customerId === 0) {
            return $this->noCustomerView();
        }

        return $this->render('client/licenses/index', ['title' => '내 라이센스', 'activeMenu' => 'licenses']);
    }

    /** GET /client/licenses/data */
    public function data(): ResponseInterface
    {
        $result = service('clientService')->licensesPaginate(
            $this->customerId,
            trim((string) $this->request->getGet('search')),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /client/licenses/{id} — 상세(내 것만). */
    public function show(int $id): string|RedirectResponse
    {
        if (! service('clientService')->ownsLicense($this->customerId, $id)) {
            return redirect()->to('/client/licenses')->with('error', '권한이 없는 라이센스입니다.');
        }

        $detail = service('licenseQueryService')->detail($id);
        if ($detail === null) {
            return redirect()->to('/client/licenses')->with('error', '라이센스를 찾을 수 없습니다.');
        }

        return $this->render('agency/licenses/show', array_merge($detail, ['title' => '라이센스 상세', 'activeMenu' => 'licenses']));
    }
}
