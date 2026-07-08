<?php
/**
 * @var array<string, mixed>|null $product
 * @var list<array{id:int, product_id:int, code:string, name:string}> $modules
 * @var list<array{id:int, product_id:int, version:string}> $versions
 * @var list<array{id:int, code:string, name:string}> $masterModules
 * @var list<\App\Enums\LicenseType> $licenseTypes
 * @var list<\App\Enums\PeriodCode> $periodCodes
 */
$isEdit = $product !== null;
$action = $isEdit ? '/admin/products/' . $product['id'] : '/admin/products';
$val    = static fn (string $k, string $default = ''): string => esc((string) ($product[$k] ?? old($k) ?? $default));

// 버전 textarea 초기값: 활성 버전 목록(줄 단위). old() 우선(검증 실패 재입력 보존).
$versionsText = old('versions');
if ($versionsText === null) {
    $versionsText = implode("\n", array_map(static fn (array $v): string => $v['version'], $versions));
}
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
        <div class="card__head">버전</div>
        <div class="card__body">
            <p class="muted" style="margin-top:0;margin-bottom:12px;font-size:12px;">
                이 상품의 버전을 한 줄에 하나씩 입력합니다. 라이센스 발급 시 여기서 선택합니다.
                <?php if ($isEdit): ?>목록에서 제거한 버전은 비활성 처리되어 발급 목록에서 숨겨집니다(이력 보존).<?php endif; ?>
            </p>
            <div class="field" style="margin-bottom:0;">
                <label class="field__label" for="versions">버전 목록</label>
                <textarea class="input" id="versions" name="versions" rows="4"
                          placeholder="예:&#10;2.0.1&#10;1.0.1"><?= esc($versionsText) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">모듈</div>
        <div class="card__body">
            <?php if ($isEdit): ?>
                <?php /* 모듈 구성은 생성 시 확정 — 수정 불가(이미 판매된 상품 보호). 모듈 변경은 신규 상품으로. */ ?>
                <div class="alert alert--info" style="margin-bottom:12px;">
                    모듈 구성은 상품 생성 시 확정되며 수정할 수 없습니다. 모듈을 바꾸려면 상품을 새로 등록하세요.
                </div>
                <?php if ($modules === []): ?>
                    <p class="muted mb-0">연결된 모듈이 없습니다.</p>
                <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($modules as $m): ?>
                            <span class="badge badge--muted"><?= esc($m['code']) ?> · <?= esc($m['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($masterModules === []): ?>
                <p class="muted mb-0">
                    등록된 모듈이 없습니다.
                    <a href="/admin/products?tab=modules">모듈 관리</a> 탭에서 모듈을 먼저 등록하세요.
                </p>
            <?php else: ?>
                <p class="muted" style="margin-top:0;margin-bottom:12px;font-size:12px;">상품에 포함할 모듈을 선택합니다. 저장 후에는 변경할 수 없습니다.</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px 20px;">
                    <?php foreach ($masterModules as $m): ?>
                        <label style="display:inline-flex;gap:6px;align-items:center;">
                            <input type="checkbox" name="module_ids[]" value="<?= esc((string) $m['id']) ?>"
                                <?= in_array($m['id'], old('module_ids', []) ?: [], false) ? 'checked' : '' ?>>
                            <?= esc($m['code']) ?> · <?= esc($m['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary"><?= $isEdit ? '수정' : '등록' ?></button>
        <a href="/admin/products" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>
