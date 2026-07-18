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
 * 노드락 — 데스크톱 검증 도구(tools/licverify)용 무인증 유효성 확인.
 *
 * /api/v1/licenses/effectiveness(AITessera 포털용, JWT 필수)와 동일 로직이지만
 * 로그인 세션이 없는 독립 클라이언트가 호출할 수 있도록 인증 없이 노출한다.
 * license_key+host_id 조합이 사실상의 조회 키이며, RateLimitMiddleware(IP+path
 * 기준, 전역 적용)가 무차별 대입을 억제한다.
 */
final class NodeLockVerifyController
{
    public function __construct(
        private readonly Json $json,
        private readonly NodeLockAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/nodelock/verify', summary: '노드락 유효성 확인(무인증, 데스크톱 도구용)', security: [], tags: ['License'], responses: [
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
