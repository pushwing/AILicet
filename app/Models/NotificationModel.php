<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use CodeIgniter\Model;

/**
 * 인앱 메시지(notifications) 모델 — 수신함 조회·읽음 처리.
 *
 * 운영자(role=Operator)는 공용 수신함이라 recipient_user_id 무관하게 역할로만 스코프하고,
 * 대행사/회원은 recipient_user_id(본인 AITessera user id)로 스코프한다.
 */
final class NotificationModel extends Model
{
    protected $table         = 'notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'recipient_role', 'recipient_user_id', 'customer_id', 'license_id',
        'type', 'level', 'title', 'body', 'dedup_key', 'is_read', 'read_at',
    ];

    /** 수신자 스코프를 빌더에 적용한다. */
    private function scope(int $role, int $userId): self
    {
        $this->where('recipient_role', $role);
        // 운영자 공용 수신함은 개별 user 로 나누지 않는다.
        if ($role !== UserRole::Operator->value) {
            $this->where('recipient_user_id', $userId);
        }

        return $this;
    }

    /** 안 읽은 메시지 수. */
    public function unreadCountFor(int $role, int $userId): int
    {
        return $this->scope($role, $userId)->where('is_read', 0)->countAllResults();
    }

    /**
     * 수신함 목록(최신순).
     *
     * @return list<array<string, mixed>>
     */
    public function inboxFor(int $role, int $userId, int $limit = 50, int $offset = 0): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->scope($role, $userId)
            ->orderBy('is_read', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll($limit, $offset);

        return $rows;
    }

    /** 단건 읽음 처리(스코프 강제 — 타인 메시지는 무시). */
    public function markRead(int $id, int $role, int $userId): bool
    {
        $found = $this->scope($role, $userId)->where('id', $id)->first();
        if ($found === null) {
            return false;
        }

        return $this->update($id, ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }

    /** 전체 읽음 처리. */
    public function markAllRead(int $role, int $userId): void
    {
        // Model::update() 는 인자 없이 호출 시 예외를 던지므로 빌더를 직접 사용한다.
        $builder = $this->builder()->where('recipient_role', $role)->where('is_read', 0);
        if ($role !== UserRole::Operator->value) {
            $builder->where('recipient_user_id', $userId);
        }
        $builder->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }
}
