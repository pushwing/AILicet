# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI 기반 성형·토탈 광고 솔루션. CodeIgniter 4 기반 Admin + REST API 단일 프로젝트.

> **공통 규칙은 전역 [`~/.claude/CLAUDE.md`](~/.claude/CLAUDE.md) 에서 자동 상속**된다(언어·Git 워크플로우·보안·코드 스타일·테스트·API·LSP). 이 문서와 아래 `.claude/rules/` 는 **AILicet 저장소 전용** 규칙만 정의한다. 규칙 수정 시 해당 rule 파일을 편집한다.

## 기술 스택

- **언어**: PHP 8.4+ (시스템 CLI) / PHP 8.5 (FrankenPHP 내장)
- **웹 서버**: FrankenPHP v1.12 — `make serve` (포트 8300, 권장) / CI4 내장 — `make serve-spark`
- **프레임워크**: CodeIgniter 4
- **인증**: 세션(Admin) / JWT Bearer(API) — JWT는 외부 라이브러리 없이 `JwtLibrary`(HMAC-SHA256)로 직접 구현
- **API 문서**: Swagger UI (`/api/docs`) — `zircote/swagger-php`
- **정적 분석**: PHPStan 레벨 6 (`app/`, Views 제외)

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

## 아키텍처 핵심 패턴 — JWT 인증 흐름

`JwtAuthFilter`가 토큰을 검증한 뒤 `Auth::setUserId()`로 사용자 ID를 정적 홀더에 저장하고, 컨트롤러는 `$this->authUserId()`(= `Auth::userId()`)로 꺼내 쓴다. 별도 의존성 주입 없이 요청 컨텍스트 안에서만 유효하다.

```php
// JwtAuthFilter → Auth 홀더에 저장
Auth::setUserId((int) $payload['sub']);

// BaseApiController 상속 컨트롤러에서 사용
$userId = $this->authUserId();
```

## 상세 규칙 (`.claude/rules/`)

AILicet 고유 규칙은 아래 파일로 분리되어 있으며 `@import` 로 자동 로드된다.

| 규칙 파일 | 내용 |
|-----------|------|
| [Admin 뷰](.claude/rules/admin-view.md) | 뷰 렌더링·AG Grid·Tiptap·Chart.js·PhpSpreadsheet·브랜드 컬러 |
| [CI·CD·인프라](.claude/rules/ci-cd.md) | GitHub Actions·SSH 배포·서버 준비·클라우드 |

@.claude/rules/admin-view.md
@.claude/rules/ci-cd.md
