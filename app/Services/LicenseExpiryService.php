<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Models\LicenseModel;

/**
 * 라이센스 만료 관련 배치 유스케이스 — 만료 임박 알림 대상 조회 + 만료 종료 처리.
 */
final class LicenseExpiryService
{
    public function __construct(
        private readonly ?LicenseLifecycleService $lifecycle = null,
    ) {
    }

    /**
     * N일 후 만료되는 활성 라이센스 목록.
     *
     * @return list<array<string, mixed>>
     */
    public function expiringInDays(int $days): array
    {
        $target = date('Y-m-d', strtotime("+{$days} days"));

        /** @var list<array<string, mixed>> $rows */
        $rows = model(LicenseModel::class)
            ->where('status', LicenseStatus::Active->value)
            ->where('expire_date', $target)
            ->findAll();

        return $rows;
    }

    /**
     * 만료일이 지난 활성 라이센스를 종료 처리한다.
     *
     * @return list<int> 종료된 라이센스 id
     */
    public function terminateExpired(int $actorId = 0): array
    {
        /** @var list<array{id:int}> $rows */
        $rows = model(LicenseModel::class)
            ->select('id')
            ->where('status', LicenseStatus::Active->value)
            ->where('expire_date <', date('Y-m-d'))
            ->where('expire_date IS NOT NULL')
            ->findAll();

        $lifecycle  = $this->lifecycle ?? service('licenseLifecycleService');
        $terminated = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $lifecycle->terminate($id, $actorId, '만료 자동 종료');
            $terminated[] = $id;
        }

        return $terminated;
    }
}
