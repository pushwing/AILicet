<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\DTO\FloatingIssueRequest;
use App\DTO\NodeLockIssueRequest;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\CustomerModel;
use App\Models\ProductModuleModel;
use App\Models\ProductVersionModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * 라이센스 관리 UI (운영자).
 *
 * 발급은 종류에 따라 NodeLock/Floating 발급 서비스로, 상태 변경은 LicenseLifecycleService 로 위임한다.
 */
final class LicenseController extends BaseAdminController
{
    /** GET /admin/licenses — 목록. */
    public function index(): string
    {
        return $this->render('admin/licenses/index', [
            'title'      => '라이센스 관리',
            'activeMenu' => 'licenses',
            'types'      => LicenseType::cases(),
            'statuses'   => LicenseStatus::cases(),
        ]);
    }

    /** GET /admin/licenses/data — 검색·페이징 데이터(JSON). */
    public function data(): ResponseInterface
    {
        $result = service('licenseQueryService')->paginate(
            trim((string) $this->request->getGet('search')),
            (string) $this->request->getGet('type'),
            (string) $this->request->getGet('status'),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /admin/licenses/new — 발급 폼. */
    public function new(): string
    {
        return $this->render('admin/licenses/new', [
            'title'       => '라이센스 발급',
            'activeMenu'  => 'licenses',
            'products'    => service('productService')->activeForSelect(),
            'customers'   => model(CustomerModel::class)->where('is_active', 1)->orderBy('company_name', 'ASC')->findAll(),
            'periodCodes' => PeriodCode::cases(),
        ]);
    }

    /** GET /admin/licenses/product-modules/{id} — 상품 모듈(JSON, 발급 폼 연동). */
    public function productModules(int $productId): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => model(ProductModuleModel::class)->byProduct($productId),
        ]);
    }

    /** GET /admin/licenses/product-versions/{id} — 상품 버전(JSON, 발급 폼 연동). */
    public function productVersions(int $productId): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => model(ProductVersionModel::class)->byProduct($productId),
        ]);
    }

    /** POST /admin/licenses — 발급(종류별 분기). */
    public function create(): RedirectResponse
    {
        $type     = (string) $this->request->getPost('license_type');
        $issuedBy = (int) (session()->get('authUser')['id'] ?? 0);

        $payload = [
            'product_id'       => (int) $this->request->getPost('product_id'),
            'period_code'      => (string) $this->request->getPost('period_code'),
            'issued_by'        => $issuedBy,
            'version'          => $this->request->getPost('version') ?: null,
            'expire_date'      => $this->request->getPost('expire_date') ?: null,
            'support_end_date' => $this->request->getPost('support_end_date') ?: null,
            'modules'          => (array) $this->request->getPost('modules'),
            'customer_id'      => $this->request->getPost('customer_id') ?: null,
            'is_trial'         => $this->request->getPost('is_trial') === '1',
            'limits'           => array_filter([
                'count'  => $this->request->getPost('limit_count') !== null && $this->request->getPost('limit_count') !== ''
                    ? (int) $this->request->getPost('limit_count') : null,
                'credit' => $this->request->getPost('limit_credit') !== null && $this->request->getPost('limit_credit') !== ''
                    ? (int) $this->request->getPost('limit_credit') : null,
            ], static fn ($v) => $v !== null),
        ];

        try {
            // 기간정책별 필수/잠금 필드 검증·정규화(잠금 필드 값 제거). 이슈 #48
            $normalized = service('licensePolicyValidator')->normalize(
                $payload['period_code'],
                $payload['expire_date'],
                $payload['support_end_date'],
                $payload['limits'],
            );
            $payload['expire_date']      = $normalized['expire_date'];
            $payload['support_end_date'] = $normalized['support_end_date'];
            $payload['limits']           = $normalized['limits'];

            if ($type === LicenseType::Floating->value) {
                $result = service('floatingLicenseService')->issue(FloatingIssueRequest::fromArray(array_merge($payload, [
                    'activate_term' => (int) ($this->request->getPost('activate_term') ?: 24),
                    'check_term'    => (int) ($this->request->getPost('check_term') ?: 30),
                ])));
            } else {
                $payload['host_id'] = (string) $this->request->getPost('host_id');
                $result = service('nodeLockLicenseService')->issue(NodeLockIssueRequest::fromArray($payload));
            }
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/licenses/' . $result['license_id'])
            ->with('message', "라이센스가 발급되었습니다. 관리키: {$result['license_key']}");
    }

    /** GET /admin/licenses/{id} — 상세. */
    public function show(int $id): string|RedirectResponse
    {
        $detail = service('licenseQueryService')->detail($id);
        if ($detail === null) {
            return redirect()->to('/admin/licenses')->with('error', '라이센스를 찾을 수 없습니다.');
        }

        return $this->render('admin/licenses/show', array_merge($detail, [
            'title'      => '라이센스 상세',
            'activeMenu' => 'licenses',
        ]));
    }

    /** GET /admin/licenses/{id}/download — 노드락 서명 파일 다운로드. */
    public function download(int $id): ResponseInterface|RedirectResponse
    {
        $detail = service('licenseQueryService')->detail($id);
        $path   = $detail['license']['path'] ?? null;
        if ($detail === null || ! is_string($path) || $path === '') {
            return redirect()->to('/admin/licenses/' . $id)->with('error', '다운로드할 라이센스 파일이 없습니다.');
        }

        $contents = service('licenseStorage')->get($path);
        if ($contents === null) {
            return redirect()->to('/admin/licenses/' . $id)->with('error', '라이센스 파일을 찾을 수 없습니다.');
        }

        return $this->response
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Disposition', 'attachment; filename="NLicense.lic"')
            ->setBody($contents);
    }

    /** POST /admin/licenses/{id}/suspend|resume|terminate — 상태 전이. */
    public function suspend(int $id): RedirectResponse
    {
        return $this->transition($id, 'suspend');
    }

    public function resume(int $id): RedirectResponse
    {
        return $this->transition($id, 'resume');
    }

    public function terminate(int $id): RedirectResponse
    {
        return $this->transition($id, 'terminate');
    }

    /** POST /admin/licenses/{id}/extend — 만료일 연장. */
    public function extend(int $id): RedirectResponse
    {
        $newDate = (string) $this->request->getPost('expire_date');
        $actor   = (int) (session()->get('authUser')['id'] ?? 0);

        try {
            service('licenseLifecycleService')->extend($id, $newDate, $actor);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/licenses/' . $id)->with('message', '만료일이 연장되었습니다.');
    }

    /** POST /admin/licenses/{id}/reissue — 재발급. */
    public function reissue(int $id): RedirectResponse
    {
        $actor   = (int) (session()->get('authUser')['id'] ?? 0);
        $newHost = $this->request->getPost('host_id') ?: null;

        try {
            $newKey = service('licenseLifecycleService')->reissue($id, $actor, $newHost !== null ? (string) $newHost : null);
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/licenses/' . $id)->with('message', "재발급되었습니다. 새 관리키: {$newKey}");
    }

    private function transition(int $id, string $action): RedirectResponse
    {
        $actor  = (int) (session()->get('authUser')['id'] ?? 0);
        $reason = (string) $this->request->getPost('reason');

        try {
            service('licenseLifecycleService')->{$action}($id, $actor, $reason);
        } catch (InvalidStateTransitionException | RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/licenses/' . $id)->with('message', '상태가 변경되었습니다.');
    }
}
