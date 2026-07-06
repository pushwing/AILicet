<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\LicenseHistoryModel;
use App\Models\LicenseModel;
use RuntimeException;
use Throwable;

/**
 * 라이센스 생명주기 관리 — 상태 전이·연장·재발급.
 *
 * 모든 변경은 license_history 이력으로 추적하고, DB 트랜잭션은 이 서비스(레이어)에서
 * 관리한다(예외 시 롤백). 상태 전이 규칙은 LicenseStatus::canTransitionTo() 로 강제한다.
 */
final class LicenseLifecycleService
{
    public function __construct(
        private ?NodeLockLicenseService $nodeLock = null,
    ) {
    }

    /** 정지 (Active → Suspended). */
    public function suspend(int $licenseId, int $actorId, string $reason = ''): void
    {
        $this->changeStatus($licenseId, LicenseStatus::Suspended, $actorId, $reason ?: '라이센스 정지');
    }

    /** 정지 해제 (Suspended → Active). */
    public function resume(int $licenseId, int $actorId, string $reason = ''): void
    {
        $this->changeStatus($licenseId, LicenseStatus::Active, $actorId, $reason ?: '라이센스 정지 해제');
    }

    /** 종료 (→ Terminated). */
    public function terminate(int $licenseId, int $actorId, string $reason = ''): void
    {
        $this->changeStatus($licenseId, LicenseStatus::Terminated, $actorId, $reason ?: '라이센스 종료');
    }

    /** 보관 (→ Archived). */
    public function archive(int $licenseId, int $actorId, string $reason = ''): void
    {
        $this->changeStatus($licenseId, LicenseStatus::Archived, $actorId, $reason ?: '라이센스 보관');
    }

    /**
     * 상태 전이(전이 규칙 강제 + 이력 기록). 트랜잭션.
     *
     * @throws InvalidStateTransitionException 허용되지 않은 전이
     * @throws RuntimeException                라이센스 없음
     */
    public function changeStatus(int $licenseId, LicenseStatus $target, int $actorId, string $reason): void
    {
        $license = $this->mustFind($licenseId);
        $current = LicenseStatus::from((string) $license['status']);

        if ($current === $target) {
            return; // 멱등
        }
        if (! $current->canTransitionTo($target)) {
            throw new InvalidStateTransitionException(
                sprintf("'%s' → '%s' 상태로 전이할 수 없습니다.", $current->label(), $target->label()),
            );
        }

        $this->transactional(function () use ($licenseId, $target, $actorId, $reason, $current): void {
            model(LicenseModel::class)->update($licenseId, ['status' => $target->value]);
            $this->addHistory($licenseId, HistoryType::StatusChange, $actorId, sprintf(
                '%s → %s%s',
                $current->label(),
                $target->label(),
                $reason !== '' ? " ({$reason})" : '',
            ));
        });
    }

    /**
     * 만료일 연장. 트랜잭션.
     *
     * @throws RuntimeException 라이센스 없음
     */
    public function extend(int $licenseId, string $newExpireDate, int $actorId): void
    {
        $license = $this->mustFind($licenseId);
        $old     = $license['expire_date'] !== null ? (string) $license['expire_date'] : '무기한';

        $this->transactional(function () use ($licenseId, $newExpireDate, $actorId, $old): void {
            model(LicenseModel::class)->update($licenseId, ['expire_date' => $newExpireDate]);
            $this->addHistory($licenseId, HistoryType::StatusChange, $actorId, "만료일 연장: {$old} → {$newExpireDate}");
        });
    }

