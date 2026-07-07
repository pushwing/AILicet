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
 * 플로팅 — 분석 시작(analysis_key 발급).
 */
final class AnalysisStartController
{
    public function __construct(
        private readonly Json $json,
        private readonly FloatingAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/floating/analysis/start', summary: '분석 시작', security: [['bearerAuth' => []]], tags: ['Floating'], responses: [
        new OA\Response(response: 200, description: 'analysis_key'),
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

        return $this->json->success($this->service->analysisStart($licenseKey, $hostId));
    }
}
