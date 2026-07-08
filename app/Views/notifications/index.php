<?php
/**
 * 인앱 수신함(운영자/대행사/회원 공용).
 *
 * @var list<array<string, mixed>> $notifications
 * @var string $baseUrl  읽음 처리 폼의 기준 URL(예: /admin/notifications)
 */
use App\Enums\NotificationType;

/** level → 배지 클래스 */
$badgeClass = static fn (string $level): string => match ($level) {
    'error'   => 'badge--danger',
    'warning' => 'badge--warning',
    default   => 'badge--muted',
};
$unreadCount = count(array_filter($notifications, static fn ($n) => (int) $n['is_read'] === 0));
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">알림</h1>
    <p class="page-head__desc">라이센스 만료 임박·종료 알림을 확인합니다.</p>
</div>

<div class="card">
    <div class="card__head" style="display:flex;justify-content:space-between;align-items:center;">
        <span>수신함 <?php if ($unreadCount > 0): ?><span class="badge badge--danger"><?= esc((string) $unreadCount) ?></span><?php endif; ?></span>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="<?= esc($baseUrl) ?>/read-all" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost" style="padding:4px 12px;">모두 읽음</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card__body">
        <?php if ($notifications === []): ?>
            <p class="muted mb-0">받은 알림이 없습니다.</p>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?php
                $isRead = (int) $n['is_read'] === 1;
                $type   = NotificationType::tryFrom((string) $n['type']);
                ?>
                <div style="padding:14px 0;border-bottom:1px solid var(--color-border);<?= $isRead ? 'opacity:.6;' : '' ?>">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span class="badge <?= esc($badgeClass((string) $n['level'])) ?>"><?= esc($type?->label() ?? (string) $n['type']) ?></span>
                            <strong><?= esc((string) $n['title']) ?></strong>
                            <?php if (! $isRead): ?><span class="badge badge--danger">N</span><?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span class="muted" style="font-size:12px;"><?= esc((string) ($n['created_at'] ?? '')) ?></span>
                            <?php if (! $isRead): ?>
                                <form method="post" action="<?= esc($baseUrl) ?>/<?= (int) $n['id'] ?>/read" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost" style="padding:3px 10px;font-size:12px;">읽음</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top:6px;white-space:pre-wrap;"><?= esc((string) $n['body']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
