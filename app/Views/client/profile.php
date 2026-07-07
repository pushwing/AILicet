<?php
/** @var array<string, mixed>|null $customer */
$val = static fn (string $k): string => esc((string) ($customer[$k] ?? old($k) ?? ''));
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head"><h1 class="page-head__title">내 정보</h1></div>

<?php if (session()->getFlashdata('message')): ?><div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<form method="post" action="/client/profile">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;"><div class="card__body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="field">
                <label class="field__label">이메일</label>
                <input class="input" value="<?= $val('email') ?>" disabled>
            </div>
            <div class="field">
                <label class="field__label" for="company_name">회사명 *</label>
                <input class="input" id="company_name" name="company_name" required value="<?= $val('company_name') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="name">이름 *</label>
                <input class="input" id="name" name="name" required value="<?= $val('name') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="phone">연락처</label>
                <input class="input" id="phone" name="phone" value="<?= $val('phone') ?>">
            </div>
        </div>
    </div></div>
    <button type="submit" class="btn btn--primary">저장</button>
</form>
<?= $this->endSection() ?>
