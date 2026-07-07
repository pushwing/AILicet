<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-end;">
    <div>
        <h1 class="page-head__title">고객 관리</h1>
        <p class="page-head__desc">우리 대행사 소속 고객만 표시됩니다.</p>
    </div>
    <a href="/agency/customers/new" class="btn btn--primary">+ 고객 등록</a>
</div>

<?php if (session()->getFlashdata('message')): ?><div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<div class="card"><div class="card__body">
    <div style="display:flex;gap:10px;margin-bottom:14px;">
        <input class="input" id="search" placeholder="회사명·담당자·이메일 검색" style="max-width:280px;">
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
    const CSRF_NAME = '<?= csrf_token() ?>', CSRF_HASH = '<?= csrf_hash() ?>';
    const state = { page: 1, perPage: 20, lastPage: 1 };
    let api;
    function del(id, name) {
        if (!confirm(`'${name}' 고객을 삭제할까요?`)) return;
        const f = document.createElement('form'); f.method = 'post'; f.action = `/agency/customers/${id}/delete`;
        f.innerHTML = `<input type="hidden" name="${CSRF_NAME}" value="${CSRF_HASH}">`;
        document.body.appendChild(f); f.submit();
    }
    api = agGrid.createGrid(document.getElementById('grid'), {
        columnDefs: [
            { field: 'company_name', headerName: '회사명', flex: 1.2 },
            { field: 'name', headerName: '담당자', flex: 0.8 },
            { field: 'email', headerName: '이메일', flex: 1.2 },
            { field: 'phone', headerName: '연락처', flex: 0.9 },
            { field: 'is_active', headerName: '상태', flex: 0.6, cellRenderer: p => Number(p.value) === 1
                ? '<span class="badge badge--success">활성</span>' : '<span class="badge badge--muted">비활성</span>' },
            { headerName: '관리', flex: 1, sortable: false, cellRenderer: p => {
                const n = (p.data.company_name || '').replace(/'/g, '\\\'');
                return `<a class="btn btn--ghost" style="padding:4px 10px" href="/agency/customers/${p.data.id}/edit">수정</a>
                        <button class="btn btn--ghost" style="padding:4px 10px" onclick="del(${p.data.id}, '${n}')">삭제</button>`;
            } },
        ],
        defaultColDef: { sortable: true, resizable: true },
    });
    async function reload(page) {
        if (page < 1 || page > state.lastPage) return;
        state.page = page;
        const params = new URLSearchParams({ search: document.getElementById('search').value, page, per_page: state.perPage });
        const json = await (await fetch(`/agency/customers/data?${params}`)).json();
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
