<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\CustomerModel;
use App\Models\LicenseModel;
use App\Models\NotificationModel;

/**
 * 인앱 메시지 발송·조회 유스케이스.
 *
 * 라이센스 만료 임박/종료를 소유 회원·소속 대행사·운영자에게 전달한다.
 * 수신자는 customer_license → customers 로 해석하며, 배치 재실행 시 중복
 * 발송을 막기 위해 dedup_key 로 멱등성을 보장한다.
 */
final class NotificationService
{
    /** 운영자 요약 본문에 나열할 최대 건수. */
    private const int SUMMARY_LIMIT = 20;

    /**
     * 대행사 참조 캐시(배치 내 동일 대행사 반복 조회 방지).
     *
     * @var array<int, array{id:int, user_id:?int}|null>
     */
    private array $agencyCache = [];

    /**
     * 만료 임박 라이센스 1건 → 소유 회원 + 소속 대행사에게 발송.
     *
     * @param array<string, mixed> $license licenses.* (+ product_name)
     */
    public function notifyLicenseExpiring(array $license, int $days): void
    {
        $this->fanOutToOwners(
            (int) ($license['id'] ?? 0),
            $this->productName($license),
            (string) ($license['expire_date'] ?? ''),
            NotificationType::LicenseExpiring,
            $days,
        );
    }

    /**
     * 운영자 공용 수신함에 만료 임박 요약 1건.
     *
     * @param list<array<string, mixed>> $list
     */
    public function notifyExpiringSummaryToOperators(int $days, array $list): void
    {
        if ($list === []) {
            return;
        }

        $target = date('Y-m-d', strtotime("+{$days} days"));
        $this->push(
            role: UserRole::Operator->value,
            userId: null,
            customerId: null,
            licenseId: null,
            type: NotificationType::LicenseExpiring,
            level: $days <= 1 ? 'error' : 'warning',
            title: "라이센스 만료 {$days}일 전: " . count($list) . '건',
            body: $this->summarize($list),
            dedup: "expiring_summary:{$days}:{$target}",
        );
    }

    /**
     * 만료 종료된 라이센스 → 소유 회원·대행사에게 종료 안내 + 운영자 요약.
     *
     * @param list<int> $licenseIds
     */
    public function notifyLicenseExpired(array $licenseIds): void
    {
        if ($licenseIds === []) {
            return;
        }

        $ids = implode(', ', array_slice($licenseIds, 0, self::SUMMARY_LIMIT));
        $this->push(
            role: UserRole::Operator->value,
            userId: null,
            customerId: null,
            licenseId: null,
            type: NotificationType::LicenseExpired,
            level: 'info',
            title: '라이센스 만료 종료: ' . count($licenseIds) . '건',
            body: count($licenseIds) . '건이 만료되어 자동 종료되었습니다. (id: ' . $ids . ')',
            dedup: 'expired_summary:' . date('Y-m-d'),
        );

        foreach ($licenseIds as $licenseId) {
            $license = $this->findLicense($licenseId);
            if ($license === null) {
                continue;
            }
            $this->fanOutToOwners(
                $licenseId,
                $this->productName($license),
                (string) ($license['expire_date'] ?? ''),
                NotificationType::LicenseExpired,
                null,
            );
        }
    }

    /** 안 읽은 메시지 수(수신함 배지용). */
    public function unreadCount(int $role, int $userId): int
    {
        if ($role === 0) {
            return 0;
        }

        return model(NotificationModel::class)->unreadCountFor($role, $userId);
    }

    /**
     * 수신함 목록.
     *
     * @return list<array<string, mixed>>
     */
    public function inbox(int $role, int $userId, int $limit = 50): array
    {
        return model(NotificationModel::class)->inboxFor($role, $userId, $limit);
    }

    public function markRead(int $id, int $role, int $userId): bool
    {
        return model(NotificationModel::class)->markRead($id, $role, $userId);
    }

    public function markAllRead(int $role, int $userId): void
    {
        model(NotificationModel::class)->markAllRead($role, $userId);
    }

    /**
     * 라이센스 소유자(회원/대행사)와 회원의 소속 대행사에게 동일 이벤트를 발송한다.
     */
    private function fanOutToOwners(int $licenseId, string $product, string $expireDate, NotificationType $type, ?int $days): void
    {
        $level     = $type === NotificationType::LicenseExpired ? 'info' : ($days !== null && $days <= 1 ? 'error' : 'warning');
        $dedupBase = $type === NotificationType::LicenseExpired ? "expired:{$licenseId}" : "expiring:{$days}:{$licenseId}";

        foreach ($this->owners($licenseId) as $owner) {
            // 소유자 본인(회원 또는 대행사)
            [$title, $body] = $this->buildMessage($type, $days, $product, $expireDate, $licenseId, null);
            $this->push(
                role: $owner['role'],
                userId: $owner['user_id'],
                customerId: $owner['id'],
                licenseId: $licenseId,
                type: $type,
                level: $level,
                title: $title,
                body: $body,
                dedup: "{$dedupBase}:owner:{$owner['id']}",
            );

            // 회원 소유면 소속 대행사에게도(고객명 명시)
            if ($owner['role'] === UserRole::Member->value && $owner['parent'] !== null) {
                $parent = $owner['parent'];
                [$aTitle, $aBody] = $this->buildMessage($type, $days, $product, $expireDate, $licenseId, $owner['company_name']);
                $this->push(
                    role: UserRole::Agency->value,
                    userId: $parent['user_id'],
                    customerId: $parent['id'],
                    licenseId: $licenseId,
                    type: $type,
                    level: $level,
                    title: $aTitle,
                    body: $aBody,
                    dedup: "{$dedupBase}:agency:{$parent['id']}",
                );
            }
        }
    }

