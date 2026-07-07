<?php

declare(strict_types=1);

use App\Middleware\RoutingMiddleware;
use App\Support\Config;
use App\Support\InMemoryRateLimiter;
use App\Support\Jwt;
use App\Support\InMemoryLogQueue;
use App\Support\LicenseVerifier;
use App\Support\LogQueue;
use App\Support\RateLimiter;
use App\Support\RawLogWriter;
use App\Support\RedisLogQueue;
use App\Support\RedisRateLimiter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Predis\Client as Redis;
use Psr\Container\ContainerInterface;

use function App\routes;
use function DI\autowire;
use function DI\factory;

/**
 * DI 컨테이너 정의.
 *
 * @return array<string, mixed>
 */
return [
    Psr17Factory::class => autowire(),

    Jwt::class => factory(static fn (Config $c): Jwt => new Jwt($c->jwtSecret)),

    LicenseVerifier::class => factory(static fn (Config $c): LicenseVerifier => new LicenseVerifier($c->licensePublicKey)),

    RawLogWriter::class => factory(static fn (Config $c): RawLogWriter => new RawLogWriter($c->rawLogPath)),

    // 로그 큐 — 테스트는 인메모리, 그 외 Redis
    LogQueue::class => factory(static function (ContainerInterface $ct, Config $c): LogQueue {
        if ($c->appEnv === 'testing') {
            return new InMemoryLogQueue();
        }

        return new RedisLogQueue($ct->get(Redis::class));
    }),

    Redis::class => factory(static fn (Config $c): Redis => new Redis([
        'scheme' => 'tcp',
        'host'   => $c->redisHost,
        'port'   => $c->redisPort,
    ])),

    // 테스트 환경은 인메모리, 그 외는 Redis
    RateLimiter::class => factory(static function (ContainerInterface $ct, Config $c): RateLimiter {
        if ($c->appEnv === 'testing') {
            return new InMemoryRateLimiter($c->rateLimitMax);
        }

        return new RedisRateLimiter($ct->get(Redis::class), $c->rateLimitMax, $c->rateLimitWindow);
    }),

    RoutingMiddleware::class => factory(
        static fn (ContainerInterface $ct): RoutingMiddleware => new RoutingMiddleware($ct, routes()),
    ),
];
