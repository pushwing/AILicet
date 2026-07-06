<?php

declare(strict_types=1);

namespace App\Controllers\Agency;

use App\DTO\CustomerRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * 대행사 — 하위 고객 관리(소유권 스코프).
 */
final class CustomerController extends BaseAgencyController
{
    /** GET /agency/customers */
    public function index(): string
    {
        if ($this->agencyId === 0) {
            return $this->noAgencyView();
        }

        return $this->render('agency/customers/index', ['title' => '고객 관리', 'activeMenu' => 'customers']);
    }

    /** GET /agency/customers/data — 스코프된 검색·페이징(JSON). */
    public function data(): ResponseInterface
    {
        $result = service('agencyService')->customersPaginate(
            $this->agencyId,
            trim((string) $this->request->getGet('search')),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /agency/customers/new */
    public function new(): string
    {
        return $this->render('agency/customers/form', ['title' => '고객 등록', 'activeMenu' => 'customers', 'customer' => null]);
    }

    /** POST /agency/customers */
    public function create(): RedirectResponse
    {
        try {
            service('agencyService')->createClient($this->agencyId, CustomerRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/agency/customers')->with('message', '고객이 등록되었습니다.');
    }

    /** GET /agency/customers/{id}/edit */
    public function edit(int $id): string|RedirectResponse
    {
        if (! service('agencyService')->ownsCustomer($this->agencyId, $id)) {
            return redirect()->to('/agency/customers')->with('error', '권한이 없는 고객입니다.');
        }

        return $this->render('agency/customers/form', [
            'title'      => '고객 수정',
            'activeMenu' => 'customers',
            'customer'   => model(\App\Models\CustomerModel::class)->find($id),
        ]);
    }

    /** POST /agency/customers/{id} */
    public function update(int $id): RedirectResponse
    {
        try {
            service('agencyService')->updateClient($this->agencyId, $id, CustomerRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/agency/customers')->with('message', '고객 정보가 수정되었습니다.');
    }

    /** POST /agency/customers/{id}/delete */
    public function delete(int $id): RedirectResponse
    {
        if (! service('agencyService')->ownsCustomer($this->agencyId, $id)) {
            return redirect()->to('/agency/customers')->with('error', '권한이 없는 고객입니다.');
        }
        model(\App\Models\CustomerModel::class)->delete($id);

        return redirect()->to('/agency/customers')->with('message', '고객이 삭제되었습니다.');
    }
}
