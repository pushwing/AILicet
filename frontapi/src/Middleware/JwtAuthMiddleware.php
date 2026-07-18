<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\InvalidTokenException;
use App\Exception\UnauthorizedException;
use App\Support\Jwt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AITessera JWT(Bearer) 검증 미들웨어.
 *
 * 공개 경로(PUBLIC_ROUTES, 메서드+경로 정확 일치)는 검증 없이 통과시키고,
 * 그 외에는 Bearer 토큰을 검증해 userId/role 을 요청 애트리뷰트에 주입한다.
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    /** @var list<array{0:string, 1:string}> */
    private const array PUBLIC_ROUTES = [
        ['GET', '/health'],
        ['GET', '/api/docs'],
        ['GET', '/api/v1/openapi.json'],
        ['POST', '/api/v1/nodelock/verify'],
    ];

    public function __construct(private readonly Jwt $jwt)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isPublic($request->getMethod(), $request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        $header = $request->getHeaderLine('Authorization');
        if (! str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException();
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            throw new InvalidTokenException('토큰이 비어 있습니다.');
        }

        $claims = $this->jwt->decode($token);
        if (! is_numeric($claims['sub'] ?? null)) {
            throw new InvalidTokenException('토큰에 유효한 사용자 식별자가 없습니다.');
        }

        $request = $request
            ->withAttribute('userId', (int) $claims['sub'])
            ->withAttribute('role', isset($claims['role']) ? (int) $claims['role'] : null)
            ->withAttribute('aff', isset($claims['aff']) ? (string) $claims['aff'] : null);

        return $handler->handle($request);
    }

    private function isPublic(string $method, string $path): bool
    {
        foreach (self::PUBLIC_ROUTES as [$m, $p]) {
            if ($method === $m && $path === $p) {
                return true;
            }
        }

        return false;
    }
}
