<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\Enums\UserRole;
use App\Exceptions\AitesseraException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 운영자 — AITessera 회원(계정) 관리.
 *
 * 로그인 운영자의 AITessera 액세스 토큰으로 회원 목록·상세·수정 및 계정 생성 API를 소비한다.
 */
final class AccountController extends BaseAdminController
{
    /** GET /admin/accounts — 목록 화면. */
    public function index(): string
    {
        if ($this->operatorToken() === null) {
            return $this->render('admin/accounts/unavailable', ['title' => '회원 계정', 'activeMenu' => 'accounts']);
        }

        return $this->render('admin/accounts/index', [
            'title'      => '회원 계정',
            'activeMenu' => 'accounts',
            'roles'      => UserRole::cases(),
        ]);
    }

    /** GET /admin/accounts/data — AITessera 회원 목록 프록시(JSON). */
    public function data(): ResponseInterface
    {
        $token = $this->operatorToken();
        if ($token === null) {
            return $this->jsonError('AITESSERA_NOT_CONNECTED', 'AITessera 로그인 세션이 필요합니다.', 401);
        }

        $query = array_filter([
            'page'      => (int) ($this->request->getGet('page') ?? 1),
            'per_page'  => (int) ($this->request->getGet('per_page') ?? 20),
            'role'      => $this->request->getGet('role'),
            'is_active' => $this->request->getGet('is_active'),
            'q'         => $this->request->getGet('q'),
            'sort'      => $this->request->getGet('sort'),
        ], static fn ($v) => $v !== null && $v !== '');

        try {
            $result = service('aitesseraClient')->listUsers($token, $query);
        } catch (AitesseraException $e) {
            if ($this->isTokenExpired($e)) {
                return $this->reloginJson();
            }

            return $this->jsonError($e->errorCode(), $e->getMessage(), $e->httpStatusCode());
        }

        return $this->response->setJSON(['status' => 'success', 'data' => $result['items'], 'meta' => $result['meta']]);
    }

    private function jsonError(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => 'error', 'code' => $code, 'message' => $message]);
    }

    /** AITessera 응답이 토큰 만료·무효(재로그인 필요)인가. */
    private function isTokenExpired(AitesseraException $e): bool
    {
        return $e->httpStatusCode() === 401
            || in_array($e->errorCode(), ['TOKEN_EXPIRED', 'INVALID_TOKEN', 'UNAUTHORIZED'], true);
    }

    /** 토큰 만료 — 세션을 비우고 로그인으로 이동(HTML). */
    private function forceRelogin(): RedirectResponse
    {
        session()->remove('authUser');

        return redirect()->to('/admin/login')->with('error', '세션(토큰)이 만료되었습니다. 다시 로그인해 주세요.');
    }

    /** 토큰 만료 — 세션을 비우고 재로그인 위치를 알리는 JSON(AJAX). */
    private function reloginJson(): ResponseInterface
    {
        session()->remove('authUser');

        return $this->response->setStatusCode(401)->setJSON([
            'status'   => 'error',
            'code'     => 'SESSION_EXPIRED',
            'message'  => '세션(토큰)이 만료되었습니다. 다시 로그인해 주세요.',
            'redirect' => '/admin/login',
        ]);
    }

    /** GET /admin/accounts/new — 계정 생성 폼. */
    public function new(): string|RedirectResponse
    {
        if ($this->operatorToken() === null) {
            return redirect()->to('/admin/accounts');
        }

        return $this->render('admin/accounts/form', [
            'title'      => '계정 생성',
            'activeMenu' => 'accounts',
            'account'    => null,
            'roles'      => UserRole::cases(),
        ]);
    }

    /** POST /admin/accounts — 계정 생성(운영자/대행사/일반회원). */
    public function create(): RedirectResponse
    {
        $token = $this->operatorToken();
        if ($token === null) {
            return redirect()->to('/admin/accounts');
        }

        $aff = (string) (session()->get('authUser')['aff'] ?? 'ailicet');
        try {
            service('aitesseraClient')->createAccount($token, [
                'email'       => (string) $this->request->getPost('email'),
                'password'    => (string) $this->request->getPost('password'),
                'role'        => (int) $this->request->getPost('role'),
                'name'        => (string) $this->request->getPost('name'),
                'contact'     => (string) $this->request->getPost('contact'),
                'company'     => $this->request->getPost('company') ?: null,
                'affiliation' => $aff,
            ]);
        } catch (AitesseraException $e) {
            if ($this->isTokenExpired($e)) {
                return $this->forceRelogin();
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/accounts')->with('message', '계정이 생성되었습니다.');
    }

    /** GET /admin/accounts/{id}/edit — 회원 수정 폼. */
    public function edit(int $id): string|RedirectResponse
    {
        $token = $this->operatorToken();
        if ($token === null) {
            return redirect()->to('/admin/accounts');
        }

        try {
            $account = service('aitesseraClient')->getUser($token, $id);
        } catch (AitesseraException $e) {
            if ($this->isTokenExpired($e)) {
                return $this->forceRelogin();
            }

            return redirect()->to('/admin/accounts')->with('error', $e->getMessage());
        }

        return $this->render('admin/accounts/form', [
            'title'      => '회원 수정',
            'activeMenu' => 'accounts',
            'account'    => $account,
            'roles'      => UserRole::cases(),
        ]);
    }

    /** POST /admin/accounts/{id} — 회원 정보 수정(PATCH 위임). */
    public function update(int $id): RedirectResponse
    {
        $token = $this->operatorToken();
        if ($token === null) {
            return redirect()->to('/admin/accounts');
        }

        // 값이 입력된 필드만 부분 갱신
        $data = array_filter([
            'name'      => $this->request->getPost('name'),
            'contact'   => $this->request->getPost('contact'),
            'company'   => $this->request->getPost('company'),
            'is_active' => $this->request->getPost('is_active') !== null ? (int) $this->request->getPost('is_active') : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $data['password'] = $password;
        }

        try {
            service('aitesseraClient')->updateUser($token, $id, $data);
        } catch (AitesseraException $e) {
            if ($this->isTokenExpired($e)) {
                return $this->forceRelogin();
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/accounts')->with('message', '회원 정보가 수정되었습니다.');
    }

    /** 로그인 운영자의 AITessera 액세스 토큰. */
    private function operatorToken(): ?string
    {
        $token = session()->get('authUser')['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
