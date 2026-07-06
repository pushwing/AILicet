<?php
/**
 * @var array<string, mixed>        $license
 * @var array<string, mixed>|null   $product
 * @var list<array<string, mixed>>  $customers
 * @var list<array<string, mixed>>  $history
 * @var string|null                 $current_key
 */
use App\Enums\HistoryType;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PeriodCode;

$status   = LicenseStatus::tryFrom((string) $license['status']);
$type     = LicenseType::tryFrom((string) $license['license_type']);
$period   = PeriodCode::tryFrom((string) $license['period_code']);
$isNode   = $type === LicenseType::NodeLock;
$statusCls = ['active' => 'success', 'suspended' => 'warning', 'terminated' => 'danger', 'archived' => 'muted'][(string) $license['status']] ?? 'muted';
$row      = static fn (string $label, ?string $value): string =>
    '<div style="display:flex;padding:8px 0;border-bottom:1px solid var(--color-border);"><div style="width:140px;color:var(--color-text-muted);">' . esc($label) . '</div><div>' . esc($value ?? '-') . '</div></div>';
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
    <a href="/admin/licenses" class="btn btn--ghost">목록</a>
</div>

<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;align-items:start;">
    <!-- 정보 -->
    <div class="card">
        <div class="card__head">라이센스 정보</div>
        <div class="card__body">
            <?= $row('상품', (string) ($product['name'] ?? '') . ' (' . (string) ($product['product_code'] ?? '') . ')') ?>
            <?= $row('종류', $type?->label()) ?>
            <?= $row('기간정책', $period?->label()) ?>
            <?= $row('버전', $license['version'] !== null ? (string) $license['version'] : null) ?>
            <?php if ($isNode): ?>
                <?= $row('호스트ID', $license['host_id'] !== null ? (string) $license['host_id'] : null) ?>
            <?php endif; ?>
            <?= $row('만료일', $license['expire_date'] !== null ? (string) $license['expire_date'] : '무기한') ?>
            <?= $row('기술지원 종료', $license['support_end_date'] !== null ? (string) $license['support_end_date'] : null) ?>
            <?= $row('발급일', $license['issue_date'] !== null ? (string) $license['issue_date'] : null) ?>
            <?= $row('현재 관리키', $current_key) ?>
            <?= $row('발급 회원', implode(', ', array_map(static fn ($c) => (string) $c['company_name'], $customers)) ?: null) ?>
            <?php if ($isNode && ! empty($license['path'])): ?>
                <div style="padding-top:12px;">
                    <a class="btn btn--ghost" href="/admin/licenses/<?= esc((string) $license['id']) ?>/download">⬇ 라이센스 파일 다운로드</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 관리 액션 -->
    <div class="card">
        <div class="card__head">상태 관리</div>
        <div class="card__body" style="display:flex;flex-direction:column;gap:14px;">
            <?php $lid = (string) $license['id']; ?>
            <?php if ($status === LicenseStatus::Active): ?>
                <form method="post" action="/admin/licenses/<?= $lid ?>/suspend" style="display:flex;gap:8px;">
                    <?= csrf_field() ?>
                    <input class="input" name="reason" placeholder="정지 사유">
                    <button class="btn btn--ghost">정지</button>
                </form>
            <?php elseif ($status === LicenseStatus::Suspended): ?>
                <form method="post" action="/admin/licenses/<?= $lid ?>/resume">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost">정지 해제</button>
                </form>
            <?php endif; ?>

            <?php if ($status === LicenseStatus::Active || $status === LicenseStatus::Suspended): ?>
                <form method="post" action="/admin/licenses/<?= $lid ?>/extend" style="display:flex;gap:8px;">
                    <?= csrf_field() ?>
                    <input class="input" type="date" name="expire_date" required>
                    <button class="btn btn--ghost">연장</button>
                </form>
                <form method="post" action="/admin/licenses/<?= $lid ?>/reissue" style="display:flex;gap:8px;"
                      onsubmit="return confirm('재발급하면 이전 키가 폐기됩니다. 진행할까요?');">
                    <?= csrf_field() ?>
                    <?php if ($isNode): ?>
                        <input class="input" name="host_id" placeholder="새 호스트ID(선택)">
                    <?php endif; ?>
                    <button class="btn btn--ghost">재발급</button>
                </form>
                <form method="post" action="/admin/licenses/<?= $lid ?>/terminate"
                      onsubmit="return confirm('종료하면 되돌릴 수 없습니다. 진행할까요?');">
                    <?= csrf_field() ?>
                    <button class="btn" style="background:var(--color-danger);color:#fff;">종료</button>
                </form>
            <?php else: ?>
                <p class="muted mb-0">종료·보관된 라이센스는 상태 변경할 수 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 이력 -->
<div class="card" style="margin-top:20px;">
    <div class="card__head">이력</div>
    <div class="card__body">
        <?php if (empty($history)): ?>
            <p class="muted mb-0">이력이 없습니다.</p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:2px;">
                <?php foreach ($history as $h): ?>
                    <?php $ht = HistoryType::tryFrom((string) $h['type']); ?>
                    <div style="display:flex;gap:14px;padding:10px 0;border-bottom:1px solid var(--color-border);">
                        <span class="badge badge--muted" style="height:fit-content;"><?= esc($ht?->label() ?? (string) $h['type']) ?></span>
                        <div style="flex:1;">
                            <div><?= esc((string) ($h['contents'] ?? '')) ?></div>
                            <div class="muted" style="font-size:12px;margin-top:2px;">
                                <?= esc((string) ($h['created_at'] ?? '')) ?>
                                <?php if (! empty($h['license_key'])): ?> · 키 <?= esc(substr((string) $h['license_key'], 0, 12)) ?>…<?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><?= $this->endSection() ?>
