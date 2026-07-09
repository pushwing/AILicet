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

    /**
     * 여러 라이센스 키를 한 번의 쿼리로 소속 라이센스 ID 에 매핑한다(N+1 방지).
     * 키별로 가장 최근(id DESC) 발급·재발급 이력의 license_id 를 취한다. 없는 키는 결과에서 제외.
     *
     * @param list<string> $keys
     *
     * @return array<string, int> license_key => license_id
     */
    public function licenseIdsByKeys(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn (string $k): bool => $k !== '')));
        if ($keys === []) {
            return [];
        }

        $map = [];
        // 대량 키를 대비해 IN 절을 500개 단위로 청크 처리(쿼리 크기·플레이스홀더 한계 보호).
        foreach (array_chunk($keys, 500) as $chunk) {
            /** @var list<array{license_key:string, license_id:int}> $rows */
            $rows = $this->select('license_key, license_id')
                ->whereIn('license_key', $chunk)
                ->whereIn('type', [HistoryType::Issue->value, HistoryType::Reissue->value])
                ->orderBy('id', 'DESC')
                ->findAll();

            foreach ($rows as $row) {
                // id DESC 정렬이므로 각 키의 첫 등장이 최신 이력이다.
                $map[$row['license_key']] ??= (int) $row['license_id'];
            }
        }

        return $map;
    }
}
