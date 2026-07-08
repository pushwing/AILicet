<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\DTO\CustomerRequest;
use App\Enums\CustomerType;
use App\Models\CustomerModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * 회원(대행사/고객) 관리 (운영자).
 *
 * 목록은 서버사이드 검색·페이징(meta 표준)을 JSON 으로 제공하고, 화면은 AG Grid 로 렌더한다.
 */
final class CustomerController extends BaseAdminController
{
    /** GET /admin/members — 목록 화면. */
    public function index(): string
    {
        return $this->render('admin/members/index', [
            'title'      => '회원관리',
            'activeMenu' => 'members',
            'types'      => CustomerType::cases(),
        ]);
    }

    /** GET /admin/members/data — 검색·페이징 데이터(JSON, meta 표준). */
    public function data(): ResponseInterface
    {
        $result = service('customerService')->paginate(
            trim((string) $this->request->getGet('search')),
            (string) $this->request->getGet('type'),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result['items'],
            'meta'   => $result['meta'],
        ]);
    }

    /** GET /admin/members/new — 등록 폼. */
    public function new(): string
    {
        return $this->render('admin/members/form', [
            'title'      => '회원 등록',
            'activeMenu' => 'members',
            'customer'   => null,
            'types'      => CustomerType::cases(),
            'agencies'   => model(CustomerModel::class)->activeAgencies(),
        ]);
    }

    /** POST /admin/members — 생성. */
    public function create(): RedirectResponse
    {
        try {
            $id = service('customerService')->create(CustomerRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/members')->with('message', "회원이 등록되었습니다. (#{$id})");
    }

    /** GET /admin/members/{id}/edit — 수정 폼. */
    public function edit(int $id): string|RedirectResponse
    {
        $service  = service('customerService');
        $customer = $service->find($id);
        if ($customer === null) {
            return redirect()->to('/admin/members')->with('error', '회원을 찾을 수 없습니다.');
        }

        // 대행사 상세: 하위 고객 목록을 함께 노출한다. 고객이면 빈 배열.
        $isAgency = ($customer['customer_type'] ?? '') === CustomerType::Agency->value;
        $clients  = $isAgency ? $service->childClients($id) : [];

        // 연동 사용자(user_id) → AITessera 계정 정보 병기(베스트에포트).
        $userId        = isset($customer['user_id']) ? (int) $customer['user_id'] : null;
        $linkedAccount = $service->linkedAccount($userId, $this->operatorToken());

        return $this->render('admin/members/form', [
            'title'         => '회원 수정',
            'activeMenu'    => 'members',
            'customer'      => $customer,
            'types'         => CustomerType::cases(),
            'agencies'      => model(CustomerModel::class)->activeAgencies(),
            'clients'       => $clients,
            'linkedAccount' => $linkedAccount,
        ]);
    }

    /** POST /admin/members/{id} — 수정. */
    public function update(int $id): RedirectResponse
    {
        try {
            service('customerService')->update($id, CustomerRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/members')->with('message', '회원 정보가 수정되었습니다.');
    }

    /** POST /admin/members/{id}/delete — 삭제. */
    public function delete(int $id): RedirectResponse
    {
        service('customerService')->delete($id);

        return redirect()->to('/admin/members')->with('message', '회원이 삭제되었습니다.');
    }
}
