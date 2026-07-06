<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-end;">
    <div>
        <h1 class="page-head__title">상품·모듈 관리</h1>
        <p class="page-head__desc">라이선스 발급의 기준이 되는 상품·모듈·기간정책을 관리합니다.</p>
    </div>
    <a href="/admin/products/new" class="btn btn--primary">+ 상품 등록</a>
</div>

<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <div id="productGrid" class="ag-theme-alpine grid-wrap" style="height:520px;"></div>
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

    function deleteProduct(id, name) {
        if (!confirm(`'${name}' 상품을 삭제할까요?`)) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = `/admin/products/${id}/delete`;
        form.innerHTML = `<input type="hidden" name="${CSRF_NAME}" value="${CSRF_HASH}">`;
        document.body.appendChild(form);
        form.submit();
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
</script>
<?= $this->endSection() ?>
