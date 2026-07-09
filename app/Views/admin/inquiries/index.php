<?php
/**
 * 고객 문의 목록.
 *
 * @var list<\App\Enums\InquiryStatus>   $statuses
 * @var list<\App\Enums\InquiryCategory> $categories
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">문의관리</h1>
    <p class="page-head__desc">고객센터 문의를 조회하고 AI 답변 초안을 검토·확정 발송합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert--success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
            <input class="input" id="search" placeholder="제목·이메일 검색" style="max-width:240px;">
            <select class="input" id="statusFilter" style="max-width:150px;">
                <option value="">전체 상태</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= esc($s->value) ?>"><?= esc($s->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" id="categoryFilter" style="max-width:150px;">
                <option value="">전체 분류</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= esc($c->value) ?>"><?= esc($c->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--ghost" onclick="reload(1)">검색</button>
        </div>

        <div id="inquiryGrid" class="ag-theme-alpine grid-wrap" style="height:460px;"></div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
            <span class="muted" id="pageInfo" style="font-size:13px;"></span>
            <div style="display:flex;gap:8px;">
                <button class="btn btn--ghost" id="prevBtn" onclick="reload(state.page-1)">이전</button>
                <button class="btn btn--ghost" id="nextBtn" onclick="reload(state.page+1)">다음</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script>
    const STATUS_LABEL = <?= json_encode(array_reduce(
        $statuses,
        static fn (array $carry, \App\Enums\InquiryStatus $s): array => $carry + [$s->value => $s->label()],
        [],
    ), JSON_UNESCAPED_UNICODE) ?>;
    const CATEGORY_LABEL = <?= json_encode(array_reduce(
        $categories,
        static fn (array $carry, \App\Enums\InquiryCategory $c): array => $carry + [$c->value => $c->label()],
        [],
    ), JSON_UNESCAPED_UNICODE) ?>;
    const state = { page: 1, perPage: 20, lastPage: 1 };
    let api;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const statusClass = v => v === 'answered' ? 'success' : (v === 'closed' ? 'muted' : 'warning');

    const gridOptions = {
        columnDefs: [
            { field: 'id', headerName: 'ID', flex: 0.4 },
            { field: 'created_at', headerName: '접수일시', flex: 1 },
            { field: 'subject', headerName: '제목', flex: 1.6 },
            { field: 'ai_category', headerName: 'AI분류', flex: 0.8,
              cellRenderer: p => p.value ? `<span class="badge badge--muted">${esc(CATEGORY_LABEL[p.value] || p.value)}</span>` : '<span class="muted">-</span>' },
            { field: 'ai_draft_reply', headerName: 'AI초안', flex: 0.6, sortable: false,
              cellRenderer: p => p.value ? '✅' : '<span class="muted">-</span>' },
            { field: 'status', headerName: '상태', flex: 0.7,
              cellRenderer: p => `<span class="badge badge--${statusClass(p.value)}">${esc(STATUS_LABEL[p.value] || p.value)}</span>` },
            { headerName: '', flex: 0.5, sortable: false,
              cellRenderer: p => `<a class="btn btn--ghost" style="padding:4px 10px" href="/admin/inquiries/${p.data.id}">상세</a>` },
        ],
        defaultColDef: { sortable: true, resizable: true },
    };

    async function reload(page) {
        if (page < 1 || page > state.lastPage) return;
        state.page = page;
        const params = new URLSearchParams({
            search: document.getElementById('search').value,
            status: document.getElementById('statusFilter').value,
            category: document.getElementById('categoryFilter').value,
            page: state.page, per_page: state.perPage,
        });
        const json = await (await fetch(`/admin/inquiries/data?${params}`)).json();
        api.setGridOption('rowData', json.data);
        state.lastPage = json.meta.last_page;
        document.getElementById('pageInfo').textContent = `총 ${json.meta.total}건 · ${json.meta.page}/${json.meta.last_page} 페이지`;
        document.getElementById('prevBtn').disabled = json.meta.page <= 1;
        document.getElementById('nextBtn').disabled = json.meta.page >= json.meta.last_page;
    }

    api = agGrid.createGrid(document.getElementById('inquiryGrid'), gridOptions);
    document.getElementById('search').addEventListener('keyup', e => { if (e.key === 'Enter') reload(1); });
    reload(1);
</script>
<?= $this->endSection() ?>
