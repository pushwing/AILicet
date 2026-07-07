<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="auth-card" style="text-align:center;">
    <div class="auth-card__brand" style="justify-content:center;"><span class="auth-card__brand-mark">A</span> AILicet</div>
    <?php if (! empty($ok)): ?>
        <h3 class="mt-0">이메일 인증 완료 ✅</h3>
        <p class="muted">가입이 완료되었습니다. 로그인 후 이용하세요.</p>
    <?php else: ?>
        <h3 class="mt-0">인증 실패</h3>
        <p class="muted">유효하지 않거나 이미 사용된 인증 링크입니다.</p>
    <?php endif; ?>
    <a href="/admin/login" class="btn btn--primary btn--block">로그인</a>
</div>
<?= $this->endSection() ?>
