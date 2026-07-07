<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use App\Support\Database;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 헬스체크(공개) — DB 연결 상태 포함.
 */
final class HealthController
{
    public function __construct(
        private readonly Json $json,
        private readonly Database $db,
    ) {
    }

    #[OA\Get(path: '/health', summary: '헬스체크(DB 연결 포함)', tags: ['System'], responses: [
        new OA\Response(response: 200, description: '정상'),
        new OA\Response(response: 503, description: 'DB 연결 불가'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $dbOk = $this->db->ping();

        return $this->json->success([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db'     => $dbOk,
        ], [], $dbOk ? 200 : 503);
    }
}
