# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI 기반 성형·토탈 광고 솔루션. 한 저장소에 **두 개의 독립 배포 단위**가 있다.

- **루트 (`app/`)** — CodeIgniter 4 기반 Admin/Agency/Client 포털. 세션 인증(`AdminAuthFilter`).
- **`frontapi/`** — 클라이언트 라이선스 프로그램(노드락·플로팅 인증) 전용 REST API. CI4 미사용, pure PHP(PSR-15 미들웨어 + PHP-DI + Relay). JWT 인증. 별도 `composer.json`으로 독립 관리되지만 루트 앱과 동일한 MySQL·Redis를 공유한다.

> **공통 규칙은 전역 [`~/.claude/CLAUDE.md`](~/.claude/CLAUDE.md) 에서 자동 상속**된다(언어·Git 워크플로우·보안·코드 스타일·테스트·API·LSP). 이 문서와 아래 `.claude/rules/` 는 **AILicet 저장소 전용** 규칙만 정의한다. 규칙 수정 시 해당 rule 파일을 편집한다.

## 기술 스택

- **언어**: PHP 8.4+ (시스템 CLI) / PHP 8.5 (FrankenPHP 내장)
- **웹 서버**: FrankenPHP v1.12 — `make serve` (포트 8300, 권장) / CI4 내장 — `make serve-spark`
- **프레임워크**: CodeIgniter 4
- **인증**: 세션(Admin/Agency/Client 포털) / JWT Bearer(`frontapi`) — 루트 앱은 라이선스 서명용 `JwtLibrary`(HMAC-SHA256)를 직접 구현, `frontapi`는 자체 `App\Support\Jwt`로 클라이언트 프로그램 토큰을 검증
- **API 문서**: Swagger UI (`/api/docs`, `frontapi`) — `zircote/swagger-php`
- **정적 분석**: PHPStan 레벨 6 (루트 `app/`, Views 제외 / `frontapi/src`)

> **frontapi는 별도 프로젝트다.** 루트에서 `composer install`/`composer analyse`/`composer test`를 돌려도 `frontapi/`는 검증되지 않는다 — 반드시 `cd frontapi && composer install`로 별도 설치·검증한다(상세: [CI·CD·인프라](.claude/rules/ci-cd.md)).

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
git config core.hooksPath .githooks   # 로컬 검증 게이트 활성화 (최초 1회, 상세: .claude/rules/ci-cd.md)
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
composer cs                   # php-cs-fixer 스타일 검사 (dry-run·diff)
composer cs-fix               # php-cs-fixer 스타일 자동 수정
composer check                # PHPStan + PHPUnit 순차 실행
composer ci                   # CS Fixer + PHPStan + PHPUnit (CI backend 잡과 동일)
```

`frontapi/`(별도 REST API 프로젝트)는 루트와 별개로 설치·검증한다:

```bash
cd frontapi
composer install
composer analyse              # PHPStan 레벨 6
composer test                 # PHPUnit (DB_HOST/DB_PORT 등 DB_* env 필요)
composer check                # analyse + test (CI frontapi 잡과 동일)
```

## 디렉토리 규칙

| 경로 | 용도 |
|------|------|
| `app/Controllers/Admin/` | 운영자(operator) 컨트롤러 (세션 인증) |
| `app/Controllers/Agency/` | 대행사(agency) 컨트롤러 (세션 인증) |
| `app/Controllers/Client/` | 고객(member) 컨트롤러 (세션 인증) |
| `app/Models/` | Admin/Agency/Client 공유 모델 |
| `app/Filters/` | `AdminAuthFilter`(세션, role별) · `JwtAuthFilter`(AITessera 발급 토큰 검증용 — 현재 라우트에 미연결) |
| `app/Libraries/` | `JwtLibrary`(라이선스 서명) 등 공통 라이브러리 |
| `app/Licensing/` | 라이선스 발급 전략·서명·저장 (Strategy/Signer/Storage) |
| `app/Integrations/` | 외부 연동 클라이언트 (AI Provider, AITessera) |
| `app/Queue/`, `app/Commands/` | 로그 큐 소비자·Spark 커스텀 커맨드(`logs:consume`, `AiClassifyLogs` 등) |
| `frontapi/` | **별도 배포 단위** — 클라이언트 라이선스 프로그램용 REST API (pure PHP, JWT 인증, 자체 `composer.json`) |
| `docs/` | 프로젝트 문서 |
| `assets/logo/` | 브랜드 로고 SVG |
| `ui/` | UI 컴포넌트 (aicura.css, components.html) |

## 아키텍처 핵심 패턴

### 루트 앱 — 세션 인증 + AITessera 토큰 검증 인프라

`AdminAuthFilter`가 세션을 검증해 role(operator/agency/member)별로 Admin/Agency/Client 라우트를 가른다. 별도로 `JwtAuthFilter`/`BaseApiController`가 AITessera 발급 토큰(`aitesseraToken` 서비스, `JwtVerifier`)을 검증하는 인프라로 존재하지만(`tests/unit/JwtAuthFilterTest.php`), **현재 `Config/Routes.php`에 연결된 라우트는 없다** — 실제 클라이언트향 REST API는 아래 `frontapi`가 담당한다.

```php
// JwtAuthFilter → Auth 홀더에 저장
Auth::setUser((int) $claims['sub'], $role, $claims['aff'] ?? null);

// BaseApiController 상속 컨트롤러에서 사용
$userId = $this->authUserId();
```

### `frontapi` — 클라이언트 라이선스 프로그램 REST API

CI4와 완전히 분리된 pure PHP 애플리케이션(`frontapi/src/App.php`). PSR-15 미들웨어를 Relay로 파이프라인 구성: `ErrorHandlerMiddleware → RateLimitMiddleware → JwtAuthMiddleware → RoutingMiddleware`. 노드락/플로팅 라이선스 활성화·검증(`/api/v1/licenses/*`, `/api/v1/floating/*`)과 로그 수집(`/api/v1/logs`)을 제공하며, 로그는 Redis 큐에 적재해 루트 앱의 `spark logs:consume`이 소비한다.

## 상세 규칙 (`.claude/rules/`)

AILicet 고유 규칙은 아래 파일로 분리되어 있으며 `@import` 로 자동 로드된다.

| 규칙 파일 | 내용 |
|-----------|------|
| [Admin 뷰](.claude/rules/admin-view.md) | 뷰 렌더링·AG Grid·Tiptap·Chart.js·PhpSpreadsheet·브랜드 컬러 |
| [CI·CD·인프라](.claude/rules/ci-cd.md) | GitHub Actions·SSH 배포·서버 준비·클라우드 |

@.claude/rules/admin-view.md
@.claude/rules/ci-cd.md
