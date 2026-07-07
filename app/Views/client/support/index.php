<?php
/** @var list<array<string, mixed>> $inquiries */
use App\Enums\InquiryStatus;
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head"><h1 class="page-head__title">고객센터</h1><p class="page-head__desc">문의를 등록하고 답변을 확인하세요.</p></div>

<?php if (session()->getFlashdata('message')): ?><div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.3fr;gap:20px;align-items:start;">
    <!-- 문의 등록 -->
    <div class="card">
        <div class="card__head">이메일 문의</div>
        <div class="card__body">
            <form method="post" action="/client/support">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="subject">제목 *</label>
                    <input class="input" id="subject" name="subject" required value="<?= esc(old('subject') ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="content">내용 *</label>
                    <textarea class="input" id="content" name="content" rows="6" required style="resize:vertical;"><?= esc(old('content') ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn--primary">문의 등록</button>
            </form>
        </div>
    </div>

    <!-- 내 문의내역 -->
    <div class="card">
        <div class="card__head">내 문의내역</div>
        <div class="card__body">
            <?php if (empty($inquiries)): ?>
                <p class="muted mb-0">등록된 문의가 없습니다.</p>
            <?php else: ?>
                <?php foreach ($inquiries as $q): $st = InquiryStatus::tryFrom((string) $q['status']); ?>
                    <div style="padding:12px 0;border-bottom:1px solid var(--color-border);">
                        <div style="display:flex;justify-content:space-between;gap:10px;">
                            <strong><?= esc((string) $q['subject']) ?></strong>
                            <span class="badge badge--<?= $q['status'] === 'answered' ? 'success' : 'muted' ?>"><?= esc($st?->label() ?? (string) $q['status']) ?></span>
                        </div>
                        <div class="muted" style="font-size:12px;margin:4px 0;"><?= esc((string) ($q['created_at'] ?? '')) ?></div>
                        <div style="white-space:pre-wrap;"><?= esc((string) $q['content']) ?></div>
                        <?php if (! empty($q['reply'])): ?>
                            <div class="alert alert--info" style="margin-top:8px;margin-bottom:0;white-space:pre-wrap;">
                                <strong>답변</strong><br><?= esc((string) $q['reply']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
