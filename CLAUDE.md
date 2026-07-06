# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI 기반 성형·토탈 광고 솔루션. CodeIgniter 4 기반 Admin + REST API 단일 프로젝트.

## 언어 규칙

- 모든 응답은 반드시 한국어로 작성할 것
- 코드 주석도 한국어로 작성할 것
- 영어 응답은 절대 금지

## 기술 스택

- **언어**: PHP 8.4+ (시스템 CLI) / PHP 8.5 (FrankenPHP 내장)
- **웹 서버**: FrankenPHP v1.12 — `make serve` (포트 8300, 권장) / CI4 내장 — `make serve-spark`
- **프레임워크**: CodeIgniter 4
- **인증**: 세션(Admin) / JWT Bearer(API) — JWT는 외부 라이브러리 없이 `JwtLibrary`(HMAC-SHA256)로 직접 구현
- **API 문서**: Swagger UI (`/api/docs`) — `zircote/swagger-php`

> **PHP 버전 구분**  
> - 웹 요청 처리: FrankenPHP 내장 PHP 8.5.7  
> - CLI (composer/spark/PHPStan/PHPUnit): 시스템 PHP 8.4.22  
> - CI (GitHub Actions `backend` 잡): PHP 8.5 (setup-php)  
> - `composer.json` 요구사항은 `^8.4` (8.5 포함)

## 로컬 환경 설정

```bash
cp env .env          # env 파일을 .env로 복사 후 아래 필수 키 설정
composer install
php spark migrate
```

`.env` 필수 키:

```env
# 앱
app.baseURL = http://localhost:8300/

# DB
database.default.hostname = localhost
database.default.database = aicura
database.default.username = root
database.default.password =

# JWT (필수 — 32자 이상 랜덤 문자열)
JWT_SECRET = your-secret-key-here

# 기능별 선택
TINYMCE_API_KEY =    # 리치 에디터 사용 시
```

## 커맨드

```bash
make serve                    # 개발 서버 — FrankenPHP (포트 8300, 권장)
make serve-spark              # 개발 서버 — CI4 내장 (포트 8300)
php spark migrate             # DB 마이그레이션
php spark swagger:generate    # OpenAPI 스펙 생성 (public/swagger.json)
php spark routes              # 라우트 목록
composer test                 # PHPUnit 단독 실행
composer analyse              # PHPStan 단독 실행
composer check                # PHPStan + PHPUnit 순차 실행
```

## 디렉토리 규칙

| 경로 | 용도 |
|------|------|
| `app/Controllers/Admin/` | 관리자 컨트롤러 (세션 인증) |
| `app/Controllers/Api/V1/` | REST API 컨트롤러 (JWT 인증) |
| `app/Models/` | Admin·API 공유 모델 |
| `app/Filters/` | AdminAuthFilter / JwtAuthFilter |
| `app/Libraries/` | JwtLibrary 등 공통 라이브러리 |
| `app/Commands/` | Spark 커스텀 커맨드 |
| `docs/` | 프로젝트 문서 |
| `assets/logo/` | 브랜드 로고 SVG |
| `ui/` | UI 컴포넌트 (aicura.css, components.html) |

## Admin 뷰 개발

Admin 뷰 작성 시 아래를 반드시 참고한다.

- **UI 컴포넌트·CSS 클래스**: `docs/ui-guide.md`
- **브랜드 컬러·로고·타이포**: `assets/logo/`, `docs/design-system.md`
- CSS 파일: `ui/aicura.css` 를 레이아웃에 포함
- 컴포넌트 실물 확인: `ui/components.html`

### 데이터 그리드

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

### 에디터

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

### 차트

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

### 엑셀

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

## 아키텍처 핵심 패턴

### JWT 인증 흐름

`JwtAuthFilter`가 토큰을 검증한 뒤 `Auth::setUserId()`로 사용자 ID를 정적 홀더에 저장하고, 컨트롤러는 `$this->authUserId()`(= `Auth::userId()`)로 꺼내 쓴다. 별도 의존성 주입 없이 요청 컨텍스트 안에서만 유효하다.

```php
// JwtAuthFilter → Auth 홀더에 저장
Auth::setUserId((int) $payload['sub']);

// BaseApiController 상속 컨트롤러에서 사용
$userId = $this->authUserId();
```

