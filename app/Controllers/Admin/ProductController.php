<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\DTO\ProductRequest;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * 상품·모듈 코드 관리 (운영자).
 *
 * 얇은 컨트롤러: 유효성 검사 → ProductService 호출 → 응답. 비즈니스 로직은 서비스에 위임.
 */
final class ProductController extends BaseAdminController
{
    /** GET /admin/products — 목록(상품·모듈 2탭). */
    public function index(): string
    {
        return $this->render('admin/products/index', [
            'title'      => '상품·모듈 관리',
            'activeMenu' => 'products',
            'products'   => service('productService')->list(),
            'modules'    => service('moduleService')->list(),
            'authenticationMethods' => $this->authenticationMethods(),
        ]);
    }

    /** GET /admin/products/new — 등록 폼(모듈 마스터에서 선택). */
    public function new(): string
    {
        return $this->render('admin/products/form', [
            'title'         => '상품 등록',
            'activeMenu'    => 'products',
            'product'       => null,
            'modules'       => [],
            'versions'      => [],
            'masterModules' => service('moduleService')->activeForSelect(),
            'licenseTypes'  => LicenseType::cases(),
            'periodCodes'   => PeriodCode::cases(),
            'authenticationMethods' => $this->authenticationMethods(),
        ]);
    }

    /** POST /admin/products — 생성. */
    public function create(): RedirectResponse
    {
        try {
            $id = service('productService')->create(ProductRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/products')->with('message', "상품이 등록되었습니다. (#{$id})");
    }

    /** GET /admin/products/{id}/edit — 수정 폼. */
    public function edit(int $id): string|RedirectResponse
    {
        $found = service('productService')->find($id);
        if ($found === null) {
            return redirect()->to('/admin/products')->with('error', '상품을 찾을 수 없습니다.');
        }

        return $this->render('admin/products/form', [
            'title'         => '상품 수정',
            'activeMenu'    => 'products',
            'product'       => $found['product'],
            'modules'       => $found['modules'],
            'versions'      => $found['versions'],
            'masterModules' => [],
            'licenseTypes'  => LicenseType::cases(),
            'periodCodes'   => PeriodCode::cases(),
            'authenticationMethods' => $this->authenticationMethods(),
        ]);
    }

    /** POST /admin/products/{id} — 수정. */
    public function update(int $id): RedirectResponse
    {
        try {
            service('productService')->update($id, ProductRequest::fromRequest($this->request));
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/products')->with('message', '상품이 수정되었습니다.');
    }

    /** POST /admin/products/{id}/delete — 삭제. */
    public function delete(int $id): RedirectResponse
    {
        service('productService')->delete($id);

        return redirect()->to('/admin/products')->with('message', '상품이 삭제되었습니다.');
    }

    /**
     * @return array<string, string>
     */
    private function authenticationMethods(): array
    {
        $methods = [];
        foreach (LicenseType::cases() as $type) {
            $methods[$type->value] = $type->authenticationMethodLabel();
        }

        return $methods;
    }
}
