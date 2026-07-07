<?php
/**
 * @var array<string, mixed>|null $product
 * @var list<array{id:int, product_id:int, code:string, name:string}> $modules
 * @var list<\App\Enums\LicenseType> $licenseTypes
 * @var list<\App\Enums\PeriodCode> $periodCodes
 */
$isEdit = $product !== null;
$action = $isEdit ? '/admin/products/' . $product['id'] : '/admin/products';
$val    = static fn (string $k, string $default = ''): string => esc((string) ($product[$k] ?? old($k) ?? $default));
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title"><?= $isEdit ? '상품 수정' : '상품 등록' ?></h1>
    <p class="page-head__desc">상품 정보와 라이선스 종류·기간정책, 모듈을 정의합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">기본 정보</div>
        <div class="card__body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="field">
                    <label class="field__label" for="product_code">상품코드 *</label>
                    <input class="input" id="product_code" name="product_code" required
                           value="<?= $val('product_code') ?>" placeholder="예: PT001">
                </div>
                <div class="field">
                    <label class="field__label" for="name">상품명 *</label>
                    <input class="input" id="name" name="name" required
                           value="<?= $val('name') ?>" placeholder="예: tES LAB">
                </div>
                <div class="field">
                    <label class="field__label" for="product_family">제품군</label>
                    <input class="input" id="product_family" name="product_family"
                           value="<?= $val('product_family') ?>" placeholder="예: teslab">
                </div>
                <div class="field">
                    <label class="field__label" for="version">버전</label>
                    <input class="input" id="version" name="version"
                           value="<?= $val('version') ?>" placeholder="예: 3.0.1">
                </div>
                <div class="field">
                    <label class="field__label" for="license_type">라이선스 종류 *</label>
                    <select class="input" id="license_type" name="license_type" required>
                        <?php foreach ($licenseTypes as $lt): ?>
                            <option value="<?= esc($lt->value) ?>" <?= $val('license_type') === $lt->value ? 'selected' : '' ?>>
                                <?= esc($lt->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="period_code">기간정책</label>
                    <select class="input" id="period_code" name="period_code">
                        <option value="">— 선택 —</option>
                        <?php foreach ($periodCodes as $pc): ?>
                            <option value="<?= esc($pc->value) ?>" <?= $val('period_code') === $pc->value ? 'selected' : '' ?>>
                                <?= esc($pc->label()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">
            모듈
            <button type="button" class="btn btn--ghost" onclick="addModule()">+ 모듈 추가</button>
        </div>
        <div class="card__body">
            <div id="moduleRows" style="display:flex;flex-direction:column;gap:10px;"></div>
            <p class="muted mb-0" style="margin-top:8px;font-size:12px;">모듈 코드와 이름을 입력합니다. (예: MD001 / 정량분석)</p>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/admin/products" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const EXISTING_MODULES = <?= json_encode(array_map(static fn ($m) => ['code' => $m['code'], 'name' => $m['name']], $modules)) ?>;

    function moduleRow(code = '', name = '') {
        const row = document.createElement('div');
        row.style.cssText = 'display:grid;grid-template-columns:180px 1fr auto;gap:10px;';
        row.innerHTML = `
            <input class="input" name="module_code[]" placeholder="코드 (MD001)" value="${code.replace(/"/g, '&quot;')}">
            <input class="input" name="module_name[]" placeholder="모듈명" value="${name.replace(/"/g, '&quot;')}">
            <button type="button" class="btn btn--ghost" onclick="this.parentNode.remove()">삭제</button>`;
        return row;
    }
    function addModule(code, name) {
        document.getElementById('moduleRows').appendChild(moduleRow(code, name));
    }
    (EXISTING_MODULES.length ? EXISTING_MODULES : [{ code: '', name: '' }])
        .forEach(m => addModule(m.code, m.name));
</script>
<?= $this->endSection() ?>