### Admin 뷰 렌더링

`BaseAdminController::render()`는 `$viewData`(세션의 `authUser` 포함)를 자동으로 병합한다. CI4 기본 `view()` 함수를 직접 호출하면 `authUser`가 누락되므로 반드시 `$this->render()`를 사용한다.

```php
// ✅ 올바른 방식
return $this->render('admin/campaigns/index', ['campaigns' => $campaigns]);

// ❌ 금지 — authUser 등 공통 데이터 누락
return view('admin/campaigns/index', ['campaigns' => $campaigns]);
```

## API 응답 포맷

```php
// 성공
$this->success($data, $meta);
// → { "status": "success", "data": {...}, "meta": {...} }

// 실패
$this->error('ERROR_CODE', '메시지', $statusCode);
// → { "status": "error", "code": "...", "message": "..." }
```

### 페이지네이션 meta 표준

목록 API의 `meta`는 아래 4개 필드를 항상 포함한다.

```php
$this->success($items, [
    'page'      => (int) $page,
    'per_page'  => (int) $limit,
    'total'     => (int) $total,
    'last_page' => (int) ceil($total / $limit),
]);
```

### 에러 코드 네이밍 규칙

`UPPER_SNAKE_CASE` · `도메인_동사` 형식으로 통일한다.

| 에러 코드 | 용도 |
|-----------|------|
| `UNAUTHORIZED` | 인증 토큰 없음 |
| `TOKEN_EXPIRED` | 토큰 만료 |
| `INVALID_TOKEN` | 토큰 형식·서명 오류 |
| `INVALID_CREDENTIALS` | 이메일·비밀번호 불일치 |
| `VALIDATION_ERROR` | 유효성 검사 실패 |
| `NOT_FOUND` | 리소스 없음 |
| `ALREADY_EXISTS` | 중복 리소스 |
| `FORBIDDEN` | 권한 없음 |
| `INTERNAL_ERROR` | 서버 내부 오류 |

### REST URI 설계

- URI는 **복수 명사**: `/api/v1/users`, `/api/v1/campaigns`
- 버전 prefix 필수: `/api/v1/`
- URI에 **동사 금지**: `/getUser` ❌ → `GET /users/{id}` ✅
- 필터·정렬·페이지는 쿼리스트링: `?filter[status]=active&sort=-created_at&page=1&per_page=20`

### HTTP 상태코드

| 상황 | 코드 |
|------|------|
| 조회 성공 | 200 |
| 생성 성공 | 201 |
| 처리 성공 (응답 본문 없음) | 204 |
| 인증 실패 | 401 |
| 권한 없음 | 403 |
| 리소스 없음 | 404 |
| 유효성 검사 실패 | 422 |
| 서버 오류 | 500 |

## Swagger 어트리뷰트 규칙

새 API 엔드포인트마다 PHP 어트리뷰트 추가 필수.

```php
use OpenApi\Attributes as OA;

#[OA\Get(path: '/campaigns', summary: '...', security: [['bearerAuth' => []]], tags: ['Campaigns'], responses: [...])]
public function index() { ... }
```

## Git 워크플로우

```
feature/* → (PR) → dev → (PR) → main
```

- **PR 대상**: `feature/*` → `dev`
- **배포**: `dev` → `main` PR
- **머지 방식**:
  - `feature/*` → `dev`: **Squash and merge**
  - `dev` → `main`(배포): **Merge commit** (⚠️ Squash 금지 — 아래 주의)
- **머지 후**: `feature/*` 브랜치 자동 삭제
- `main`과 `dev`에 직접 push 금지

> ⚠️ **`dev → main` 배포 PR 을 Squash 로 머지하면 안 된다.** Squash 는 `dev` 커밋들을
> 새 커밋 하나로 눌러 `main` 을 `dev` 의 조상에서 이탈시킨다. 그러면 다음 배포마다
> `deploy.yml` 등에서 3-way 충돌이 재발한다. **반드시 merge commit** 으로 머지해
> `main` 이 `dev` 의 조상으로 유지되게 한다(배포 = fast-forward → 무충돌).

