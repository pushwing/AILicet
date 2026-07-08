<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-alpine.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <h1 class="page-head__title">대시보드</h1>
    <p class="page-head__desc">라이선스 발급·사용 현황 요약</p>
</div>

<!-- 통계 카드 -->
<div class="stat-grid">
    <?php foreach ($stats as $s): ?>
        <div class="stat">
            <div class="stat__label"><?= esc($s['label']) ?></div>
            <div class="stat__value"><?= esc($s['value']) ?></div>
            <div class="stat__delta <?= esc($s['dir']) ?>"><?= esc($s['delta']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 차트 -->
<div class="card" style="margin-bottom:24px;">
    <div class="card__head">월별 라이선스 발급 추이</div>
    <div class="card__body">
        <canvas id="issueChart" height="90"></canvas>
    </div>
</div>

<!-- 목록 (AG Grid) -->
<div class="card">
    <div class="card__head">
        최근 라이선스
        <a href="/admin/licenses" class="btn btn--ghost">전체 보기</a>
    </div>
    <div class="card__body">
        <div id="licenseGrid" class="ag-theme-alpine grid-wrap" style="height:360px;"></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // 차트 — 컨트롤러 전달 데이터
    new Chart(document.getElementById('issueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart['labels']) ?>,
            datasets: [{
                label: '발급 건수',
                data: <?= json_encode($chart['values']) ?>,
                backgroundColor: '#1D9E75',
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });

    // 그리드 — 컨트롤러 전달 데이터
    const statusRenderer = (p) => {
        const map = { active: ['badge--success','정상'], suspended: ['badge--warning','중지'], terminated: ['badge--danger','종료'], archived: ['badge--muted','보관'] };
        const [cls, label] = map[p.value] || ['badge--muted', p.value];
        return `<span class="badge ${cls}">${label}</span>`;
    };
    agGrid.createGrid(document.getElementById('licenseGrid'), {
        columnDefs: [
            { field: 'sn', headerName: '라이선스 SN', flex: 1.2 },
            { field: 'product', headerName: '상품', flex: 1 },
            { field: 'type', headerName: '종류', flex: 0.8 },
            { field: 'customer', headerName: '고객', flex: 1 },
            { field: 'status', headerName: '상태', flex: 0.7, cellRenderer: statusRenderer },
            { field: 'expire', headerName: '만료일', flex: 0.9 },
        ],
        rowData: <?= json_encode($rows) ?>,
        pagination: true,
        paginationPageSize: 10,
        defaultColDef: { sortable: true, resizable: true },
    });
</script>
<?= $this->endSection() ?>
