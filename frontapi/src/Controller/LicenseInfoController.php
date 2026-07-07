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
 * 노드락 — 라이센스 상세 조회.
 */
final class LicenseInfoController
{
    public function __construct(
        private readonly Json $json,
        private readonly NodeLockAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/licenses/info', summary: '라이센스 상세 조회', security: [['bearerAuth' => []]], tags: ['License'], responses: [
        new OA\Response(response: 200, description: '라이센스 정보'),
        new OA\Response(response: 404, description: '라이센스 없음'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body       = Input::json($request);
        $licenseKey = trim((string) ($body['license_key'] ?? ''));
        if ($licenseKey === '') {
            return $this->json->error('VALIDATION_ERROR', 'license_key 는 필수입니다.', 422);
        }

        $info = $this->service->info($licenseKey);
        if ($info === null) {
            return $this->json->error('NOT_FOUND', '라이센스를 찾을 수 없습니다.', 404);
        }

        return $this->json->success($info);
    }
}
