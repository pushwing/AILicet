<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UnauthorizedException;
use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * REST API 인증 필터 — `Authorization: Bearer <JWT>` 검증.
 *
 * AITessera 발급 토큰을 JwtLibrary(HS256)로 검증한 뒤 Auth 홀더에 사용자 정보를 담는다.
 * 필터 인자로 허용 역할을 지정하면 역할 인가까지 수행한다. 예) `jwt:operator`, `jwt:operator,agency`
 */
final class JwtAuthFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments 허용 역할 슬러그(operator/agency/member). null 이면 인증만.
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        try {
            $claims = $this->authenticate($request);
            $role   = $this->resolveRole($claims);

            Auth::setUser(
                (int) $claims['sub'],
                $role,
                isset($claims['aff']) ? (string) $claims['aff'] : null,
            );

            $this->authorize($role, $arguments);
        } catch (DomainException $e) {
            return $this->deny($e);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null; // 응답 후 처리 없음.
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticate(RequestInterface $request): array
    {
        $header = $request->getHeaderLine('Authorization');
        if (! str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException();
        }

        $jwt = trim(substr($header, 7));
        if ($jwt === '') {
            throw new InvalidTokenException('토큰이 비어 있습니다.');
        }

        return service('jwt')->decode($jwt);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function resolveRole(array $claims): UserRole
    {
        if (! is_numeric($claims['sub'] ?? null)) {
            throw new InvalidTokenException('토큰에 유효한 사용자 식별자가 없습니다.');
        }

        $role = is_numeric($claims['role'] ?? null)
            ? UserRole::tryFrom((int) $claims['role'])
            : null;

        if ($role === null) {
            throw new InvalidTokenException('토큰에 유효한 회원구분이 없습니다.');
        }

        return $role;
    }

    /**
     * @param list<string>|null $arguments
     */
    private function authorize(UserRole $role, ?array $arguments): void
    {
        if (empty($arguments)) {
            return; // 인증만 요구.
        }

        foreach ($arguments as $slug) {
            if (UserRole::fromSlug($slug) === $role) {
                return;
            }
        }

        throw new ForbiddenException();
    }

    private function deny(DomainException $e): ResponseInterface
    {
        Auth::clear();

        return service('response')
            ->setStatusCode($e->httpStatusCode())
            ->setJSON([
                'status'  => 'error',
                'code'    => $e->errorCode(),
                'message' => $e->getMessage(),
            ]);
    }
}
