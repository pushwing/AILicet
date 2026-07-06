<?php
/**
 * @var array<string, mixed>       $license
 * @var array<string, mixed>|null  $product
 * @var list<array<string, mixed>> $customers
 * @var list<array<string, mixed>> $history
 * @var string|null                $current_key
 */
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;

$status    = LicenseStatus::tryFrom((string) $license['status']);
$type      = LicenseType::tryFrom((string) $license['license_type']);
$period    = PeriodCode::tryFrom((string) $license['period_code']);
$statusCls = ['active' => 'success', 'suspended' => 'warning', 'terminated' => 'danger', 'archived' => 'muted'][(string) $license['status']] ?? 'muted';
$row       = static fn (string $l, ?string $v): string =>
    '<div style="display:flex;padding:8px 0;border-bottom:1px solid var(--color-border);"><div style="width:140px;color:var(--color-text-muted);">' . esc($l) . '</div><div>' . esc($v ?? '-') . '</div></div>';
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-end;">
    <div>
        <h1 class="page-head__title">라이센스 #<?= esc((string) $license['id']) ?>
            <span class="badge badge--<?= $statusCls ?>" style="vertical-align:middle;"><?= esc($status?->label() ?? '') ?></span>
        </h1>
        <p class="page-head__desc"><?= esc($type?->label() ?? '') ?> · <?= esc((string) ($product['name'] ?? '')) ?></p>
    </div>
    <a href="/agency/licenses" class="btn btn--ghost">목록</a>
</div>

<div class="card"><div class="card__body">
    <?= $row('상품', (string) ($product['name'] ?? '') . ' (' . (string) ($product['product_code'] ?? '') . ')') ?>
    <?= $row('종류', $type?->label()) ?>
    <?= $row('기간정책', $period?->label()) ?>
    <?php if ($type === LicenseType::NodeLock): ?><?= $row('호스트ID', $license['host_id'] !== null ? (string) $license['host_id'] : null) ?><?php endif; ?>
    <?= $row('만료일', $license['expire_date'] !== null ? (string) $license['expire_date'] : '무기한') ?>
    <?= $row('발급일', $license['issue_date'] !== null ? (string) $license['issue_date'] : null) ?>
    <?= $row('현재 관리키', $current_key) ?>
    <?= $row('고객', implode(', ', array_map(static fn ($c) => (string) $c['company_name'], $customers)) ?: null) ?>
</div></div>

<div class="card" style="margin-top:20px;">
    <div class="card__head">이력</div>
    <div class="card__body">
        <?php if (empty($history)): ?>
            <p class="muted mb-0">이력이 없습니다.</p>
        <?php else: ?>
            <?php foreach ($history as $h): $ht = HistoryType::tryFrom((string) $h['type']); ?>
                <div style="display:flex;gap:14px;padding:10px 0;border-bottom:1px solid var(--color-border);">
                    <span class="badge badge--muted" style="height:fit-content;"><?= esc($ht?->label() ?? (string) $h['type']) ?></span>
                    <div style="flex:1;">
                        <div><?= esc((string) ($h['contents'] ?? '')) ?></div>
                        <div class="muted" style="font-size:12px;margin-top:2px;"><?= esc((string) ($h['created_at'] ?? '')) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
