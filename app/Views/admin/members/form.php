<?php
/**
 * @var array<string, mixed>|null              $customer
 * @var list<\App\Enums\CustomerType>          $types
 * @var list<array{id:int, company_name:string}> $agencies
 */
$isEdit = $customer !== null;
$action = $isEdit ? '/admin/members/' . $customer['id'] : '/admin/members';
$val    = static fn (string $k, string $d = ''): string => esc((string) ($customer[$k] ?? old($k) ?? $d));
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '회원 수정' : '회원 등록' ?></h1>
    <p class="page-head__desc">대행사 또는 고객 회원 정보를 입력합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">회원 정보</div>
        <div class="card__body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="field">
                    <label class="field__label" for="customer_type">회원 유형 *</label>
                    <select class="input" id="customer_type" name="customer_type" required onchange="toggleParent()">
                        <?php foreach ($types as $t): ?>
                            <option value="<?= esc($t->value) ?>" <?= $val('customer_type') === $t->value ? 'selected' : '' ?>>
                                <?= esc($t->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" id="parentField">
                    <label class="field__label" for="parent_id">소속 대행사</label>
                    <select class="input" id="parent_id" name="parent_id">
                        <option value="">— 없음 —</option>
                        <?php foreach ($agencies as $a): ?>
                            <option value="<?= esc((string) $a['id']) ?>" <?= $val('parent_id') === (string) $a['id'] ? 'selected' : '' ?>>
                                <?= esc($a['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                    <label class="field__label" for="user_id">연동 사용자 ID
                        <span class="muted" style="font-weight:400;">(AITessera user_id — 대행사/고객 로그인 연결)</span>
                    </label>
                    <input class="input" type="number" id="user_id" name="user_id" value="<?= $val('user_id') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="is_active">상태</label>
                    <select class="input" id="is_active" name="is_active">
                        <option value="1" <?= $val('is_active', '1') === '1' ? 'selected' : '' ?>>활성</option>
                        <option value="0" <?= $val('is_active', '1') === '0' ? 'selected' : '' ?>>비활성</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/admin/members" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // 대행사 유형이면 소속(parent) 선택 숨김
    function toggleParent() {
        const isClient = document.getElementById('customer_type').value === 'client';
        document.getElementById('parentField').style.display = isClient ? '' : 'none';
    }
    toggleParent();
</script>
<?= $this->endSection() ?>
