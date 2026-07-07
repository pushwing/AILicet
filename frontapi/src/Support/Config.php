<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 환경변수 기반 설정.
 */
final readonly class Config
{
    public function __construct(
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPass,
        public string $jwtSecret,
        public string $redisHost,
        public int $redisPort,
        public bool $rateLimitEnabled,
        public int $rateLimitMax,
        public int $rateLimitWindow,
        public string $appEnv,
        public string $licensePublicKey,
        public string $rawLogPath,
    ) {
    }

    /**
     * @param array<string, mixed> $env
     */
    public static function fromEnv(array $env): self
    {
        $get = static fn (string $key, string $default = ''): string => (string) ($env[$key] ?? $default);

        return new self(
            dbHost: $get('DB_HOST', '127.0.0.1'),
            dbPort: (int) $get('DB_PORT', '3306'),
            dbName: $get('DB_NAME', 'ailicet'),
            dbUser: $get('DB_USER', 'root'),
            dbPass: $get('DB_PASS'),
            jwtSecret: $get('JWT_SECRET'),
            redisHost: $get('REDIS_HOST', '127.0.0.1'),
            redisPort: (int) $get('REDIS_PORT', '6379'),
            rateLimitEnabled: filter_var($get('RATE_LIMIT_ENABLED', 'true'), FILTER_VALIDATE_BOOL),
            rateLimitMax: (int) $get('RATE_LIMIT_MAX', '60'),
            rateLimitWindow: (int) $get('RATE_LIMIT_WINDOW', '60'),
            appEnv: $get('APP_ENV', 'production'),
            licensePublicKey: $get('LICENSE_ED25519_PUBLIC_KEY'),
            rawLogPath: $get('RAW_LOG_PATH', __DIR__ . '/../../var/logs/raw'),
        );
    }
}
