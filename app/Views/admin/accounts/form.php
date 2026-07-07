<?php
/**
 * @var array<string, mixed>|null $account
 * @var list<\App\Enums\UserRole>  $roles
 */
$isEdit = $account !== null;
$action = $isEdit ? '/admin/accounts/' . $account['id'] : '/admin/accounts';
$val    = static fn (string $k): string => esc((string) ($account[$k] ?? old($k) ?? ''));
$roleLabels = [1 => '운영자', 2 => '대행사', 3 => '일반회원'];
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '회원 수정' : '계정 생성' ?></h1>
    <p class="page-head__desc"><?= $isEdit ? 'AITessera 회원 정보를 수정합니다.' : '운영자/대행사/일반회원 계정을 생성합니다(이메일 인증 즉시 완료).' ?></p>
</div>

<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;"><div class="card__body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="field">
                <label class="field__label" for="email">이메일 *</label>
                <input class="input" type="email" id="email" name="email" value="<?= $val('email') ?>"
                       <?= $isEdit ? 'disabled' : 'required' ?>>
            </div>
            <?php if (! $isEdit): ?>
                <div class="field">
                    <label class="field__label" for="role">회원 구분 *</label>
                    <select class="input" id="role" name="role" required>
                        <?php foreach ($roleLabels as $v => $label): ?>
                            <option value="<?= $v ?>" <?= (string) old('role') === (string) $v ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="field">
                    <label class="field__label">회원 구분</label>
                    <input class="input" value="<?= esc($roleLabels[(int) ($account['role'] ?? 0)] ?? '-') ?>" disabled>
                </div>
            <?php endif; ?>
            <div class="field">
                <label class="field__label" for="name">이름 *</label>
                <input class="input" id="name" name="name" value="<?= $val('name') ?>" <?= $isEdit ? '' : 'required' ?>>
            </div>
            <div class="field">
                <label class="field__label" for="contact">연락처 <?= $isEdit ? '' : '*' ?></label>
                <input class="input" id="contact" name="contact" value="<?= $val('contact') ?>" <?= $isEdit ? '' : 'required' ?>>
            </div>
            <div class="field">
                <label class="field__label" for="company">회사</label>
                <input class="input" id="company" name="company" value="<?= $val('company') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="password"><?= $isEdit ? '비밀번호 (변경 시에만 입력)' : '비밀번호 *' ?></label>
                <input class="input" type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?>>
            </div>
            <?php if ($isEdit): ?>
                <div class="field">
                    <label class="field__label" for="is_active">상태</label>
                    <select class="input" id="is_active" name="is_active">
                        <option value="1" <?= ! empty($account['is_active']) ? 'selected' : '' ?>>활성</option>
                        <option value="0" <?= empty($account['is_active']) ? 'selected' : '' ?>>비활성</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div></div>
    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '생성' ?></button>
        <a href="/admin/accounts" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>
