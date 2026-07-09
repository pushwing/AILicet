<?php

declare(strict_types=1);

namespace App\Controllers\Agency;

use App\DTO\FloatingIssueRequest;
use App\DTO\NodeLockIssueRequest;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;
use App\Models\CustomerModel;
use App\Models\ProductModuleModel;
use App\Models\ProductVersionModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * 대행사 — 라이센스 발급/조회(소유권 스코프).
 *
 * 발급 대상은 자기 하위 고객으로 제한되고, 조회·상세는 자기 소유 라이센스만 허용한다.
 * 상태 변경(정지/종료 등)은 운영자 권한이므로 대행사에는 제공하지 않는다.
 */
final class LicenseController extends BaseAgencyController
{
    /** GET /agency/licenses */
    public function index(): string
    {
        if ($this->agencyId === 0) {
            return $this->noAgencyView();
        }

        return $this->render('agency/licenses/index', [
            'title'      => '라이센스',
            'activeMenu' => 'licenses',
            'types'      => LicenseType::cases(),
            'statuses'   => LicenseStatus::cases(),
        ]);
    }

    /** GET /agency/licenses/data — 스코프된 검색·페이징(JSON). */
    public function data(): ResponseInterface
    {
        $result = service('agencyService')->licensesPaginate(
            $this->agencyId,
            trim((string) $this->request->getGet('search')),
            (string) $this->request->getGet('type'),
            (string) $this->request->getGet('status'),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 20),
        );

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    /** GET /agency/licenses/new — 발급 폼(고객은 자기 하위만). */
    public function new(): string
    {
        return $this->render('agency/licenses/new', [
            'title'       => '라이센스 발급',
            'activeMenu'  => 'licenses',
            'products'    => service('productService')->activeForSelect(),
            'customers'   => model(CustomerModel::class)->where('parent_id', $this->agencyId)->where('is_active', 1)->findAll(),
            'periodCodes' => PeriodCode::cases(),
        ]);
    }

    /** GET /agency/licenses/product-modules/{id} */
    public function productModules(int $productId): ResponseInterface
    {
        return $this->response->setJSON(['status' => 'success', 'data' => model(ProductModuleModel::class)->byProduct($productId)]);
    }

    /** GET /agency/licenses/product-versions/{id} */
    public function productVersions(int $productId): ResponseInterface
    {
        return $this->response->setJSON(['status' => 'success', 'data' => model(ProductVersionModel::class)->byProduct($productId)]);
    }

    /** POST /agency/licenses — 발급(자기 고객 대상). */
    public function create(): RedirectResponse
    {
        $customerId = (int) $this->request->getPost('customer_id');
        if (! service('agencyService')->ownsCustomer($this->agencyId, $customerId)) {
            return redirect()->back()->withInput()->with('error', '자기 하위 고객에게만 발급할 수 있습니다.');
        }

        $type    = (string) $this->request->getPost('license_type');
        $issuedBy = (int) (session()->get('authUser')['id'] ?? 0);

        $payload = [
            'product_id'       => (int) $this->request->getPost('product_id'),
            'period_code'      => (string) $this->request->getPost('period_code'),
            'issued_by'        => $issuedBy,
            'customer_id'      => $customerId,
            'version'          => $this->request->getPost('version') ?: null,
            'expire_date'      => $this->request->getPost('expire_date') ?: null,
            'support_end_date' => $this->request->getPost('support_end_date') ?: null,
            'modules'          => (array) $this->request->getPost('modules'),
            'limits'           => array_filter([
                'count'  => $this->request->getPost('limit_count') !== null && $this->request->getPost('limit_count') !== ''
                    ? (int) $this->request->getPost('limit_count') : null,
                'credit' => $this->request->getPost('limit_credit') !== null && $this->request->getPost('limit_credit') !== ''
                    ? (int) $this->request->getPost('limit_credit') : null,
            ], static fn ($v) => $v !== null),
        ];

        try {
            // 기간정책별 필수/잠금 필드 검증·정규화. 이슈 #48
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
                $result = service('floatingLicenseService')->issue(FloatingIssueRequest::fromArray($payload));
            } else {
                $host = NodeLockIssueRequest::normalizeHostId((string) $this->request->getPost('host_id'));
                if ($host === null) {
                    return redirect()->back()->withInput()
                        ->with('error', '호스트ID 형식이 올바르지 않습니다. 예: 9F3A-1C7B-E204-8DD6 (tools/hostid 유틸리티로 산출)');
                }
                $payload['host_id'] = $host;
                $result = service('nodeLockLicenseService')->issue(NodeLockIssueRequest::fromArray($payload));
            }
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/agency/licenses/' . $result['license_id'])
            ->with('message', "라이센스가 발급되었습니다. 관리키: {$result['license_key']}");
    }

    /** GET /agency/licenses/{id} — 상세(소유 라이센스만). */
    public function show(int $id): string|RedirectResponse
    {
        if (! service('agencyService')->ownsLicense($this->agencyId, $id)) {
            return redirect()->to('/agency/licenses')->with('error', '권한이 없는 라이센스입니다.');
        }

        $detail = service('licenseQueryService')->detail($id);
        if ($detail === null) {
            return redirect()->to('/agency/licenses')->with('error', '라이센스를 찾을 수 없습니다.');
        }

        return $this->render('agency/licenses/show', array_merge($detail, ['title' => '라이센스 상세', 'activeMenu' => 'licenses']));
    }
}
