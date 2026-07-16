<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="auth-card">
    <div class="auth-card__brand">
        <img class="auth-card__brand-mark" src="<?= base_url('assets/img/ailicet-mark.svg') ?>" width="34" height="34" alt=""> AILicet
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

    <?php if (! empty($demoLogin)): ?>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--color-border);">
            <p class="muted" style="font-size:12px;margin:0 0 10px;">개발용 빠른 로그인</p>
            <div style="display:flex;gap:8px;">
                <?php foreach ([
                    ['role' => '운영자', 'email' => 'operator@demo.test'],
                    ['role' => '대행사', 'email' => 'agency@demo.test'],
                    ['role' => '일반회원', 'email' => 'client@demo.test'],
                ] as $demo): ?>
                    <form method="post" action="/admin/login" style="flex:1;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= esc($demo['email']) ?>">
                        <input type="hidden" name="password" value="demo1234">
                        <button type="submit" class="btn btn--ghost btn--block" style="padding:8px;"><?= esc($demo['role']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
