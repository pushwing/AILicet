<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="auth-card" style="text-align:center;">
    <div class="auth-card__brand" style="justify-content:center;"><span class="auth-card__brand-mark">A</span> AILicet</div>
    <h3 class="mt-0">가입 확인 메일을 보냈습니다</h3>
    <p class="muted">입력하신 이메일의 인증 링크를 클릭하면 가입이 완료됩니다.</p>
    <?php if (! empty($verifyUrl)): ?>
        <div class="alert alert--info" style="text-align:left;">
            <strong>개발 모드</strong> — 메일 발송 없이 바로 인증할 수 있습니다:<br>
            <a href="<?= esc($verifyUrl) ?>"><?= esc($verifyUrl) ?></a>
        </div>
    <?php endif; ?>
    <a href="/admin/login" class="btn btn--ghost btn--block">로그인으로</a>
</div>
<?= $this->endSection() ?>
