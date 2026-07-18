<?php

declare(strict_types=1);

namespace App;

use App\Controller\ActivationController;
use App\Controller\AnalysisEndController;
use App\Controller\AnalysisStartController;
use App\Controller\BypassController;
use App\Controller\DocsController;
use App\Controller\EffectivenessController;
use App\Controller\FloatingEffectivenessController;
use App\Controller\FloatingVerifyController;
use App\Controller\HealthController;
use App\Controller\LicenseInfoController;
use App\Controller\LogController;
use App\Controller\NodeLockVerifyController;
use App\Controller\OpenApiController;
use App\Controller\PingController;

/**
 * 라우트 정의 — [HTTP 메서드, 경로, 핸들러 클래스].
 *
 * 핸들러는 컨테이너에서 해석되는 __invoke 컨트롤러.
 *
 * @return list<array{0:string, 1:string, 2:class-string}>
 */
function routes(): array
{
    return [
        ['GET', '/health', HealthController::class],
        ['GET', '/api/docs', DocsController::class],
        ['GET', '/api/v1/openapi.json', OpenApiController::class],
        ['GET', '/api/v1/ping', PingController::class],

        // 노드락 인증
        ['POST', '/api/v1/licenses/info', LicenseInfoController::class],
        ['POST', '/api/v1/licenses/effectiveness', EffectivenessController::class],
        ['POST', '/api/v1/licenses/bypass', BypassController::class],
        ['POST', '/api/v1/nodelock/verify', NodeLockVerifyController::class],

        // 플로팅 인증
        ['POST', '/api/v1/floating/activation', ActivationController::class],
        ['POST', '/api/v1/floating/effectiveness', FloatingEffectivenessController::class],
        ['POST', '/api/v1/floating/verify', FloatingVerifyController::class],
        ['POST', '/api/v1/floating/analysis/start', AnalysisStartController::class],
        ['POST', '/api/v1/floating/analysis/end', AnalysisEndController::class],

        // 로그 수집
        ['POST', '/api/v1/logs', LogController::class],
    ];
}
