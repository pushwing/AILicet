# Admin 뷰 개발 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/admin-view.md` 로 로드된다.

Admin 뷰 작성 시 아래를 반드시 참고한다.

- **UI 컴포넌트·CSS 클래스**: `docs/ui-guide.md`
- **브랜드 컬러·로고·타이포**: `assets/logo/`, `docs/design-system.md`
- CSS 파일: `ui/aicura.css` 를 레이아웃에 포함
- 컴포넌트 실물 확인: `ui/components.html`

## 뷰 렌더링 패턴

`BaseAdminController::render()`는 `$viewData`(세션의 `authUser` 포함)를 자동으로 병합한다. CI4 기본 `view()` 함수를 직접 호출하면 `authUser`가 누락되므로 반드시 `$this->render()`를 사용한다.

```php
// ✅ 올바른 방식
return $this->render('admin/campaigns/index', ['campaigns' => $campaigns]);

// ❌ 금지 — authUser 등 공통 데이터 누락
return view('admin/campaigns/index', ['campaigns' => $campaigns]);
```

## 데이터 그리드

목록성 화면(테이블)은 **AG Grid Community** 를 사용한다.

```html
<!-- CDN -->
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community/dist/ag-grid-community.min.js"></script>

<!-- 기본 사용 패턴 -->
<div id="myGrid" style="height:500px;" class="ag-theme-alpine"></div>
<script>
const gridOptions = {
    columnDefs: [
        { field: 'name', headerName: '캠페인명' },
        { field: 'status', headerName: '상태' },
    ],
    rowData: <?= json_encode($rows) ?>,
    pagination: true,
    paginationPageSize: 20,
};
agGrid.createGrid(document.getElementById('myGrid'), gridOptions);
</script>
```

- 테마: `ag-theme-alpine` 기본 사용
- 서버사이드 페이징이 필요한 경우 `serverSideDatasource` 적용
- `html` 셀 렌더링 시 `cellRenderer` 사용 (`innerHTML` 직접 조작 금지)

## 에디터

리치 텍스트 입력이 필요한 경우 **Tiptap** 을 사용한다. 빌드 단계 없이 ES 모듈 CDN으로 로드한다.

```html
<!-- 에디터 컨테이너 + 숨김 input (폼 제출용) -->
<div id="myEditor" style="min-height:120px;border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:8px;outline:none;"></div>
<input type="hidden" name="content" id="contentInput">

<script type="module">
import { Editor } from 'https://esm.sh/@tiptap/core@2'
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2'

const editor = new Editor({
    element: document.getElementById('myEditor'),
    extensions: [StarterKit],
    content: '',
    onUpdate: ({ editor }) => {
        document.getElementById('contentInput').value = editor.getHTML()
    },
})

// 내용 초기화
editor.commands.clearContent()

// 내용 설정 (기존 데이터 로드 시)
// editor.commands.setContent('<?= esc($content ?? '') ?>')

// fetch 제출 시 HTML 추출
// const html = editor.getHTML()
</script>
```

**툴바 버튼 예시** (Tiptap은 헤드리스이므로 직접 구성)

```js
// 툴바 버튼 → 에디터 커맨드 연결
document.getElementById('btnBold').addEventListener('click', () =>
    editor.chain().focus().toggleBold().run()
)
document.getElementById('btnItalic').addEventListener('click', () =>
    editor.chain().focus().toggleItalic().run()
)
document.getElementById('btnBullet').addEventListener('click', () =>
    editor.chain().focus().toggleBulletList().run()
)

// 활성 상태 표시
editor.on('transaction', () => {
    document.getElementById('btnBold').classList.toggle('is-active', editor.isActive('bold'))
    document.getElementById('btnItalic').classList.toggle('is-active', editor.isActive('italic'))
})
```

- 저장 시 출력은 반드시 `esc($content, 'html')` 또는 허용된 태그 화이트리스트 필터 적용
- 저장된 HTML 불러올 때: `editor.commands.setContent(savedHtml)`
- 구현 참고: `app/Views/admin/campaigns/show.php` (메모 에디터)

## 차트

통계·리포트 화면의 차트는 **Chart.js** 를 사용한다.

```html
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<canvas id="myChart"></canvas>
<script>
new Chart(document.getElementById('myChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: '광고비',
            data: <?= json_encode($values) ?>,
            backgroundColor: '#1D9E75',
        }],
    },
    options: { responsive: true },
});
</script>
```

- 브랜드 Primary 컬러 `#0F6E56` / Secondary `#1D9E75` 우선 사용
- 데이터는 컨트롤러에서 `$labels`, `$values` 형태로 분리해 전달
- 민감한 집계 데이터는 뷰에 직접 노출하지 않고 API 엔드포인트로 분리 고려

## 엑셀

엑셀 내보내기·읽기는 **PhpSpreadsheet** 를 사용한다.

```bash
composer require phpoffice/phpspreadsheet
```

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 내보내기
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray($rows, null, 'A1');

$response = service('response');
$response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
$response->setHeader('Content-Disposition', 'attachment; filename="export.xlsx"');
ob_start();
(new Xlsx($spreadsheet))->save('php://output');
return $response->setBody(ob_get_clean());

// 읽기
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
$rows = $spreadsheet->getActiveSheet()->toArray();
```

- 대용량(1만 행 이상)은 `ChunkReadFilter` 또는 청크 단위 처리 적용
- 업로드된 파일은 `public/` 외부 경로(`writable/uploads/`)에 저장 후 처리
- 처리 완료 후 임시 파일 즉시 삭제
