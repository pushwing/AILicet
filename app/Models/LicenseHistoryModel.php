<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HistoryType;
use CodeIgniter\Model;

/**
 * 라이센스 이력(license_history) 모델. created_at 만 사용(수정 없음).
 */
final class LicenseHistoryModel extends Model
{
    protected $table         = 'license_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // 수정 컬럼 없음
    protected $allowedFields = [
        'license_id', 'type', 'host_id', 'license_sn', 'license_key', 'contents', 'created_by',
    ];

    /**
     * 라이센스별 이력(최신순).
     *
     * @return list<array<string, mixed>>
     */
    public function byLicense(int $licenseId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->where('license_id', $licenseId)->orderBy('id', 'DESC')->findAll();

        return $rows;
    }

    /**
     * 라이센스 키(발급·재발급 이력)로 소속 라이센스 ID 를 역추적한다. 없으면 null.
     */
    public function licenseIdByKey(string $key): ?int
    {
        /** @var array{license_id:int}|null $row */
        $row = $this->select('license_id')
            ->where('license_key', $key)
            ->whereIn('type', [HistoryType::Issue->value, HistoryType::Reissue->value])
            ->orderBy('id', 'DESC')
            ->first();

        return $row !== null ? (int) $row['license_id'] : null;
    }
}