### 기능 개발 시작

```bash
git checkout dev
git pull origin dev
git checkout -b feature/기능명   # 예: feature/campaign-crud
```

### dev가 앞서간 경우 rebase

```bash
git rebase origin/dev
git push --force-with-lease origin feature/기능명
```

### 커밋 메시지 (Conventional Commits)

| 접두어 | 용도 |
|--------|------|
| `feat` | 새 기능 |
| `fix` | 버그 수정 |
| `refactor` | 리팩토링 |
| `docs` | 문서 |
| `chore` | 설정·빌드 |
| `test` | 테스트 |

자세한 내용: `docs/git-workflow.md`

## API 부하 분산 원칙

API 개발 시 부하 분산을 최우선으로 고려한다. 아래 원칙을 기본으로 적용한다.

### 캐시

- 변경 빈도가 낮은 조회 응답은 **Redis 캐시** 적용
- 캐시 키 규칙: `{리소스}:{식별자}:{파라미터해시}` (예: `campaigns:list:abc123`)
- TTL 기준

| 데이터 성격 | TTL |
|------------|-----|
| 설정·코드성 데이터 | 1시간 이상 |
| 목록·집계 | 5–60분 |
| 단건 상세 | 5–10분 |
| 실시간 필요 데이터 | 캐시 적용 금지 |

- 쓰기(INSERT·UPDATE·DELETE) 발생 시 관련 캐시 즉시 무효화
- 캐시 미스 시 DB 조회 후 캐시 저장 — 로직은 Service 레이어에서 처리

### 큐

- 즉시 응답이 불필요한 작업은 큐로 위임 (이메일·알림·로그·리포트 생성 등)
- API는 큐 적재 후 즉시 `202 Accepted` 반환
- 무거운 연산(배치 집계·엑셀 생성 등)은 절대 요청 사이클 안에서 처리 금지

### DB 쿼리

- `SELECT *` 금지 — 필요한 컬럼만 명시
- N+1 쿼리 금지 — 관계 데이터는 JOIN 또는 eager load
- 목록 API는 반드시 페이징 적용 (`limit` / `offset` 또는 커서 기반)
- 인덱스 없는 컬럼 `WHERE` 조건 금지 — 마이그레이션에 인덱스 함께 정의
- 집계 쿼리(`COUNT`, `SUM` 등)는 캐시 우선 적용

### API 응답

- 불필요한 필드 제거 — 응답 페이로드 최소화
- 목록 응답에 `meta.total`, `meta.page` 포함
- 대용량 데이터 응답은 스트리밍 또는 청크 분할 고려

### 기타

- 외부 API 호출은 타임아웃 설정 필수 (기본 5초)
- 외부 API 실패 시 재시도는 큐로 처리 (즉시 재시도 금지)
- 동일 엔드포인트 반복 호출 방어: Rate Limit 필터 적용 검토

## 로그 수집 파이프라인

프론트(앱/웹)에서 API로 전송되는 로그는 큐를 통해 비동기 처리한다.

### 흐름

```
앱/웹
  │
  │ POST /api/v1/logs
  ▼
API Server
  │ 큐에 적재 (즉시 응답)
  ▼
Queue (Redis)
  │
  ▼
Queue Consumer (Spark Command / Scheduler)
  ├── 원시 로그 → 파일 저장 (writable/logs/raw/YYYY-MM-DD.log)
  └── 가공 데이터 → DB INSERT
```

### 규칙

- API는 로그를 받는 즉시 큐에 넣고 `202 Accepted` 응답 — DB 직접 쓰기 금지
- 큐 드라이버: **Redis** (`predis/predis`)
- 원시(raw) 로그는 `writable/logs/raw/` 에 날짜별 파일로 append
- 가공 후 DB 저장 — 원시 파일은 보존 (감사·재처리 용도)
- Consumer는 Spark Command로 구현, CI4 Scheduler로 주기 실행
- 큐 처리 실패 시 dead-letter 로깅 필수 (`writable/logs/queue-failed/`)

### 기본 패턴

