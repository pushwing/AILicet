<?php

declare(strict_types=1);

namespace App\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OpenAPI 스펙(JSON) 생성 — src/ 어트리뷰트를 스캔.
 */
final class OpenApiController
{
    public function __construct(private readonly Psr17Factory $factory)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $openapi  = (new Generator())->generate([__DIR__ . '/..']);
        $json     = $openapi->toJson();
        $response = $this->factory->createResponse(200)->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write($json);

        return $response;
    }
}
