<?php
/**
 * @var list<array{id:int, product_code:string, name:string, license_type:string, version:?string}> $products
 * @var list<array<string, mixed>>  $customers
 * @var list<\App\Enums\PeriodCode> $periodCodes
 */
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">라이센스 발급</h1>
    <p class="page-head__desc">우리 대행사 고객에게 라이센스를 발급합니다.</p>
</div>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<form method="post" action="/agency/licenses">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">라이센스 종류</div>
        <div class="card__body" style="display:flex;gap:20px;">
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="radio" name="license_type" value="nodelock" checked onchange="toggleType()"> 노드락</label>
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;"><input type="radio" name="license_type" value="floating" onchange="toggleType()"> 플로팅</label>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;"><div class="card__body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="field">
                <label class="field__label" for="customer_id">발급 고객 *</label>
                <select class="input" id="customer_id" name="customer_id" required>
                    <option value="">— 선택 —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= esc((string) $c['id']) ?>"><?= esc((string) $c['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="product_id">상품 *</label>
                <select class="input" id="product_id" name="product_id" required onchange="loadModules()">
                    <option value="">— 선택 —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= esc((string) $p['id']) ?>"><?= esc($p['product_code']) ?> · <?= esc($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="period_code">기간정책 *</label>
                <select class="input" id="period_code" name="period_code" required>
                    <?php foreach ($periodCodes as $pc): ?><option value="<?= esc($pc->value) ?>"><?= esc($pc->label()) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="expire_date">만료일</label>
                <input class="input" type="date" id="expire_date" name="expire_date">
            </div>
            <div class="field nodelock-only">
                <label class="field__label" for="host_id">호스트ID (유니크키) *</label>
                <input class="input" id="host_id" name="host_id" placeholder="머신 고유값">
            </div>
        </div>
    </div></div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">모듈</div>
        <div class="card__body"><div id="moduleList" class="muted">상품을 선택하면 모듈이 표시됩니다.</div></div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary">발급</button>
        <a href="/agency/licenses" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleType() {
        const t = document.querySelector('input[name="license_type"]:checked').value;
        document.querySelectorAll('.nodelock-only').forEach(e => e.style.display = t === 'nodelock' ? '' : 'none');
        document.getElementById('host_id').required = t === 'nodelock';
    }
    async function loadModules() {
        const pid = document.getElementById('product_id').value, box = document.getElementById('moduleList');
        if (!pid) { box.innerHTML = '상품을 선택하면 모듈이 표시됩니다.'; return; }
        const json = await (await fetch(`/agency/licenses/product-modules/${pid}`)).json();
        box.innerHTML = json.data.length ? json.data.map(m => `<label style="display:inline-flex;gap:6px;align-items:center;margin:4px 16px 4px 0;"><input type="checkbox" name="modules[]" value="${m.code}" checked> ${m.code} · ${m.name}</label>`).join('') : '<span class="muted">등록된 모듈이 없습니다.</span>';
    }
    toggleType();
</script>
<?= $this->endSection() ?>