```php
// API Controller — 큐에 적재
public function store(): ResponseInterface
{
    $payload = $this->request->getJSON(true);
    // 유효성 검사 후
    Redis::lpush('log_queue', json_encode($payload));
    return $this->respond(null, 202);
}

// Spark Command — Consumer
class ProcessLogQueue extends BaseCommand
{
    public function run(array $params): void
    {
        while ($raw = Redis::rpop('log_queue')) {
            // 1. 원시 파일 저장
            file_put_contents(
                WRITEPATH . 'logs/raw/' . date('Y-m-d') . '.log',
                $raw . PHP_EOL,
                FILE_APPEND
            );
            // 2. 가공 후 DB 저장
            $data = $this->transform(json_decode($raw, true));
            model(LogModel::class)->insert($data);
        }
    }
}
```

## 정적 분석 (PHPStan)

코드 작성 후 반드시 정적 분석을 통과해야 한다.

```bash
composer analyse          # PHPStan 단독 실행
composer check            # PHPStan + PHPUnit 순차 실행
```

- 분석 레벨: **6** (`phpstan.neon`)
- 분석 대상: `app/` (Views 제외)
- 새 클래스·메서드 작성 시 `array<string, mixed>` 등 제네릭 타입 명시 필수
- `@phpstan-ignore` 주석으로 억제 금지 — 원인을 찾아 코드를 수정할 것

## PHP 언어 서버 (Intelephense LSP)

Claude Code 가 PHP 코드를 심볼 단위(정의 이동·참조 찾기·자동완성)로 정확히 다루도록 **Intelephense LSP** 를 연동한다. PHPStan 이 "타입 오류 검사"라면 Intelephense 는 "코드 구조 이해" 역할로 상호 보완한다.

> 이 연동은 **Claude Code CLI 세션 전용**이다. VS Code·JetBrains 확장에서 쓰는 Intelephense 와는 별개 인스턴스이므로 에디터에는 에디터대로 따로 설치한다.

### 설치 (최초 1회)

```bash
# 1. 바이너리 설치 (Node.js + npm 필요)
npm install -g intelephense

# 2. 로컬 LSP 플러그인 생성 (~/.claude/skills/ 하위 → 전 프로젝트 공용)
mkdir -p ~/.claude/skills/php-lsp-intelephense/.claude-plugin

cat > ~/.claude/skills/php-lsp-intelephense/.claude-plugin/plugin.json << 'EOF'
{
  "name": "php-lsp-intelephense",
  "description": "Intelephense PHP 언어 서버",
  "version": "1.0.0"
}
EOF

cat > ~/.claude/skills/php-lsp-intelephense/.lsp.json << 'EOF'
{
  "php": {
    "command": "intelephense",
    "args": ["--stdio"],
    "extensionToLanguage": { ".php": "php" }
  }
}
EOF
```

