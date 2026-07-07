<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Input;
use App\Http\Json;
use App\Service\NodeLockAuthService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 노드락 — 사용정보 수집(원시 로그 적재 후 즉시 202).
 */
final class BypassController
{
    public function __construct(
        private readonly Json $json,
        private readonly NodeLockAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/licenses/bypass', summary: '사용정보 수집', security: [['bearerAuth' => []]], tags: ['License'], responses: [
        new OA\Response(response: 202, description: '수집 접수'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body       = Input::json($request);
        $licenseKey = trim((string) ($body['license_key'] ?? ''));
        $hostId     = trim((string) ($body['host_id'] ?? ''));
        if ($licenseKey === '' || $hostId === '') {
            return $this->json->error('VALIDATION_ERROR', 'license_key 와 host_id 는 필수입니다.', 422);
        }

        $this->service->bypass($licenseKey, $hostId, $body, Input::clientIp($request));

        return $this->json->success(null, [], 202);
    }
}
