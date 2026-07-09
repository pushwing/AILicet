<?php
/**
 * 상품·모듈 관리 — 2탭(상품 목록 / 모듈 관리).
 *
 * @var list<array<string, mixed>> $products
 * @var list<array{id:int, code:string, name:string, is_active:int, product_count:int}> $modules
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">상품·모듈 관리</h1>
    <p class="page-head__desc">모듈을 먼저 등록한 뒤, 상품에서 모듈을 선택해 구성합니다. 상품에 저장된 모듈은 이후 변경할 수 없습니다.</p>
</div>

<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="tabs" style="margin-bottom:16px;">
    <button type="button" class="tab-btn is-active" data-tab="products" onclick="switchTab('products')">상품 목록</button>
    <button type="button" class="tab-btn" data-tab="modules" onclick="switchTab('modules')">모듈 관리</button>
</div>

<!-- 상품 목록 탭 -->
<div id="tab-products" class="tab-panel">
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <a href="/admin/products/new" class="btn btn--primary">+ 상품 등록</a>
    </div>
    <div class="card">
        <div class="card__body">
            <div id="productGrid" class="ag-theme-alpine grid-wrap" style="height:520px;"></div>
        </div>
    </div>
</div>

<!-- 모듈 관리 탭 -->
<div id="tab-modules" class="tab-panel" style="display:none;">
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">모듈 등록</div>
        <div class="card__body">
            <form method="post" action="/admin/modules" style="display:grid;grid-template-columns:220px 1fr auto;gap:12px;align-items:end;">
                <?= csrf_field() ?>
                <div class="field" style="margin:0;">
                    <label class="field__label" for="new_module_code">모듈코드 *</label>
                    <input class="input" id="new_module_code" name="code" required placeholder="예: MD001">
                </div>
                <div class="field" style="margin:0;">
                    <label class="field__label" for="new_module_name">모듈명 *</label>
                    <input class="input" id="new_module_name" name="name" required placeholder="예: 정량분석">
                </div>
                <button type="submit" class="btn btn--primary">등록</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card__body">
            <div id="moduleGrid" class="ag-theme-alpine grid-wrap" style="height:480px;"></div>
        </div>
    </div>
</div>

<!-- 모듈 수정 모달 -->
<div id="moduleEditBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;">
    <div class="card" style="max-width:440px;margin:90px auto;">
        <div class="card__head">모듈 수정</div>
        <div class="card__body">
            <form method="post" id="moduleEditForm">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="editCode">모듈코드 *</label>
                    <input class="input" id="editCode" name="code" required>
                </div>
                <div class="field">
                    <label class="field__label" for="editName">모듈명 *</label>
                    <input class="input" id="editName" name="name" required>
                </div>
                <div class="field">
                    <label class="field__label" for="editActive">상태</label>
                    <select class="input" id="editActive" name="is_active">
                        <option value="1">활성</option>
                        <option value="0">비활성</option>
                    </select>
                </div>
                <p id="editLockNote" class="muted" style="display:none;font-size:12px;margin-top:0;">
                    이미 상품에 연결된 모듈은 코드·이름을 변경할 수 없고 활성상태만 바꿀 수 있습니다.
                </p>
                <div style="display:flex;gap:10px;margin-top:12px;">
                    <button type="submit" class="btn btn--primary">저장</button>
                    <button type="button" class="btn btn--ghost" onclick="closeEdit()">취소</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script>
    const CSRF_NAME = '<?= csrf_token() ?>';
    const CSRF_HASH = '<?= csrf_hash() ?>';

    const LICENSE_LABEL = { nodelock: '노드락', floating: '플로팅' };
    const PERIOD_LABEL = {
        perpetual: '영구', period: '기간 제한', period_count: '기간+횟수',
        perpetual_count: '영구+횟수', perpetual_credit: '영구+크레딧',
    };
    const MODULES = <?= json_encode($modules) ?>;

    // ── 탭 전환 ─────────────────────────────────────────────
    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(p => p.style.display = p.id === `tab-${name}` ? '' : 'none');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('is-active', b.dataset.tab === name));
        history.replaceState(null, '', `/admin/products?tab=${name}`);
    }
    (function initTab() {
        const tab = new URLSearchParams(location.search).get('tab');
        if (tab === 'modules') switchTab('modules');
    })();

    // ── CSRF 폼 제출 헬퍼 ───────────────────────────────────
    function postForm(action) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = action;
        form.innerHTML = `<input type="hidden" name="${CSRF_NAME}" value="${CSRF_HASH}">`;
        document.body.appendChild(form);
        form.submit();
    }

    // ── 상품 그리드 ─────────────────────────────────────────
    function deleteProduct(id, name) {
        if (!confirm(`'${name}' 상품을 삭제할까요? (발급 이력이 있으면 삭제되지 않습니다)`)) return;
        postForm(`/admin/products/${id}/delete`);
    }

    agGrid.createGrid(document.getElementById('productGrid'), {
        columnDefs: [
            { field: 'product_code', headerName: '상품코드', flex: 0.9 },
            { field: 'name', headerName: '상품명', flex: 1.2 },
            { field: 'product_family', headerName: '제품군', flex: 0.8 },
            {
                field: 'license_type', headerName: '종류', flex: 0.7,
                cellRenderer: p => `<span class="badge badge--muted">${LICENSE_LABEL[p.value] || p.value}</span>`,
            },
            { field: 'version', headerName: '버전', flex: 0.7 },
            {
                field: 'period_code', headerName: '기간정책', flex: 0.9,
                valueFormatter: p => PERIOD_LABEL[p.value] || (p.value || '-'),
            },
            {
                field: 'is_active', headerName: '상태', flex: 0.6,
                cellRenderer: p => Number(p.value) === 1
                    ? '<span class="badge badge--success">활성</span>'
                    : '<span class="badge badge--muted">비활성</span>',
            },
            {
                headerName: '관리', flex: 1, sortable: false,
                cellRenderer: p => {
                    const id = p.data.id, name = (p.data.name || '').replace(/'/g, '\\\'');
                    return `<a class="btn btn--ghost" style="padding:4px 10px" href="/admin/products/${id}/edit">수정</a>
                            <button class="btn btn--ghost" style="padding:4px 10px" onclick="deleteProduct(${id}, '${name}')">삭제</button>`;
                },
            },
        ],
        rowData: <?= json_encode($products) ?>,
        pagination: true,
        paginationPageSize: 20,
        defaultColDef: { sortable: true, resizable: true },
    });

    // ── 모듈 그리드 ─────────────────────────────────────────
    function openEdit(id) {
        const m = MODULES.find(x => x.id === id);
        if (!m) return;
        const used = Number(m.product_count) > 0;
        document.getElementById('moduleEditForm').action = `/admin/modules/${id}`;
        document.getElementById('editCode').value = m.code;
        document.getElementById('editName').value = m.name;
        document.getElementById('editActive').value = String(m.is_active);
        document.getElementById('editCode').disabled = used;
        document.getElementById('editName').disabled = used;
        document.getElementById('editLockNote').style.display = used ? '' : 'none';
        document.getElementById('moduleEditBackdrop').style.display = '';
    }
    function closeEdit() {
        document.getElementById('moduleEditBackdrop').style.display = 'none';
    }
    function deleteModule(id, code) {
        if (!confirm(`'${code}' 모듈을 삭제할까요?`)) return;
        postForm(`/admin/modules/${id}/delete`);
    }

    agGrid.createGrid(document.getElementById('moduleGrid'), {
        columnDefs: [
            { field: 'code', headerName: '모듈코드', flex: 0.9 },
            { field: 'name', headerName: '모듈명', flex: 1.4 },
            {
                field: 'is_active', headerName: '상태', flex: 0.6,
                cellRenderer: p => Number(p.value) === 1
                    ? '<span class="badge badge--success">활성</span>'
                    : '<span class="badge badge--muted">비활성</span>',
            },
            {
                field: 'product_count', headerName: '사용 상품', flex: 0.7,
                valueFormatter: p => `${Number(p.value) || 0}개`,
            },
            {
                headerName: '관리', flex: 1, sortable: false,
                cellRenderer: p => {
                    const id = p.data.id, code = (p.data.code || '').replace(/'/g, '\\\'');
                    const used = Number(p.data.product_count) > 0;
                    const del = used
                        ? '<button class="btn btn--ghost" style="padding:4px 10px;opacity:.4;cursor:not-allowed" disabled title="사용 중 모듈은 삭제할 수 없습니다">삭제</button>'
                        : `<button class="btn btn--ghost" style="padding:4px 10px" onclick="deleteModule(${id}, '${code}')">삭제</button>`;
                    return `<button class="btn btn--ghost" style="padding:4px 10px" onclick="openEdit(${id})">수정</button> ${del}`;
                },
            },
        ],
        rowData: MODULES,
        pagination: true,
        paginationPageSize: 20,
        defaultColDef: { sortable: true, resizable: true },
    });
</script>
<?= $this->endSection() ?>