> ⚠️ 공식 `php-lsp@claude-plugins-official` 플러그인은 `.lsp.json` 이 누락되어 동작하지 않는다([이슈 #444](https://github.com/anthropics/claude-plugins-official/issues/444)). 위처럼 로컬 플러그인을 직접 만든다.

### 활성화·확인

- **활성화**: 새 Claude Code 세션을 시작하거나, 대화형 세션에서 `/reload-plugins` 실행 (플러그인은 세션 시작 시 로드된다)
- **확인**: `/help` 의 "Installed plugins" 에 `php-lsp-intelephense` 표시
- **동작 점검**: `intelephense --version` 은 플래그 미지원으로 에러를 뱉으니 정상 판정 근거로 쓰지 말 것. 실제 기동은 `--stdio` 모드의 `initialize` 응답으로 확인한다.

### 사용

개발자가 직접 실행하는 명령이 아니라, Claude 가 PHP 코드를 다룰 때 뒤에서 참조한다. "이 메서드 쓰는 곳 전부 찾아줘", "정의로 가줘" 같은 요청을 텍스트 grep 대신 심볼 단위로 정확히 처리한다.

- **무료 범위**: 정의 이동·참조 찾기·자동완성·심볼 검색 (충분)
- **프리미엄($25/년)**: 워크스페이스 전역 rename·고급 리팩토링

### Dart/Flutter LSP (`app-mobile/`)

`app-mobile/` Flutter 코드용 LSP. Dart SDK 에 언어 서버가 내장되어 **별도 설치가 없다**(플러그인 파일만 만들면 된다). PHP 쪽과 동일한 방식이며, 정의 이동·참조 찾기·call hierarchy·code action 을 제공한다.

```bash
mkdir -p ~/.claude/skills/dart-lsp/.claude-plugin

cat > ~/.claude/skills/dart-lsp/.claude-plugin/plugin.json << 'EOF'
{
  "name": "dart-lsp",
  "description": "Dart/Flutter 언어 서버 (analysis server)",
  "version": "1.0.0"
}
EOF

cat > ~/.claude/skills/dart-lsp/.lsp.json << 'EOF'
{
  "dart": {
    "command": "dart",
    "args": ["language-server", "--protocol=lsp"],
    "extensionToLanguage": { ".dart": "dart" }
  }
}
EOF
```

- **활성화·확인**: PHP LSP 와 동일 (`/reload-plugins` → `/help` 에 `dart-lsp` 표시)
- **동작 점검**: `--protocol=lsp` 모드의 `initialize` 응답으로 확인

## CI (GitHub Actions)

`dev` · `main` 으로의 **push / PR** 마다 자동 검증된다. 정의: `.github/workflows/ci.yml` (단일 파일, 두 잡 병렬).

- **동시성**: 같은 ref 새 푸시 시 진행 중 실행 취소 (`concurrency.cancel-in-progress`)

### `backend` 잡 — PHP 8.5 · PHPStan · PHPUnit

`mysql:8.0` 서비스 컨테이너를 띄우고 다음 순서로 검증한다.

1. setup-php `8.5` (확장: `mbstring intl mysqli curl dom xml tokenizer`, 커버리지 `pcov`)
   - `phpunit.dist.xml` 이 `failOnWarning` + `<coverage>` 를 켜 두어 커버리지 드라이버 없으면 경고→실패 → `pcov` 필수
2. Composer 캐시 → `composer install`
3. `env` → `.env` 복사 후 CI용 DB·`JWT_SECRET` 주입
4. `writable/` 하위 디렉토리 생성 (git 미추적, `WRITEPATH` 보장)
5. `composer analyse` (PHPStan level 6)
6. MySQL 헬스 대기 → `phpunit.dist.xml` 의 `database.tests.hostname` 을 `localhost` → `127.0.0.1` 로 sed 치환 (MySQLi TCP 강제)
7. `composer test` (PHPUnit 단위·DB 통합)

### `app` 잡 — Flutter · analyze · test

`app-mobile/` 작업 디렉토리에서 실행한다.

1. Flutter stable 채널 설치 → `flutter pub get`
2. `dart format --set-exit-if-changed lib test` (포맷 검사)
3. `flutter analyze`
4. `flutter test`

### 푸시 전 로컬 사전 검증

CI 실패를 줄이기 위해 푸시 전 동일 검증을 로컬에서 수행한다.

```bash
composer check   # = analyse + test (백엔드)
# 앱: cd app-mobile && dart format --output=none --set-exit-if-changed lib test && flutter analyze && flutter test
```

> 새 PHP 코드는 PHPStan level 6 통과 + 관련 PHPUnit 테스트가 그린이어야 CI를 통과한다. 새 기능에는 `tests/` 테스트를 함께 작성한다.

## CD (배포)

`main` push(= `dev → main` PR 머지) 시 프로덕션 서버로 **SSH 자동 배포**된다. 정의: `.github/workflows/deploy.yml`.

- **트리거**: `main` push + `workflow_dispatch`(수동·롤백)
- **동시성**: `deploy-production` 그룹 — 배포 동시 실행 1개, `cancel-in-progress: false`
- **대상**: Ubuntu + mod_php 아파치 단일 서버 (appleboy/ssh-action)

### 배포 절차 (서버 SSH 실행)

1. `git reset --hard origin/main` — 최신 main 반영
2. `writable/` 디렉토리 생성 — **반드시 composer/migrate 이전** (없으면 spark 부팅 실패 `WRITEPATH is not set correctly`)
3. `composer install --no-dev --optimize-autoloader`
4. `php spark migrate --all -f` — 출력을 grep 검사해 예외 감지 시 `exit 1` 로 배포 중단
5. `php spark cache:clear`
6. `sudo -n systemctl reload apache2` — OPcache 갱신(무중단)

> **spark migrate 함정**: DB 연결 실패·마이그레이션 예외가 나도 종료코드 0 을 반환한다. `set -e` 로 못 잡으므로 출력을 캡처해 예외 패턴(`[...Exception]`·`Unable to connect`·`Access denied`)을 직접 검사하고 실패 시 배포를 중단한다.

> **writable chmod 함정**: 런타임에 아파치(`www-data`)가 만든 `writable/cache`·`session` 파일은 배포 계정 소유가 아니라 `chmod -R 775 writable` 가 `Operation not permitted` 로 실패한다. `set -e` 로 배포가 중단되지 않도록 이 `chmod` 는 best-effort(`2>/dev/null || echo …`)로 처리한다. 근본 해결은 아래 서버 준비의 setgid 구성이다.

### 필요한 GitHub Secrets (`production` 환경)

`DEPLOY_HOST` · `DEPLOY_USER` · `DEPLOY_SSH_KEY` · `DEPLOY_PORT` · `DEPLOY_PATH`

### 서버 사전 준비 (한 번만)

- **GitHub 읽기전용 deploy key** — 서버 저장소 리모트를 SSH(`git@github.com:...`)로 설정 (HTTPS면 `could not read Username` 실패)
- **프로덕션 `.env`** 에 실제 DB 접속정보 (없으면 migrate 시 `Access denied`)
- **비밀번호 없는 sudo**: `/etc/sudoers.d/aicura-deploy` 에 `<DEPLOY_USER> ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload apache2` (없으면 `sudo: a password is required` 로 실패)
- 아파치 `DocumentRoot` 는 `public/`, `writable/` 는 아파치 유저(`www-data`) 쓰기 가능
- **writable setgid 구성(권장)** — 소유권 충돌로 인한 chmod 실패를 근본 제거:
  ```bash
  sudo chown -R <DEPLOY_USER>:www-data <DEPLOY_PATH>/writable
  sudo chmod -R 2775 <DEPLOY_PATH>/writable   # setgid: 새 파일이 www-data 그룹 상속
  ```

### 배포 후 — 기본 관리자 계정 (최초 1회)

배포에는 마이그레이션만 포함되고 **시더는 자동 실행되지 않는다.** 관리자 계정(`admin@aicura.com` / `user_type=401`)이 없으면 서버에서 한 번 실행한다(재실행 안전).

```bash
cd <DEPLOY_PATH> && php spark db:seed AdminUserSeeder
```

### 브랜치 삭제 방지

저장소가 프라이빗+무료 플랜이라 GitHub 브랜치 보호·Ruleset API 는 사용 불가(Pro 필요). 대신 저장소 설정 `delete_branch_on_merge=false` 로 `dev → main` 머지 시 `dev` 자동삭제를 막는다. `main` 은 기본 브랜치라 삭제 불가.

## 네이밍 규칙

### PHP

| 대상 | 규칙 | 예시 |
|------|------|------|
| 클래스 | PascalCase | `CampaignController`, `JwtLibrary` |
| 인터페이스 | PascalCase + `Interface` | `PGInterface`, `AdapterInterface` |
| 추상 클래스 | `Base` 접두어 | `BaseApiController`, `BaseAdminController` |
| 메서드 | camelCase | `getAccessToken()`, `buildPaymentParams()` |
| 변수 | camelCase | `$accessToken`, `$campaignId` |
| 프로퍼티 | camelCase | `$authUserId`, `$refreshTtl` |
| 상수 | UPPER_SNAKE_CASE | `MAX_RETRY`, `DEFAULT_TTL` |
| 배열 키 | snake_case | `$data['access_token']`, `$payload['user_id']` |
| 파일명 | 클래스와 동일 | `CampaignController.php` |

### DB

| 대상 | 규칙 | 예시 |
|------|------|------|
| 테이블 | snake_case · 복수형 | `campaigns`, `ad_creatives`, `stock_logs` |
| 컬럼 | snake_case | `created_at`, `discount_price` |
| PK | `id` | `id` |
| FK | `{단수테이블명}_id` | `campaign_id`, `user_id` |
| 불리언 | `is_` 접두어 | `is_active`, `is_deleted` |
| 타임스탬프 | CI4 표준 | `created_at`, `updated_at`, `deleted_at` |
| 일반 인덱스 | `idx_{테이블}_{컬럼}` | `idx_campaigns_status` |
| 유니크 인덱스 | `uniq_{테이블}_{컬럼}` | `uniq_users_email` |
| Pivot 테이블 | 두 테이블 알파벳순 · 단수 | `campaign_tag` |

## 코딩 규칙

- PSR-12 준수
- 입력값은 반드시 CI4 Validation 또는 `esc()` 처리
- SQL은 CI4 Query Builder만 사용 (raw query 금지)
- 시크릿은 `.env`에서만 관리 (`env('KEY')`)
- POST 폼에는 `<?= csrf_field() ?>` 필수 (Admin 뷰)
- Model의 `$returnType`은 `'array'`로 통일 — `'object'` 혼용 금지

## PHP 절대 금지

### 보안

| 금지 | 이유 | 대신 |
|------|------|------|
| `$_GET`·`$_POST` 직접 사용 | 필터링 없는 원시 입력 | `$this->request->getPost()` |
| SQL 문자열 직접 조합 | SQL Injection | Query Builder / 바인딩 |
| `echo $변수` (뷰에서) | XSS | `echo esc($변수)` |
| `eval()` 사용 | 코드 인젝션 | 사용 이유 자체를 제거 |
| `md5()` / `sha1()`로 비밀번호 저장 | 취약한 해시 | `password_hash()` |
| 시크릿·API키 코드에 하드코딩 | 노출 위험 | `.env` + `env('KEY')` |
| CSRF 토큰 없이 POST 처리 | CSRF 공격 | `csrf_field()` |
| `$_FILES` 직접 처리 후 저장 | 악성 파일 업로드 | 확장자·MIME 검증 필수 |
| 에러 메시지에 스택 트레이스 노출 | 내부 구조 노출 | 운영 환경 `CI_ENVIRONMENT=production` |

### 코드 품질

| 금지 | 이유 |
|------|------|
| `@` 에러 억제 연산자 | 에러를 숨겨 디버깅 불가 |
| `extract($array)` | 변수 충돌·추적 불가 |
| `global $변수` | 상태 추적 불가, 테스트 불가 |
| `die()` / `exit()` 비즈니스 로직 안에 | 응답 흐름 단절, 테스트 불가 |
| 함수 하나에 100줄 이상 | 단일 책임 원칙 위반 |
| 의미 없는 변수명 (`$a`, `$tmp`, `$data2`) | 가독성 저하 |
| 주석으로 코드 비활성화 후 방치 | 죽은 코드 |
| `var_dump()` / `print_r()` 커밋 | 디버그 코드 노출 |

### PHP 특성 함정

| 금지 | 이유 | 대신 |
|------|------|------|
| `==` 타입 비교 | `0 == "a"` → true | `===` 사용 |
| `intval()` 없이 문자열을 숫자로 연산 | 타입 오염 | 명시적 형변환 또는 타입 선언 |
| 타입 선언 없는 함수 파라미터 | PHPStan 레벨 6 통과 불가 | `string $id`, `int $count` 명시 |
| `null` 반환과 `false` 반환 혼용 | 호출부 처리 혼란 | 반환 타입 통일 |
| `catch` 후 예외 무시 | 버그가 조용히 삼켜짐 | 최소한 로깅 |

### CI4 한정

| 금지 | 이유 |
|------|------|
| Controller에 비즈니스 로직 작성 | Model/Service로 위임 |
| `$db->query("... WHERE id = $id")` | SQL Injection |
| `allowedFields` 없는 Model | 의도치 않은 mass assignment |
| CSRF 예외 라우트 무분별 추가 | 보호 구멍 |
| `env()` 없이 Config에 직접 시크릿 작성 | `.env` 관리 원칙 위반 |
| 뷰에서 Model을 직접 호출해 데이터 조회 | MVC 책임 분리 위반, 테스트·유지보수 불가 |
| `new UserModel()` 직접 인스턴스화 | `model()` 헬퍼 우회 — `model(UserModel::class)` 사용 |

뷰는 컨트롤러가 전달한 데이터만 렌더링한다.

```php
// ❌ 금지 — 뷰에서 직접 조회
$campaigns = new \App\Models\CampaignModel();
foreach ($campaigns->findAll() as $item) { ... }

// ✅ 올바른 방식 — 컨트롤러에서 전달
// Controller
return $this->render('admin/campaigns/index', [
    'campaigns' => model(CampaignModel::class)->findAll(),
]);

// View
foreach ($campaigns as $item) { ... }
```

## PHP 모던 스타일 (8.4+)

상태·타입 관리는 배열·상수 대신 **readonly DTO**·**Backed Enum**을 우선한다.

```php
// ✅ readonly DTO — 요청·응답 데이터 매핑
final readonly class CreateUserRequest
{
    public function __construct(
        public string $email,
        public string $name,
        public UserRole $role = UserRole::Member,
    ) {}
}

// ✅ Backed Enum — 상태·타입은 Enum으로
enum UserRole: string
{
    case Admin  = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin  => '관리자',
            self::Member => '일반회원',
        };
    }
}

// ❌ 금지 — 배열·define()로 상태/타입 관리
define('ROLE_ADMIN', 1);
```

- 메서드·프로퍼티에 타입 선언(return type 포함) 완전 적용 (PHPStan 레벨 6 전제)
- `match` 표현식 우선 (`switch` 지양)
- DTO는 `final readonly`, 정적 팩토리(`fromRequest()`, `fromArray()`)로 생성

## 레이어 책임 (Controller · Service)

- **Controller는 얇게(thin)**: 유효성 검사 → Service 호출 → 응답 반환만 수행
- 비즈니스 로직이 Controller에 생기면 즉시 Service로 추출
- **하나의 Service 메서드 = 하나의 유스케이스**
- **DB 트랜잭션은 Service 레이어**에서 관리 (`$db->transStart()` / `transComplete()`)
- 데이터 접근은 `model(XxxModel::class)` 헬퍼 경유 (CLAUDE.md 네이밍·MVC 규칙 준수, 직접 `new` 금지)

```php
// ✅ 얇은 컨트롤러
class UserController extends BaseApiController
{
    public function store(): ResponseInterface
    {
        $dto    = CreateUserRequest::fromRequest($this->request);
        $result = service('userService')->create($dto);
        return $this->success($result, statusCode: 201);
    }
}
```

> 참고: 이 프로젝트는 별도 Repository 레이어를 두지 않고 CI4 `Model`을 데이터 접근 계층으로 사용한다. 복잡한 쿼리는 Model에 메서드로 캡슐화한다.

## 도메인 예외 처리

- 도메인 예외는 `app/Exceptions/` 에 커스텀 클래스로 정의
- 예외는 **HTTP 상태코드 + 에러 코드(문자열)** 를 반드시 포함 (에러 코드 네이밍 규칙 준수)
- 전역 핸들러는 `app/Config/Exceptions.php` 에 등록

```php
// app/Exceptions/DomainException.php
abstract class DomainException extends \RuntimeException
{
    abstract public function httpStatusCode(): int;
    abstract public function errorCode(): string;   // 예: 'USER_NOT_FOUND'
}
```

## 테스트

```bash
composer test                 # PHPUnit 단독 실행
```

- 단위 테스트: `tests/unit/` — 외부 의존성 Mock
- 통합 테스트: `tests/feature/` — `CIUnitTestCase` + DB 트랜잭션 롤백
- 커버리지 목표: **Service 레이어 80% 이상**
- 테스트 DB는 `.env.testing` 별도 설정 — 운영 DB 절대 사용 금지
- 새 기능 구현 시 테스트 코드를 함께 작성한다

## 클라우드·인프라 (참고)

- **AWS 기본 스택**: ECS(Fargate) + RDS + ElastiCache(Redis) + SQS
- **시크릿 관리**: `.env` 커밋 금지 — AWS SSM Parameter Store / Secrets Manager 사용
- **로그**: 구조화 로그(JSON) 지향
- **헬스체크**: `GET /health` 엔드포인트 (DB·캐시 연결 상태 포함) 제공 권장
