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
 * 노드락 — 유효성 검증(hostId + licenseKey).
 */
final class EffectivenessController
{
    public function __construct(
        private readonly Json $json,
        private readonly NodeLockAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/licenses/effectiveness', summary: '노드락 유효성 검증', security: [['bearerAuth' => []]], tags: ['License'], responses: [
        new OA\Response(response: 200, description: '유효성 결과(valid/reason)'),
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

        return $this->json->success($this->service->effectiveness($licenseKey, $hostId));
    }
}
