<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;
use PDO;

/**
 * 플로팅 라이센스 조회·활성화·분석 리포지토리 — PDO prepared statement 전용.
 */
final class FloatingRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** 관리키로 플로팅 라이센스 id. */
    public function licenseIdByKey(string $licenseKey): ?int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT h.license_id
             FROM license_history h JOIN licenses l ON l.id = h.license_id
             WHERE h.license_key = :k AND h.type IN ('issue','reissue') AND l.license_type = 'floating'
             ORDER BY h.id DESC LIMIT 1",
        );
        $stmt->execute([':k' => $licenseKey]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $licenseId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM licenses WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $licenseId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * 아직 만료되지 않은 최신 활성화(현재 점유).
     *
     * @return array<string, mixed>|null
     */
    public function activeActivation(int $licenseId, string $now): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM floating_activations
             WHERE license_id = :id AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute([':id' => $licenseId, ':now' => $now]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insertActivation(int $licenseId, string $hostId, string $activatedAt, string $expiresAt): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO floating_activations (license_id, host_id, activated_at, expires_at, created_at)
             VALUES (:id, :host, :act, :exp, :now)',
        );
        $stmt->execute([':id' => $licenseId, ':host' => $hostId, ':act' => $activatedAt, ':exp' => $expiresAt, ':now' => $activatedAt]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function insertAnalysis(int $licenseId, string $analysisKey, string $hostId, string $now): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO analysis_logs (license_id, analysis_key, host_id, status, amount, created_at)
             VALUES (:id, :ak, :host, 'started', 0, :now)",
        );
        $stmt->execute([':id' => $licenseId, ':ak' => $analysisKey, ':host' => $hostId, ':now' => $now]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAnalysis(string $analysisKey): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM analysis_logs WHERE analysis_key = :ak LIMIT 1');
        $stmt->execute([':ak' => $analysisKey]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function completeAnalysis(int $id, string $status, int $amount, string $now): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE analysis_logs SET status = :s, amount = :amt, ended_at = :now WHERE id = :id',
        );
        $stmt->execute([':s' => $status, ':amt' => $amount, ':now' => $now, ':id' => $id]);
    }

    /** 완료된 분석 건수(카운트제 사용량). */
    public function usedCount(int $licenseId): int
    {
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM analysis_logs WHERE license_id = :id AND status = 'completed'");
        $stmt->execute([':id' => $licenseId]);

        return (int) $stmt->fetchColumn();
    }

    /** 완료된 분석의 크레딧 합(크레딧제 사용량). */
    public function usedCredit(int $licenseId): int
    {
        $stmt = $this->pdo()->prepare("SELECT COALESCE(SUM(amount),0) FROM analysis_logs WHERE license_id = :id AND status = 'completed'");
        $stmt->execute([':id' => $licenseId]);

        return (int) $stmt->fetchColumn();
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }
}
