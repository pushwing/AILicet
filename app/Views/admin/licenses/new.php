<?php
/**
 * @var list<array{id:int, product_code:string, name:string, license_type:string, version:?string}> $products
 * @var list<array<string, mixed>>       $customers
 * @var list<\App\Enums\PeriodCode>      $periodCodes
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">라이센스 발급</h1>
    <p class="page-head__desc">노드락(파일) 또는 플로팅(키) 라이센스를 발급합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<form method="post" action="/admin/licenses">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">라이센스 종류</div>
        <div class="card__body">
            <div style="display:flex;gap:20px;">
                <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                    <input type="radio" name="license_type" value="nodelock" checked onchange="toggleType()"> 노드락 (오프라인 파일)
                </label>
                <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                    <input type="radio" name="license_type" value="floating" onchange="toggleType()"> 플로팅 (온라인 키)
                </label>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">발급 정보</div>
        <div class="card__body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="field">
                    <label class="field__label" for="product_id">상품 *</label>
                    <select class="input" id="product_id" name="product_id" required onchange="loadModules()">
                        <option value="">— 선택 —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= esc((string) $p['id']) ?>" data-type="<?= esc($p['license_type']) ?>">
                                <?= esc($p['product_code']) ?> · <?= esc($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="customer_id">발급 회원</label>
                    <select class="input" id="customer_id" name="customer_id">
                        <option value="">— 없음 —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= esc((string) $c['id']) ?>"><?= esc((string) $c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="period_code">기간정책 *</label>
                    <select class="input" id="period_code" name="period_code" required>
                        <?php foreach ($periodCodes as $pc): ?>
                            <option value="<?= esc($pc->value) ?>"><?= esc($pc->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="version">버전</label>
                    <input class="input" id="version" name="version" placeholder="예: 3.0.1">
                </div>
                <div class="field">
                    <label class="field__label" for="expire_date">만료일</label>
                    <input class="input" type="date" id="expire_date" name="expire_date">
                </div>
                <div class="field">
                    <label class="field__label" for="support_end_date">기술지원 종료일</label>
                    <input class="input" type="date" id="support_end_date" name="support_end_date">
                </div>

                <!-- 노드락 전용 -->
                <div class="field nodelock-only">
                    <label class="field__label" for="host_id">호스트ID (유니크키) *</label>
                    <input class="input" id="host_id" name="host_id" placeholder="머신 고유값">
                </div>

                <!-- 플로팅 전용 -->
                <div class="field floating-only" style="display:none;">
                    <label class="field__label" for="activate_term">활성화 간격(시간)</label>
                    <input class="input" type="number" id="activate_term" name="activate_term" value="24">
                </div>
                <div class="field floating-only" style="display:none;">
                    <label class="field__label" for="check_term">유효성 체크 간격(분)</label>
                    <input class="input" type="number" id="check_term" name="check_term" value="30">
                </div>

                <div class="field">
                    <label class="field__label" for="limit_count">사용 횟수 제한</label>
                    <input class="input" type="number" id="limit_count" name="limit_count" placeholder="미입력 시 무제한">
                </div>
                <div class="field">
                    <label class="field__label" for="limit_credit">크레딧 제한</label>
                    <input class="input" type="number" id="limit_credit" name="limit_credit" placeholder="세그플러스">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card__head">모듈</div>
        <div class="card__body">
            <div id="moduleList" class="muted">상품을 선택하면 모듈이 표시됩니다.</div>
        </div>
    </div>

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn--primary">발급</button>
        <a href="/admin/licenses" class="btn btn--ghost">취소</a>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function toggleType() {
        const type = document.querySelector('input[name="license_type"]:checked').value;
        document.querySelectorAll('.nodelock-only').forEach(e => e.style.display = type === 'nodelock' ? '' : 'none');
        document.querySelectorAll('.floating-only').forEach(e => e.style.display = type === 'floating' ? '' : 'none');
        document.getElementById('host_id').required = type === 'nodelock';
    }

    async function loadModules() {
        const pid = document.getElementById('product_id').value;
        const box = document.getElementById('moduleList');
        if (!pid) { box.innerHTML = '상품을 선택하면 모듈이 표시됩니다.'; return; }
        const json = await (await fetch(`/admin/licenses/product-modules/${pid}`)).json();
        if (!json.data.length) { box.innerHTML = '<span class="muted">등록된 모듈이 없습니다.</span>'; return; }
        box.innerHTML = json.data.map(m => `
            <label style="display:inline-flex;gap:6px;align-items:center;margin:4px 16px 4px 0;">
                <input type="checkbox" name="modules[]" value="${m.code}" checked> ${m.code} · ${m.name}
            </label>`).join('');
    }

    toggleType();
</script>
<?= $this->endSection() ?>
