<?php

declare(strict_types=1);

namespace App\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Swagger UI 문서 페이지(공개).
 */
final class DocsController
{
    public function __construct(private readonly Psr17Factory $factory)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="ko">
            <head>
                <meta charset="utf-8">
                <title>AILicet frontApi — API 문서</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui-bundle.js"></script>
                <script>
                    window.ui = SwaggerUIBundle({ url: '/api/v1/openapi.json', dom_id: '#swagger-ui' });
                </script>
            </body>
            </html>
            HTML;

        $response = $this->factory->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($html);

        return $response;
    }
}
