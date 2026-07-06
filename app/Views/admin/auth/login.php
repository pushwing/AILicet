<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="auth-card">
    <div class="auth-card__brand">
        <span class="auth-card__brand-mark">A</span> AILicet
    </div>
    <p class="auth-card__sub">라이선스 관리 콘솔 로그인</p>

    <?php if (! empty($error)): ?>
        <div class="alert alert--danger"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if (! empty($notice)): ?>
        <div class="alert alert--info"><?= esc($notice) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/login">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="email">이메일</label>
            <input class="input" type="email" id="email" name="email"
                   value="<?= esc($email ?? '') ?>" required autofocus placeholder="you@example.com">
        </div>
        <div class="field">
            <label class="field__label" for="password">비밀번호</label>
            <input class="input" type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn--primary btn--block">로그인</button>
    </form>
</div>
<?= $this->endSection() ?>
