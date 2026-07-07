<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * PDO 팩토리 — prepared statement 전용(raw query 금지).
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->dbHost,
                $this->config->dbPort,
                $this->config->dbName,
            );

            $this->pdo = new PDO($dsn, $this->config->dbUser, $this->config->dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // 진짜 prepared statement
            ]);
        }

        return $this->pdo;
    }

    /** 연결 상태 확인(헬스체크). */
    public function ping(): bool
    {
        try {
            return $this->pdo()->query('SELECT 1')->fetchColumn() === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
