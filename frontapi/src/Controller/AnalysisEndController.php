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
 * 플로팅 — 분석 종료(성공 시 사용량 차감, 중복 방지).
 */
final class AnalysisEndController
{
    public function __construct(
        private readonly Json $json,
        private readonly FloatingAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/floating/analysis/end', summary: '분석 종료(사용량 차감)', security: [['bearerAuth' => []]], tags: ['Floating'], responses: [
        new OA\Response(response: 200, description: '차감 결과(deducted/amount)'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body        = Input::json($request);
        $analysisKey = trim((string) ($body['analysis_key'] ?? ''));
        if ($analysisKey === '') {
            return $this->json->error('VALIDATION_ERROR', 'analysis_key 는 필수입니다.', 422);
        }
        $success = (bool) ($body['success'] ?? true);
        $amount  = (int) ($body['amount'] ?? 1);

        return $this->json->success($this->service->analysisEnd($analysisKey, $success, $amount));
    }
}
