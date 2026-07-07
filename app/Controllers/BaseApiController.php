<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Enums\UserRole;
use App\Libraries\Auth;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * REST API 컨트롤러 기반 클래스.
 *
 * JwtAuthFilter 가 담아둔 인증 정보를 authUserId()/authRole() 로 꺼내 쓰고,
 * 표준 응답 포맷(success/error)을 제공한다.
 */
abstract class BaseApiController extends Controller
{
    /** 인증된 사용자 ID (미인증 시 0). */
    protected function authUserId(): int
    {
        return Auth::userId() ?? 0;
    }

    protected function authRole(): ?UserRole
    {
        return Auth::role();
    }

    /**
     * 성공 응답.
     *
     * @param mixed                $data
     * @param array<string, mixed> $meta
     */
    protected function success(mixed $data = null, array $meta = [], int $statusCode = 200): ResponseInterface
    {
        $body = ['status' => 'success', 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return $this->response->setStatusCode($statusCode)->setJSON($body);
    }

    /**
     * 실패 응답.
     */
    protected function error(string $code, string $message, int $statusCode = 400): ResponseInterface
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ]);
    }
}
