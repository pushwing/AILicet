<?php
/**
 * 감사로그 상세.
 *
 * @var array<string, mixed> $log
 */
use App\Enums\AuditEventType;

$event   = AuditEventType::tryFrom((string) $log['event_type']);
$row     = static fn (string $label, ?string $value): string =>
    '<div style="display:flex;padding:8px 0;border-bottom:1px solid var(--color-border);"><div style="width:140px;color:var(--color-text-muted);">' . esc($label) . '</div><div style="flex:1;word-break:break-all;">' . esc($value ?? '-') . '</div></div>';

// detail(JSON) 파싱 — 파싱 실패 시 원문 그대로 노출. 빈 값·리터럴 null 은 '상세 없음' 처리
$detailRaw = is_string($log['detail'] ?? null) ? trim((string) $log['detail']) : '';
$detail    = $detailRaw !== '' ? json_decode($detailRaw, true) : null;
$detailStr = is_array($detail)
    ? (string) json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : ($detailRaw !== '' && $detailRaw !== 'null' ? $detailRaw : '');

$licenseId = $log['license_id'] !== null ? (int) $log['license_id'] : null;

// AI 사람용 설명(ai_explanation) — 배치가 채운 경우에만 표시. 없으면 카드 자체를 렌더링하지 않는다.
$aiExplanation = is_string($log['ai_explanation'] ?? null) ? trim((string) $log['ai_explanation']) : '';
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head page-head--actions">
    <div>
        <h1 class="page-head__title">감사로그 #<?= esc((string) $log['id']) ?>
            <span class="badge badge--warning" style="vertical-align:middle;"><?= esc($event?->label() ?? (string) $log['event_type']) ?></span>
        </h1>
        <p class="page-head__desc"><?= esc((string) ($log['created_at'] ?? '')) ?></p>
    </div>
    <a href="/admin/audit-logs" class="btn btn--ghost">목록</a>
</div>

<?php if ($aiExplanation !== ''): ?>
    <!-- AI 사람용 설명 -->
    <div class="card" style="margin-bottom:20px;border-left:3px solid var(--color-primary,#0F6E56);">
        <div class="card__head">🤖 AI 설명</div>
        <div class="card__body">
            <p style="margin:0;line-height:1.6;"><?= esc($aiExplanation) ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <!-- 이벤트 정보 -->
    <div class="card">
        <div class="card__head">이벤트 정보</div>
        <div class="card__body">
            <?= $row('이벤트 유형', $event?->label() ?? (string) $log['event_type']) ?>
            <?= $row('발생일시', $log['created_at'] !== null ? (string) $log['created_at'] : null) ?>
            <?= $row('상품', $log['product_name'] !== null ? (string) $log['product_name'] : null) ?>
            <?= $row('라이센스키', $log['license_key'] !== null ? (string) $log['license_key'] : null) ?>
            <?= $row('등록 호스트', $log['host_id'] !== null ? (string) $log['host_id'] : null) ?>
            <?= $row('사용 호스트', $log['client_host_id'] !== null ? (string) $log['client_host_id'] : null) ?>
            <?= $row('IP', $log['ip'] !== null ? (string) $log['ip'] : null) ?>
            <?php if ($licenseId !== null): ?>
                <div style="padding-top:12px;">
                    <a class="btn btn--ghost" href="/admin/licenses/<?= esc((string) $licenseId) ?>">연관 라이센스 보기 →</a>
                </div>
            <?php else: ?>
                <div style="padding-top:12px;" class="muted">연관 라이센스 없음(삭제되었거나 미연결)</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 상세(detail) -->
    <div class="card">
        <div class="card__head">상세 데이터</div>
        <div class="card__body">
            <?php if ($detailStr !== ''): ?>
                <pre style="margin:0;white-space:pre-wrap;word-break:break-all;font-size:13px;background:var(--color-bg-subtle,#f6f8fa);padding:12px;border-radius:var(--radius-sm,6px);"><?= esc($detailStr) ?></pre>
            <?php else: ?>
                <span class="muted">상세 데이터 없음</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
