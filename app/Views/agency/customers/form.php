<?php
/** @var array<string, mixed>|null $customer */
$isEdit = $customer !== null;
$action = $isEdit ? '/agency/customers/' . $customer['id'] : '/agency/customers';
$val    = static fn (string $k, string $d = ''): string => esc((string) ($customer[$k] ?? old($k) ?? $d));
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '고객 수정' : '고객 등록' ?></h1>
    <p class="page-head__desc">우리 대행사 소속 고객으로 등록됩니다.</p>
</div>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;"><div class="card__body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="field">
                <label class="field__label" for="company_name">회사명 *</label>
                <input class="input" id="company_name" name="company_name" required value="<?= $val('company_name') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="name">담당자명 *</label>
                <input class="input" id="name" name="name" required value="<?= $val('name') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="email">이메일 *</label>
                <input class="input" type="email" id="email" name="email" required value="<?= $val('email') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="phone">연락처</label>
                <input class="input" id="phone" name="phone" value="<?= $val('phone') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="is_active">상태</label>
                <select class="input" id="is_active" name="is_active">
                    <option value="1" <?= $val('is_active', '1') === '1' ? 'selected' : '' ?>>활성</option>
                    <option value="0" <?= $val('is_active', '1') === '0' ? 'selected' : '' ?>>비활성</option>
                </select>
            </div>
        </div>
        <input type="hidden" name="customer_type" value="client">
    </div></div>
    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/agency/customers" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>