    /**
     * 재발급 — 새 관리키 발급, 호스트ID 변경 가능. 이전 키는 폐기(최신 키만 유효).
     * 노드락은 서명 파일을 재생성한다. 트랜잭션.
     *
     * @return string 새 관리키
     *
     * @throws InvalidStateTransitionException 종료/보관 상태
     * @throws RuntimeException                라이센스 없음
     */
    public function reissue(int $licenseId, int $actorId, ?string $newHostId = null, string $reason = ''): string
    {
        $license = $this->mustFind($licenseId);
        $status  = LicenseStatus::from((string) $license['status']);
        if (in_array($status, [LicenseStatus::Terminated, LicenseStatus::Archived], true)) {
            throw new InvalidStateTransitionException('종료·보관된 라이센스는 재발급할 수 없습니다.');
        }

        $newKey    = $this->makeLicenseKey();
        $newSn      = $this->makeLicenseSn((string) ($license['product_id'] ?? '0'));
        $isNodeLock = (string) $license['license_type'] === LicenseType::NodeLock->value;
        $hostId     = $newHostId ?? ($license['host_id'] !== null ? (string) $license['host_id'] : null);
        $prevKey    = $this->currentKey($licenseId);

        $this->transactional(function () use ($licenseId, $newKey, $newSn, $hostId, $actorId, $reason, $isNodeLock, $prevKey): void {
            // 노드락: host_id 변경 + 서명 파일 재생성(path 갱신)
            if ($isNodeLock) {
                $this->nodeLock()->regenerateFile($licenseId, $newKey, $newSn, $hostId);
            } elseif ($hostId !== null) {
                model(LicenseModel::class)->update($licenseId, ['host_id' => $hostId]);
            }

            $note = sprintf(
                '재발급(이전 키 폐기: %s…)%s',
                substr($prevKey ?? '', 0, 8),
                $reason !== '' ? " {$reason}" : '',
            );
            $this->addHistory($licenseId, HistoryType::Reissue, $actorId, $note, $newKey, $newSn, $hostId);
        });

        return $newKey;
    }

    /** 현재 유효한(최신 발급/재발급) 관리키. */
    public function currentKey(int $licenseId): ?string
    {
        /** @var array<string, mixed>|null $row */
        $row = model(LicenseHistoryModel::class)
            ->where('license_id', $licenseId)
            ->whereIn('type', [HistoryType::Issue->value, HistoryType::Reissue->value])
            ->orderBy('id', 'DESC')
            ->first();

        return $row !== null && $row['license_key'] !== null ? (string) $row['license_key'] : null;
    }

    /** 폐기된(이전) 키인가 — 부정사용 감지 대상. 최신 키가 아니면서 이력에 존재. */
    public function isRevokedKey(int $licenseId, string $key): bool
    {
        $current = $this->currentKey($licenseId);
        if ($current === null || $key === $current) {
            return false;
        }

        return model(LicenseHistoryModel::class)
            ->where('license_id', $licenseId)
            ->where('license_key', $key)
            ->countAllResults() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function mustFind(int $licenseId): array
    {
        /** @var array<string, mixed>|null $license */
        $license = model(LicenseModel::class)->find($licenseId);
        if ($license === null) {
            throw new RuntimeException('라이센스를 찾을 수 없습니다.');
        }

        return $license;
    }

    private function addHistory(
        int $licenseId,
        HistoryType $type,
        int $actorId,
        string $contents,
        ?string $licenseKey = null,
        ?string $licenseSn = null,
        ?string $hostId = null,
    ): void {
        model(LicenseHistoryModel::class)->insert([
            'license_id'  => $licenseId,
            'type'        => $type->value,
            'host_id'     => $hostId,
            'license_key' => $licenseKey,
            'license_sn'  => $licenseSn,
            'contents'    => $contents,
            'created_by'  => $actorId,
        ]);
    }

    /**
     * 콜백을 명시적 트랜잭션으로 실행(예외 시 롤백 후 재던짐).
     *
     * @param callable():void $callback
     */
    private function transactional(callable $callback): void
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $callback();
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function nodeLock(): NodeLockLicenseService
    {
        return $this->nodeLock ??= service('nodeLockLicenseService');
    }

    private function makeLicenseKey(): string
    {
        return bin2hex(random_bytes(16)) . date('His');
    }

    private function makeLicenseSn(string $productCode): string
    {
        return 'R' . $productCode . date('ymd') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    }
}
