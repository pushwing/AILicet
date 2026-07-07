<?php

declare(strict_types=1);

namespace App\Models;

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
}
