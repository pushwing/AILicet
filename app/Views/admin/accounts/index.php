<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head page-head--actions">
    <div>
        <h1 class="page-head__title">운영자 관리</h1>
        <p class="page-head__desc">AITessera 운영자 계정을 조회·수정하고 신규 운영자 계정을 생성합니다.</p>
    </div>
    <a href="/admin/accounts/new" class="btn btn--primary">+ 운영자 등록</a>
</div>

<?php if (session()->getFlashdata('message')): ?><div class="alert alert--info"><?= esc(session()->getFlashdata('message')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

<div class="card"><div class="card__body">
    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
        <input class="input" id="search" placeholder="이메일·이름 검색" style="max-width:260px;">
        <select class="input" id="activeFilter" style="max-width:150px;">
            <option value="">전체 상태</option>
            <option value="true">활성</option>
            <option value="false">비활성</option>
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
    const state = { page: 1, perPage: 20, lastPage: 1 };
    let api = agGrid.createGrid(document.getElementById('grid'), {
        columnDefs: [
            { field: 'id', headerName: 'ID', flex: 0.4 },
            { field: 'email', headerName: '이메일', flex: 1.3 },
            { field: 'name', headerName: '이름', flex: 0.8 },
            { field: 'company', headerName: '회사', flex: 1, valueFormatter: p => p.value || '-' },
            { field: 'is_active', headerName: '상태', flex: 0.6, cellRenderer: p => p.value
                ? '<span class="badge badge--success">활성</span>' : '<span class="badge badge--muted">비활성</span>' },
            { headerName: '', flex: 0.5, sortable: false, cellRenderer: p => `<a class="btn btn--ghost" style="padding:4px 10px" href="/admin/accounts/${p.data.id}/edit">수정</a>` },
        ],
        defaultColDef: { sortable: true, resizable: true },
    });
    async function reload(page) {
        if (page < 1 || page > state.lastPage) return;
        state.page = page;
        const params = new URLSearchParams({ page, per_page: state.perPage });
        const q = document.getElementById('search').value; if (q) params.set('q', q);
        const a = document.getElementById('activeFilter').value; if (a) params.set('is_active', a);
        const res = await fetch(`/admin/accounts/data?${params}`);
        const json = await res.json();
        // 토큰 만료 → 로그인으로 이동해 재획득
        if (json.code === 'SESSION_EXPIRED' && json.redirect) {
            alert(json.message || '세션이 만료되었습니다. 다시 로그인해 주세요.');
            window.location.href = json.redirect;
            return;
        }
        if (json.status !== 'success') { document.getElementById('pageInfo').textContent = json.message || '조회 실패'; return; }
        api.setGridOption('rowData', json.data);
        state.lastPage = json.meta.last_page || 1;
        document.getElementById('pageInfo').textContent = `총 ${json.meta.total ?? 0}건 · ${json.meta.page}/${state.lastPage} 페이지`;
        document.getElementById('prevBtn').disabled = (json.meta.page || 1) <= 1;
        document.getElementById('nextBtn').disabled = (json.meta.page || 1) >= state.lastPage;
    }
    document.getElementById('search').addEventListener('keyup', e => { if (e.key === 'Enter') reload(1); });
    reload(1);
</script>
<?= $this->endSection() ?>
