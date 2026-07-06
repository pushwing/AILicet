<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\UserRole;
use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 서버렌더링(Admin/대행사/고객) 화면 인증 필터 — 세션 기반.
 *
 * 로그인 시 세션 `authUser`(id/role/aff)를 저장하고, 본 필터가 이를 확인해
 * Auth 홀더에 담는다. 필터 인자로 허용 역할을 지정하면 역할 인가까지 수행한다.
 * 예) `adminAuth:operator`
 */
final class AdminAuthFilter implements FilterInterface
{
    private const string LOGIN_ROUTE = '/admin/login';

    /**
     * @param list<string>|null $arguments 허용 역할 슬러그. null 이면 로그인만 요구.
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $authUser = session()->get('authUser');

        if (! is_array($authUser) || ! isset($authUser['id'], $authUser['role'])) {
            return redirect()->to(self::LOGIN_ROUTE);
        }

        $role = UserRole::tryFrom((int) $authUser['role']);
        if ($role === null) {
            session()->remove('authUser');

            return redirect()->to(self::LOGIN_ROUTE);
        }

        Auth::setUser(
            (int) $authUser['id'],
            $role,
            isset($authUser['aff']) ? (string) $authUser['aff'] : null,
        );

        if (! $this->hasAllowedRole($role, $arguments)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody('접근 권한이 없습니다.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null; // 응답 후 처리 없음.
    }

    /**
     * @param list<string>|null $arguments
     */
    private function hasAllowedRole(UserRole $role, ?array $arguments): bool
    {
        if (empty($arguments)) {
            return true;
        }

        foreach ($arguments as $slug) {
            if (UserRole::fromSlug($slug) === $role) {
                return true;
            }
        }

        return false;
    }
}
