<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;
use PDO;

/**
 * 라이센스 조회 리포지토리 — PDO prepared statement 전용(raw 문자열 조합 금지).
 */
final class LicenseRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** 관리키로 라이센스 id 를 찾는다(발급/재발급 이력 기준). */
    public function licenseIdByKey(string $licenseKey): ?int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT license_id FROM license_history
             WHERE license_key = :k AND type IN ('issue','reissue')
             ORDER BY id DESC LIMIT 1",
        );
        $stmt->execute([':k' => $licenseKey]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** 라이센스의 현재 유효 키(최신 발급/재발급). */
    public function currentKey(int $licenseId): ?string
    {
        $stmt = $this->pdo()->prepare(
            "SELECT license_key FROM license_history
             WHERE license_id = :id AND type IN ('issue','reissue')
             ORDER BY id DESC LIMIT 1",
        );
        $stmt->execute([':id' => $licenseId]);
        $key = $stmt->fetchColumn();

        return $key === false ? null : (string) $key;
    }

    /**
     * 라이센스 + 상품 정보.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $licenseId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT l.*, p.name AS product_name, p.product_code
             FROM licenses l
             LEFT JOIN products p ON p.id = l.product_id
             WHERE l.id = :id AND l.deleted_at IS NULL
             LIMIT 1',
        );
        $stmt->execute([':id' => $licenseId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }
}
