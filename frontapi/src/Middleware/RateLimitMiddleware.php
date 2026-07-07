<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\RateLimitedException;
use App\Support\Config;
use App\Support\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 클라이언트 IP 기준 요청 빈도 제한.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Config $config,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->config->rateLimitEnabled) {
            return $handler->handle($request);
        }

        $key = $this->clientIp($request) . ':' . $request->getUri()->getPath();
        if (! $this->limiter->hit($key)) {
            throw new RateLimitedException();
        }

        return $handler->handle($request);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return (string) ($server['REMOTE_ADDR'] ?? 'unknown');
    }
}
