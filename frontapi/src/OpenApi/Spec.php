<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI 문서 기본 정보 + 보안 스킴.
 */
#[OA\Info(version: '1.0.0', title: 'AILicet frontApi', description: '클라이언트 프로그램용 라이선스 인증 API')]
#[OA\Server(url: '/', description: 'frontApi')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
final class Spec
{
}
