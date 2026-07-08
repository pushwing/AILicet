# AILicet UI 가이드

서버렌더링(Admin/대행사/고객) 화면 작성 시 참고하는 공통 UI 규약.
스타일 소스: [`public/assets/css/aicura.css`](../public/assets/css/aicura.css)

## 브랜드 컬러

| 토큰 | 값 | 용도 |
|------|-----|------|
| `--color-primary` | `#0F6E56` | 주요 액션·사이드바 |
| `--color-secondary` | `#1D9E75` | 강조·활성·차트 |
| `--color-danger` / `warning` / `info` | — | 시맨틱 상태 |

색상·간격·반경은 모두 CSS 변수(`:root`)로 정의되어 있으므로 하드코딩 대신 변수를 사용한다.

## 레이아웃

CI4 뷰 레이아웃 상속을 사용한다.

```php
<?= $this->extend('layouts/app') ?>   {/* 인증 셸: 사이드바+상단바 */}
<?= $this->section('content') ?> ... <?= $this->endSection() ?>
<?= $this->section('head') ?>    {/* 페이지별 CSS (예: AG Grid 테마) */}
<?= $this->section('scripts') ?> {/* 페이지별 JS (예: 차트 초기화) */}
```

- `layouts/app` — 로그인 후 공통 셸. 사이드바 메뉴는 `authUser.role`(UserRole) 에 따라 자동 분기
- `layouts/auth` — 로그인 등 비인증 화면(중앙 카드)
- 컨트롤러는 반드시 `BaseAdminController::render()` 사용 (authUser 자동 병합)

## 주요 컴포넌트 클래스

| 클래스 | 용도 |
|--------|------|
| `.page-head` / `.page-head__title` / `__desc` | 페이지 헤더 |
| `.stat-grid` / `.stat` / `.stat__value` / `.stat__delta.up\|down` | 통계 카드 |
| `.card` / `.card__head` / `.card__body` | 카드 컨테이너 |
| `.btn` `.btn--primary` `.btn--ghost` `.btn--block` | 버튼 |
| `.badge` `.badge--success\|warning\|danger\|muted` | 상태 배지 |
| `.field` / `.field__label` / `.input` | 폼 |
| `.alert` `.alert--danger\|info` | 알림 |
| `.tabs` / `.tab-btn` `.tab-btn.is-active` | 탭 내비게이션 (패널은 `.tab-panel`, JS로 전환) |

## 데이터 그리드 · 차트

- 목록: **AG Grid Community** (`ag-theme-alpine`) — CDN 로드, `cellRenderer` 로 배지 렌더
- 차트: **Chart.js** — 브랜드 컬러(`#1D9E75`) 사용, 데이터는 컨트롤러에서 `labels`/`values` 분리 전달
- 구현 예: [`app/Views/admin/dashboard.php`](../app/Views/admin/dashboard.php)

## 규칙

- 뷰 출력은 반드시 `esc()` 처리 (XSS 방지)
- POST 폼에는 `<?= csrf_field() ?>` 포함
- 뷰에서 Model 직접 호출 금지 — 컨트롤러가 전달한 데이터만 렌더
