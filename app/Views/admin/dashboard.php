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

<!-- AI 자연어 질의 -->
<div class="card" style="margin-bottom:24px;">
    <div class="card__head">AI 인사이트 질의</div>
    <div class="card__body">
        <form id="aiQueryForm" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" id="aiQueryInput" placeholder="예: 이번 달 발급 건수, 만료 임박 몇 개, 부정사용 감지 추이"
                   style="flex:1;min-width:240px;padding:10px 12px;border:1px solid var(--color-border);border-radius:var(--radius-sm);outline:none;">
            <button type="submit" id="aiQueryBtn" class="btn btn--primary">질의</button>
        </form>
        <div id="aiQueryResult" style="margin-top:14px;display:none;">
            <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                <span id="aiQueryMetric" class="badge badge--muted"></span>
                <span id="aiQueryValue" style="font-size:24px;font-weight:700;color:#0F6E56;"></span>
            </div>
            <p id="aiQueryInsight" style="margin:8px 0 0;color:var(--color-text);line-height:1.6;"></p>
        </div>
    </div>
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
    // AI 자연어 질의 — POST 후 결과 렌더(값·인사이트)
    const aiForm = document.getElementById('aiQueryForm');
    const aiInput = document.getElementById('aiQueryInput');
    const aiBtn = document.getElementById('aiQueryBtn');
    const aiResult = document.getElementById('aiQueryResult');
    const aiMetric = document.getElementById('aiQueryMetric');
    const aiValue = document.getElementById('aiQueryValue');
    const aiInsight = document.getElementById('aiQueryInsight');

    aiForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = aiInput.value.trim();
        if (question === '') return;

        aiBtn.disabled = true;
        aiBtn.textContent = '분석 중…';
        try {
            const res = await fetch('/admin/dashboard/query', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question }),
            });
            const data = await res.json();

            aiResult.style.display = 'block';
            if (data.ok) {
                aiMetric.style.display = '';
                aiValue.style.display = '';
                aiMetric.textContent = `${data.metric} · ${data.period}`;
                aiValue.textContent = Number(data.value).toLocaleString() + '건';
            } else {
                aiMetric.style.display = 'none';
                aiValue.style.display = 'none';
            }
            aiInsight.textContent = data.insight || '';
        } catch (err) {
            aiResult.style.display = 'block';
            aiMetric.style.display = 'none';
            aiValue.style.display = 'none';
            aiInsight.textContent = '질의 처리 중 오류가 발생했습니다.';
        } finally {
            aiBtn.disabled = false;
            aiBtn.textContent = '질의';
        }
    });
</script>
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
