<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-end;">
    <div>
        <h1 class="page-head__title">라이센스</h1>
        <p class="page-head__desc">우리 대행사 고객에게 발급된 라이센스만 표시됩니다.</p>
    </div>
    <a href="/agency/licenses/new" class="btn btn--primary">+ 라이센스 발급</a>
</div>

<?php if (session()->getFlashdata('message')): ?><div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<div class="card"><div class="card__body">
    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
        <input class="input" id="search" placeholder="호스트ID·상품명 검색" style="max-width:260px;">
        <select class="input" id="typeFilter" style="max-width:150px;"><option value="">전체 종류</option>
            <?php foreach ($types as $t): ?><option value="<?= esc($t->value) ?>"><?= esc($t->label()) ?></option><?php endforeach; ?>
        </select>
        <select class="input" id="statusFilter" style="max-width:150px;"><option value="">전체 상태</option>
            <?php foreach ($statuses as $s): ?><option value="<?= esc($s->value) ?>"><?= esc($s->label()) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn--ghost" onclick="reload(1)">검색</button>
    </div>
    <div id="grid" class="ag-theme-alpine" style="height:460px;"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
        <span class="muted" id="pageInfo" style="font-size:13px;"></span>
        <div style="display:flex;gap:8px;">
            <button class="btn btn--ghost" id="prevBtn" onclick="reload(state.page-1)">이전</button>
            <button class="btn btn--ghost" id="nextBtn" onclick="reload(state.page+1)">다음</button>
        </div>
    </div>
</div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script>
    const TYPE_LABEL = { nodelock: '노드락', floating: '플로팅' };
    const STATUS = { active: ['success','정상'], suspended: ['warning','중지'], terminated: ['danger','종료'], archived: ['muted','보관'] };
    const state = { page: 1, perPage: 20, lastPage: 1 };
    let api;
    api = agGrid.createGrid(document.getElementById('grid'), {
        columnDefs: [
            { field: 'id', headerName: 'ID', flex: 0.4 },
            { field: 'product_name', headerName: '상품', flex: 1.1 },
            { field: 'license_type', headerName: '종류', flex: 0.7, cellRenderer: p => `<span class="badge badge--muted">${TYPE_LABEL[p.value] || p.value}</span>` },
            { field: 'host_id', headerName: '호스트ID', flex: 1, valueFormatter: p => p.value || '-' },
            { field: 'expire_date', headerName: '만료일', flex: 0.8, valueFormatter: p => p.value || '무기한' },
            { field: 'status', headerName: '상태', flex: 0.7, cellRenderer: p => { const [c,l] = STATUS[p.value] || ['muted',p.value]; return `<span class="badge badge--${c}">${l}</span>`; } },
            { headerName: '', flex: 0.5, sortable: false, cellRenderer: p => `<a class="btn btn--ghost" style="padding:4px 10px" href="/agency/licenses/${p.data.id}">상세</a>` },
        ],
        defaultColDef: { sortable: true, resizable: true },
    });
    async function reload(page) {
        if (page < 1 || page > state.lastPage) return;
        state.page = page;
        const params = new URLSearchParams({ search: document.getElementById('search').value, type: document.getElementById('typeFilter').value, status: document.getElementById('statusFilter').value, page, per_page: state.perPage });
        const json = await (await fetch(`/agency/licenses/data?${params}`)).json();
        api.setGridOption('rowData', json.data);
        state.lastPage = json.meta.last_page;
        document.getElementById('pageInfo').textContent = `총 ${json.meta.total}건 · ${json.meta.page}/${json.meta.last_page} 페이지`;
        document.getElementById('prevBtn').disabled = json.meta.page <= 1;
        document.getElementById('nextBtn').disabled = json.meta.page >= json.meta.last_page;
    }
    document.getElementById('search').addEventListener('keyup', e => { if (e.key === 'Enter') reload(1); });
    reload(1);
</script>
<?= $this->endSection() ?>