    /**
     * 제목·본문 생성.
     *
     * @return array{0:string, 1:string}
     */
    private function buildMessage(NotificationType $type, ?int $days, string $product, string $expireDate, int $licenseId, ?string $clientName): array
    {
        $subject = $clientName !== null
            ? "{$clientName} 고객의 [{$product}] 라이센스(#{$licenseId})"
            : "[{$product}] 라이센스(#{$licenseId})";

        if ($type === NotificationType::LicenseExpired) {
            return ['라이센스 만료 종료 안내', "{$subject}가 {$expireDate}자로 만료되어 종료 처리되었습니다."];
        }

        return ["라이센스 만료 {$days}일 전 안내", "{$subject}가 {$expireDate}에 만료됩니다."];
    }

    /**
     * 라이센스 소유 회원 목록(소속 대행사 참조 포함).
     *
     * @return list<array{id:int, role:int, user_id:?int, company_name:string, parent:?array{id:int, user_id:?int}}>
     */
    private function owners(int $licenseId): array
    {
        $customerIds = $this->ownerCustomerIds($licenseId);
        if ($customerIds === []) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = model(CustomerModel::class)
            ->select('id, customer_type, user_id, parent_id, company_name')
            ->whereIn('id', $customerIds)
            ->findAll();

        $owners = [];
        foreach ($rows as $row) {
            $isAgency = (string) $row['customer_type'] === CustomerType::Agency->value;
            $parent   = null;
            if (! $isAgency && $row['parent_id'] !== null) {
                $parent = $this->agencyRef((int) $row['parent_id']);
            }

            $owners[] = [
                'id'           => (int) $row['id'],
                'role'         => $isAgency ? UserRole::Agency->value : UserRole::Member->value,
                'user_id'      => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'company_name' => (string) $row['company_name'],
                'parent'       => $parent,
            ];
        }

        return $owners;
    }

    /**
     * 소속 대행사 참조(id·user_id).
     *
     * @return array{id:int, user_id:?int}|null
     */
    private function agencyRef(int $agencyId): ?array
    {
        if (array_key_exists($agencyId, $this->agencyCache)) {
            return $this->agencyCache[$agencyId];
        }

        /** @var array<string, mixed>|null $row */
        $row = model(CustomerModel::class)->select('id, user_id')->find($agencyId);
        $ref = $row === null ? null : [
            'id'      => (int) $row['id'],
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
        ];

        return $this->agencyCache[$agencyId] = $ref;
    }

    /**
     * @return list<int>
     */
    private function ownerCustomerIds(int $licenseId): array
    {
        /** @var list<array{customer_id:int}> $rows */
        $rows = db_connect()->table('customer_license')
            ->distinct()->select('customer_id')
            ->where('license_id', $licenseId)
            ->get()->getResultArray();

        return array_map(static fn ($r) => (int) $r['customer_id'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLicense(int $licenseId): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = model(LicenseModel::class)
            ->select('licenses.*, products.name AS product_name')
            ->join('products', 'products.id = licenses.product_id', 'left')
            ->find($licenseId);

        return $row;
    }

    /**
     * @param array<string, mixed> $license
     */
    private function productName(array $license): string
    {
        $name = (string) ($license['product_name'] ?? '');

        return $name !== '' ? $name : ('상품#' . (int) ($license['product_id'] ?? 0));
    }

    /**
     * 만료 임박 라이센스 목록 요약(운영자 요약 본문).
     *
     * @param list<array<string, mixed>> $list
     */
    private function summarize(array $list): string
    {
        $lines = array_map(
            static fn ($l) => "#{$l['id']} (만료 {$l['expire_date']})",
            array_slice($list, 0, self::SUMMARY_LIMIT),
        );

        return implode("\n", $lines)
            . (count($list) > self::SUMMARY_LIMIT ? "\n… 외 " . (count($list) - self::SUMMARY_LIMIT) . '건' : '');
    }

    /**
     * dedup_key 중복이 아니면 메시지를 저장한다.
     */
    private function push(
        int $role,
        ?int $userId,
        ?int $customerId,
        ?int $licenseId,
        NotificationType $type,
        string $level,
        string $title,
        string $body,
        string $dedup,
    ): void {
        $model = model(NotificationModel::class);
        if ($dedup !== '' && $model->where('dedup_key', $dedup)->countAllResults() > 0) {
            return;
        }

        $model->insert([
            'recipient_role'    => $role,
            'recipient_user_id' => $userId,
            'customer_id'       => $customerId,
            'license_id'        => $licenseId,
            'type'              => $type->value,
            'level'             => $level,
            'title'             => $title,
            'body'              => $body,
            'dedup_key'         => $dedup !== '' ? $dedup : null,
        ]);
    }
}
