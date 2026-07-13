<?php
/**
 * 감사로그 목록.
 *
 * @var list<\App\Enums\AuditEventType> $eventTypes
 */
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">감사로그</h1>
    <p class="page-head__desc">부정사용 등 라이센스 감사 이벤트를 조회합니다.</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert--danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
            <input class="input" id="search" placeholder="라이센스키·호스트ID·IP 검색" style="max-width:260px;">
            <select class="input" id="eventFilter" style="max-width:160px;">
                <option value="">전체 이벤트</option>
                <?php foreach ($eventTypes as $e): ?>
                    <option value="<?= esc($e->value) ?>"><?= esc($e->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input" id="dateFrom" type="date" style="max-width:160px;" title="시작일">
            <span class="muted">~</span>
            <input class="input" id="dateTo" type="date" style="max-width:160px;" title="종료일">
            <button class="btn btn--ghost" onclick="reload(1)">검색</button>
        </div>

        <div id="auditGrid" class="ag-theme-alpine grid-wrap" style="height:460px;"></div>

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
    const EVENT_LABEL = <?= json_encode(array_reduce(
        $eventTypes,
        static fn (array $carry, \App\Enums\AuditEventType $e): array => $carry + [$e->value => $e->label()],
        [],
    ), JSON_UNESCAPED_UNICODE) ?>;
    const state = { page: 1, perPage: 20, lastPage: 1 };
    let api;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    const gridOptions = {
        columnDefs: [
            { field: 'id', headerName: 'ID', flex: 0.4 },
            { field: 'created_at', headerName: '발생일시', flex: 1 },
            { field: 'event_type', headerName: '이벤트', flex: 0.9,
              cellRenderer: p => `<span class="badge badge--warning">${esc(EVENT_LABEL[p.value] || p.value)}</span>` },
            { field: 'product_name', headerName: '상품', flex: 1, valueFormatter: p => p.value || '-' },
            { field: 'license_key', headerName: '라이센스키', flex: 1.1, valueFormatter: p => p.value || '-' },
            { field: 'client_host_id', headerName: '사용 호스트', flex: 1, valueFormatter: p => p.value || '-' },
            { field: 'ip', headerName: 'IP', flex: 0.8, valueFormatter: p => p.value || '-' },
            { field: 'ai_explanation', headerName: 'AI 설명', flex: 1.4,
              tooltipValueGetter: p => p.value || '', valueFormatter: p => p.value || '-' },
            { headerName: '', flex: 0.5, sortable: false,
              cellRenderer: p => `<a class="btn btn--ghost" style="padding:4px 10px" href="/admin/audit-logs/${p.data.id}">상세</a>` },
        ],
        defaultColDef: { sortable: true, resizable: true },
    };

    async function reload(page) {
        if (page < 1 || page > state.lastPage) return;
        state.page = page;
        const params = new URLSearchParams({
            search: document.getElementById('search').value,
            event_type: document.getElementById('eventFilter').value,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value,
            page: state.page, per_page: state.perPage,
        });
        const json = await (await fetch(`/admin/audit-logs/data?${params}`)).json();
        api.setGridOption('rowData', json.data);
        state.lastPage = json.meta.last_page;
        document.getElementById('pageInfo').textContent = `총 ${json.meta.total}건 · ${json.meta.page}/${json.meta.last_page} 페이지`;
        document.getElementById('prevBtn').disabled = json.meta.page <= 1;
        document.getElementById('nextBtn').disabled = json.meta.page >= json.meta.last_page;
    }

    api = agGrid.createGrid(document.getElementById('auditGrid'), gridOptions);
    document.getElementById('search').addEventListener('keyup', e => { if (e.key === 'Enter') reload(1); });
    reload(1);
</script>
<?= $this->endSection() ?>
