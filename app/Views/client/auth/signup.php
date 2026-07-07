<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="auth-card">
    <div class="auth-card__brand"><span class="auth-card__brand-mark">A</span> AILicet</div>
    <p class="auth-card__sub">고객 회원가입</p>

    <?php if (! empty($error)): ?><div class="alert alert--danger"><?= esc($error) ?></div><?php endif; ?>
    <?php if (! empty($errors)): ?>
        <div class="alert alert--danger"><?php foreach ($errors as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" action="/signup">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="company_name">회사명 *</label>
            <input class="input" id="company_name" name="company_name" required value="<?= esc($old['company_name'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="field__label" for="name">이름 *</label>
            <input class="input" id="name" name="name" required value="<?= esc($old['name'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="field__label" for="email">이메일 *</label>
            <input class="input" type="email" id="email" name="email" required value="<?= esc($old['email'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="field__label" for="phone">연락처</label>
            <input class="input" id="phone" name="phone" value="<?= esc($old['phone'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn--primary btn--block">가입하기</button>
    </form>
    <p class="muted" style="text-align:center;margin-bottom:0;margin-top:16px;font-size:13px;">
        이미 계정이 있으신가요? <a href="/admin/login">로그인</a>
    </p>
</div>
<?= $this->endSection() ?>
