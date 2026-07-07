<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\NotFoundException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastRoute\simpleDispatcher;

/**
 * fast-route 라우팅 최종 핸들러 — 매칭된 __invoke 컨트롤러를 컨테이너에서 해석해 실행한다.
 */
final class RoutingMiddleware implements MiddlewareInterface
{
    private Dispatcher $dispatcher;

    /**
     * @param list<array{0:string, 1:string, 2:class-string}> $routes
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $routes,
    ) {
        $this->dispatcher = simpleDispatcher(static function (RouteCollector $r) use ($routes): void {
            foreach ($routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        if ($result[0] !== Dispatcher::FOUND) {
            throw new NotFoundException();
        }

        /** @var class-string $controllerClass */
        $controllerClass = $result[1];
        /** @var array<string, string> $vars */
        $vars = $result[2];

        $controller = $this->container->get($controllerClass);
        $request    = $request->withAttribute('routeParams', $vars);

        /** @var callable(ServerRequestInterface): ResponseInterface $controller */
        return $controller($request);
    }
}
