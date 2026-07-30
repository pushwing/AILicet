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
 * 플로팅 — 데스크톱 검증 도구(tools/licverify)용 무인증 유효성 확인(잔여 카운트/크레딧).
 *
 * /api/v1/floating/effectiveness(AITessera 포털용, JWT 필수)와 동일 로직을
 * 로그인 세션 없는 독립 클라이언트가 호출할 수 있도록 무인증으로 노출한다.
 */
final class FloatingVerifyController
{
    public function __construct(
        private readonly Json $json,
        private readonly FloatingAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/floating/verify', summary: '플로팅 유효성 확인(무인증, 데스크톱 도구용)', security: [], tags: ['Floating'], responses: [
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
