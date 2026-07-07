<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseAdminController;
use App\Enums\UserRole;
use App\Exceptions\DomainException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Admin/대행사/고객 공용 로그인·로그아웃.
 *
 * 인증은 AITessera(JWT) 에 위임한다. 로그인 성공 시 반환된 Access Token 을
 * JwtLibrary 로 검증·해석해 세션 authUser(id/role/aff/name)에 담는다.
 * 로컬 개발(AITessera 미설정 + development)에서는 데모 로그인으로 UI 를 확인할 수 있다.
 */
final class AuthController extends BaseAdminController
{
    /** GET /admin/login — 로그인 화면. */
    public function showLogin(): string|RedirectResponse
    {
        if (session()->has('authUser')) {
            return redirect()->to('/admin');
        }

        return $this->render('admin/auth/login', [
            'title'     => '로그인',
            'notice'    => $this->devNotice(),
            'demoLogin' => $this->isDemoLogin(),
        ]);
    }

    /** POST /admin/login — 인증 처리. */
    public function login(): string|RedirectResponse
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[4]',
        ];
        if (! $this->validate($rules)) {
            return $this->renderLoginError('이메일과 비밀번호를 확인해 주세요.');
        }

        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');

        $base = (string) env('aitessera.baseURL');

        try {
            if ($base !== '') {
                $authUser = $this->authenticateWithAitessera($base, $email, $password);
            } elseif (ENVIRONMENT === 'development') {
                $authUser = $this->demoUser($email);
            } else {
                return $this->renderLoginError('인증 서버(AITessera)가 설정되지 않았습니다.');
            }
        } catch (DomainException $e) {
            return $this->renderLoginError($e->getMessage());
        } catch (Throwable) {
            return $this->renderLoginError('인증 서버와 통신할 수 없습니다.');
        }

        if ($authUser === null) {
            return $this->renderLoginError('이메일 또는 비밀번호가 올바르지 않습니다.');
        }

        session()->set('authUser', $authUser);

        return redirect()->to('/admin');
    }

    /** GET /admin/logout — 로그아웃. */
    public function logout(): RedirectResponse
    {
        session()->remove('authUser');

        return redirect()->to('/admin/login');
    }

    /**
     * AITessera 로그인 API 로 토큰을 발급받아 사용자 정보를 해석한다.
     *
     * @return array{id:int, name:string, role:int, aff:?string, token:string}|null
     */
    private function authenticateWithAitessera(string $base, string $email, string $password): ?array
    {
        $response = service('curlrequest')->post(rtrim($base, '/') . '/api/v1/tokens', [
            'json'        => ['email' => $email, 'password' => $password],
            'timeout'     => 5,
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body  = json_decode((string) $response->getBody(), true);
        $token = is_array($body) ? $this->extractToken($body) : null;
        if ($token === null) {
            return null;
        }

        $claims = service('jwt')->decode($token); // 서명·만료 검증(공유 시크릿)

        return [
            'id'    => (int) ($claims['sub'] ?? 0),
            'name'  => $email,
            'role'  => (int) ($claims['role'] ?? UserRole::Member->value),
            'aff'   => isset($claims['aff']) ? (string) $claims['aff'] : null,
            'token' => $token, // AITessera 운영자 API 호출용 액세스 토큰
        ];
    }

    /**
     * 응답 본문에서 Access Token 문자열을 찾는다(래핑 형태 방어).
     *
     * @param array<string, mixed> $body
     */
    private function extractToken(array $body): ?string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        foreach (['access_token', 'accessToken', 'token'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * 개발용 데모 사용자. AITessera 없이 UI 를 확인하기 위한 용도.
     * 이메일로 역할을 구분한다: agency* → 대행사(id 2), client* → 고객(id 3), 그 외 → 운영자(id 1).
     *
     * @return array{id:int, name:string, role:int, aff:string}
     */
    private function demoUser(string $email): array
    {
        $lower = strtolower($email);
        [$id, $role] = match (true) {
            str_starts_with($lower, 'agency') => [2, UserRole::Agency],
            str_starts_with($lower, 'client') => [3, UserRole::Member],
            default                           => [1, UserRole::Operator],
        };

        return ['id' => $id, 'name' => $email, 'role' => $role->value, 'aff' => 'ailicet'];
    }

    private function renderLoginError(string $message): string
    {
        return $this->render('admin/auth/login', [
            'title'     => '로그인',
            'error'     => $message,
            'email'     => (string) $this->request->getPost('email'),
            'notice'    => $this->devNotice(),
            'demoLogin' => $this->isDemoLogin(),
        ]);
    }

    /** 개발용 데모 로그인 활성화 여부(development + AITessera 미설정). */
    private function isDemoLogin(): bool
    {
        return ENVIRONMENT === 'development' && (string) env('aitessera.baseURL') === '';
    }

    private function devNotice(): ?string
    {
        if ($this->isDemoLogin()) {
            return '개발 모드: AITessera 미설정. 아래 빠른 로그인으로 운영자/대행사/일반회원 역할에 바로 접속할 수 있습니다.';
        }

        return null;
    }
}
