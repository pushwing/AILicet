<?php

declare(strict_types=1);

namespace App\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * 표준 JSON 응답 빌더.
 *
 * 성공: { "status":"success", "data":..., "meta":... }
 * 실패: { "status":"error", "code":"...", "message":"..." }
 */
final class Json
{
    public function __construct(private readonly Psr17Factory $factory)
    {
    }

    /**
     * @param mixed                $data
     * @param array<string, mixed> $meta
     */
    public function success(mixed $data = null, array $meta = [], int $status = 200): ResponseInterface
    {
        $body = ['status' => 'success', 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return $this->write($status, $body);
    }

    public function error(string $code, string $message, int $status = 400): ResponseInterface
    {
        return $this->write($status, ['status' => 'error', 'code' => $code, 'message' => $message]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function write(int $status, array $body): ResponseInterface
    {
        $json     = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $response = $this->factory->createResponse($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write($json);

        return $response;
    }
}
