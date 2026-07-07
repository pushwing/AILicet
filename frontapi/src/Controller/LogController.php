<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Input;
use App\Http\Json;
use App\Support\LogQueue;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 로그 수집 — 큐에 적재 후 즉시 202 (DB 직접 쓰기 금지, 설계 로그 파이프라인).
 */
final class LogController
{
    public function __construct(
        private readonly Json $json,
        private readonly LogQueue $queue,
    ) {
    }

    #[OA\Post(path: '/api/v1/logs', summary: '로그 수집(큐 적재)', security: [['bearerAuth' => []]], tags: ['Log'], responses: [
        new OA\Response(response: 202, description: '큐 적재됨'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body    = Input::json($request);
        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            return $this->json->error('VALIDATION_ERROR', 'message 는 필수입니다.', 422);
        }

        $this->queue->push([
            'level'     => (string) ($body['level'] ?? 'info'),
            'source'    => isset($body['source']) ? (string) $body['source'] : null,
            'message'   => $message,
            'context'   => $body['context'] ?? null,
            'client_ip' => Input::clientIp($request),
            'user_id'   => $request->getAttribute('userId'),
            'logged_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->json->success(null, [], 202);
    }
}
