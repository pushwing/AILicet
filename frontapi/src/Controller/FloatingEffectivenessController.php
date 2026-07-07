<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Input;
use App\Http\Json;
use App\Service\FloatingAuthService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 플로팅 — 유효성 + 잔여 카운트/크레딧.
 */
final class FloatingEffectivenessController
{
    public function __construct(
        private readonly Json $json,
        private readonly FloatingAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/floating/effectiveness', summary: '플로팅 유효성(잔여 카운트/크레딧)', security: [['bearerAuth' => []]], tags: ['Floating'], responses: [
        new OA\Response(response: 200, description: '유효성 결과(valid/remaining)'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body       = Input::json($request);
        $licenseKey = trim((string) ($body['license_key'] ?? ''));
        if ($licenseKey === '') {
            return $this->json->error('VALIDATION_ERROR', 'license_key 는 필수입니다.', 422);
        }

        return $this->json->success($this->service->effectiveness($licenseKey));
    }
}
