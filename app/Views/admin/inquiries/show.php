<?php
/**
 * 고객 문의 상세 — AI 분류·초안 표시 + 답변 확정 발송.
 *
 * @var array<string, mixed> $inquiry
 */
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;

$status    = InquiryStatus::tryFrom((string) $inquiry['status']);
$category  = $inquiry['ai_category'] !== null ? InquiryCategory::tryFrom((string) $inquiry['ai_category']) : null;
$draft     = (string) ($inquiry['ai_draft_reply'] ?? '');
$reply     = (string) ($inquiry['reply'] ?? '');
$answered  = $inquiry['status'] === InquiryStatus::Answered->value;
$statusCls = $answered ? 'success' : ($inquiry['status'] === 'closed' ? 'muted' : 'warning');

$row = static fn (string $label, ?string $value): string =>
    '<div style="display:flex;padding:8px 0;border-bottom:1px solid var(--color-border);"><div style="width:110px;color:var(--color-text-muted);">' . esc($label) . '</div><div style="flex:1;word-break:break-all;">' . esc($value ?? '-') . '</div></div>';
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head page-head--actions">
    <div>
        <h1 class="page-head__title">문의 #<?= esc((string) $inquiry['id']) ?>
            <span class="badge badge--<?= esc($statusCls) ?>" style="vertical-align:middle;"><?= esc($status?->label() ?? (string) $inquiry['status']) ?></span>
        </h1>
        <p class="page-head__desc"><?= esc((string) ($inquiry['created_at'] ?? '')) ?></p>
    </div>
    <a href="/admin/inquiries" class="btn btn--ghost">목록</a>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert--success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>

<div class="detail-grid detail-grid--balanced">
    <!-- 문의 내용 -->
    <div class="card">
        <div class="card__head">문의 내용</div>
        <div class="card__body">
            <?= $row('접수자', (string) ($inquiry['email'] ?? '')) ?>
            <?= $row('제목', (string) ($inquiry['subject'] ?? '')) ?>
            <?= $row('AI 분류', $category?->label() ?? ($inquiry['ai_processed_at'] !== null ? '미분류' : 'AI 처리 대기중')) ?>
            <div style="padding-top:12px;">
                <div style="color:var(--color-text-muted);margin-bottom:6px;">내용</div>
                <div style="white-space:pre-wrap;word-break:break-word;line-height:1.6;"><?= esc((string) ($inquiry['content'] ?? '')) ?></div>
            </div>
        </div>
    </div>

    <!-- 답변 -->
    <div class="card">
        <div class="card__head">답변<?= $answered ? ' (발송완료)' : ' 작성' ?></div>
        <div class="card__body">
            <?php if ($draft !== ''): ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span class="badge badge--muted">🤖 AI 답변 초안</span>
                        <button type="button" class="btn btn--ghost" style="padding:4px 10px;" onclick="useDraft()">초안 불러오기</button>
                    </div>
                    <div id="aiDraft" style="white-space:pre-wrap;word-break:break-word;line-height:1.6;background:var(--color-bg-subtle,#f6f8fa);border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:10px;font-size:14px;"><?= esc($draft) ?></div>
                    <p class="muted" style="font-size:12px;margin-top:6px;">※ AI 초안은 참고용입니다. 반드시 검토·수정 후 발송하세요.</p>
                </div>
            <?php elseif ($inquiry['ai_processed_at'] === null): ?>
                <p class="muted" style="margin-bottom:14px;">AI 답변 초안 생성 대기중입니다(배치 처리).</p>
            <?php else: ?>
                <p class="muted" style="margin-bottom:14px;">AI 초안 생성에 실패했거나 결과가 비어 있습니다. 직접 작성해 주세요.</p>
            <?php endif; ?>

            <form method="post" action="/admin/inquiries/<?= esc((string) $inquiry['id']) ?>/reply">
                <?= csrf_field() ?>
                <label class="muted" style="display:block;margin-bottom:6px;">답변 내용</label>
                <textarea class="input" name="reply" id="replyInput" rows="10"
                          style="width:100%;line-height:1.6;resize:vertical;"
                          placeholder="답변을 입력하거나 위 AI 초안을 불러와 수정하세요."><?= esc(old('reply', $reply)) ?></textarea>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                    <button type="submit" class="btn btn--primary"
                            onclick="return confirm('이 내용으로 답변을 발송할까요?')">
                        <?= $answered ? '답변 재발송' : '답변 발송(확정)' ?>
                    </button>
                </div>
            </form>

            <?php if ($answered && $inquiry['replied_at'] !== null): ?>
                <p class="muted" style="font-size:12px;margin-top:10px;">발송일시: <?= esc((string) $inquiry['replied_at']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // AI 초안을 답변 입력창으로 복사(운영자가 수정 후 발송).
    function useDraft() {
        const draft = document.getElementById('aiDraft');
        const input = document.getElementById('replyInput');
        if (draft && input) {
            input.value = draft.innerText;
            input.focus();
        }
    }
</script>
<?= $this->endSection() ?>
