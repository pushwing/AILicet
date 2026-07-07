<?php

declare(strict_types=1);

namespace App;

use App\Middleware\ErrorHandlerMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoutingMiddleware;
use App\Support\Config;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\Relay;

/**
 * frontApi 애플리케이션 — DI 컨테이너 + PSR-15 미들웨어 파이프라인.
 */
final class App
{
    private function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function create(array $env): self
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([Config::class => Config::fromEnv($env)]);
        $builder->addDefinitions(require __DIR__ . '/container.php');

        return new self($builder->build());
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queue = [
            $this->container->get(ErrorHandlerMiddleware::class),
            $this->container->get(RateLimitMiddleware::class),
            $this->container->get(JwtAuthMiddleware::class),
            $this->container->get(RoutingMiddleware::class),
        ];

        return (new Relay($queue))->handle($request);
    }
}
