<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\ApiException;
use App\Http\Json;
use App\Support\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * 최외곽 에러 핸들러 — ApiException 은 표준 에러 응답으로, 그 외 예외는 500 으로 변환.
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Json $json,
        private readonly Config $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ApiException $e) {
            return $this->json->error($e->errorCode(), $e->getMessage(), $e->statusCode());
        } catch (Throwable $e) {
            // 운영 환경에서는 내부 메시지 노출 금지
            $message = $this->config->appEnv === 'production' ? '서버 내부 오류가 발생했습니다.' : $e->getMessage();

            return $this->json->error('INTERNAL_ERROR', $message, 500);
        }
    }
}
