# AILicet Codex 작업 지침

AI 기반 성형·토탈 광고 솔루션의 CodeIgniter 4 Admin + REST API 단일 프로젝트다.

## 작업 원칙

- 작업 전 `git status --short --branch`로 현재 브랜치와 사용자 변경을 확인한다. 기존 변경은 되돌리거나 덮어쓰지 않는다.
- 기본 브랜치 흐름은 `feature/* → dev → main`이다. `main`에는 직접 push하지 않는다.
- 기능 브랜치는 최신 `dev`에서 만든다. `feature/* → dev`는 squash merge 후 feature 브랜치를 삭제하고, `dev → main`은 merge commit으로 병합한다.
- 검증에 실패한 상태로 커밋·push하지 않는다. 검증을 실행할 수 없으면 이유를 명확히 보고한다.
- 코드·설정·빌드·CI·마이그레이션·시더를 변경했으면 feature PR 흐름을 따른다. 문서·주석만 바뀐 경우에만 `dev` 직접 커밋·push를 허용할 수 있다.
- 커밋 메시지는 `이모지 + Conventional Commits 접두어 + 한국어 설명` 형식을 사용한다. 예: `✨ feat: 캠페인 상태 필터 추가`

## 기술 스택과 로컬 실행

- PHP: CLI 8.4+, FrankenPHP 내장 8.5
- 웹 서버: FrankenPHP v1.12 (`make serve`, 포트 8301) 또는 CI4 내장 서버 (`make serve-spark`)
- 프레임워크: CodeIgniter 4
- 인증: Admin 세션, API JWT Bearer
- API 문서: Swagger UI `/api/docs` (`zircote/swagger-php`)
- 정적 분석: PHPStan level 6 (`app/`, Views 제외)

초기 설정:

```bash
cp env .env
composer install
php spark migrate
git config core.hooksPath .githooks
```

`.env`에는 최소한 `app.baseURL = http://localhost:8301/`, `database.default.*`, 32자 이상 `JWT_SECRET`을 설정한다. `.env`와 시크릿은 커밋하지 않는다.

자주 쓰는 명령:

```bash
make serve
make serve-spark
php spark migrate
php spark swagger:generate
php spark routes
composer test
composer analyse
composer cs
composer cs-fix
composer check
composer ci
```

## 구조와 구현 규칙

| 경로 | 역할 |
| --- | --- |
| `app/Controllers/Admin/` | 세션 인증 관리자 컨트롤러 |
| `app/Controllers/Api/V1/` | JWT 인증 REST API 컨트롤러 |
| `app/Models/` | Admin·API 공용 데이터 접근 계층 |
| `app/Filters/` | `AdminAuthFilter`, `JwtAuthFilter` |
| `app/Libraries/` | `JwtLibrary` 등 공통 라이브러리 |
| `app/Commands/` | Spark 커스텀 명령 |
| `docs/` | 프로젝트 문서 |
| `assets/logo/` | 브랜드 로고 SVG |
| `ui/` | UI 컴포넌트와 스타일 |

- Controller는 입력 검증 → Service 호출 → 응답 반환만 담당한다. 트랜잭션은 Service 레이어에서 관리한다.
- CI4 Model을 데이터 접근 계층으로 사용하고 `model(XxxModel::class)` 헬퍼를 우선한다.
- 원시 `$_GET`, `$_POST`, `$_REQUEST`, `$_FILES`를 직접 사용하지 않는다. Request 객체로 입력을 받고 검증한다.
- 사용자 제어 출력은 문맥에 맞게 이스케이프하며, SQL은 Query Builder 또는 바인딩을 쓴다.
- 새 PHP 코드는 `declare(strict_types=1)`, PSR-12, 명시적 타입을 따른다. PHPStan ignore로 문제를 숨기지 않는다.
- 상태처럼 제한된 값 집합은 Backed Enum을 우선 검토하고, 요청·응답 매핑에는 `final readonly` DTO를 우선 검토한다.
- 새 기능·수정에는 관련 PHPUnit 회귀 테스트를 추가한다.

### JWT 인증 흐름

`JwtAuthFilter`가 토큰 검증 뒤 `Auth::setUserId()`로 요청 사용자 ID를 저장한다. `BaseApiController` 상속 컨트롤러는 `$this->authUserId()`로 읽는다. 이 정적 홀더는 요청 컨텍스트 안에서만 사용한다.

```php
Auth::setUserId((int) $payload['sub']);

$userId = $this->authUserId();
```

## Admin 뷰

- UI 컴포넌트와 CSS 클래스는 `docs/ui-guide.md`, 브랜드 요소는 `assets/logo/`와 `docs/design-system.md`, 실제 구성은 `ui/components.html`을 참고한다.
- 레이아웃에는 `ui/aicura.css`를 포함한다.
- Admin 컨트롤러에서는 반드시 `BaseAdminController::render()`를 쓴다. 기본 `view()`를 직접 호출하면 `authUser`를 포함한 공통 `$viewData`가 누락된다.
- 목록 화면은 AG Grid Community와 기본 테마 `ag-theme-alpine`을 사용한다. HTML 셀 렌더링은 `cellRenderer`를 사용하며 `innerHTML` 직접 조작은 금지한다.
- 리치 텍스트는 빌드 없는 Tiptap ES 모듈 CDN 방식을 사용한다. 저장·출력 시 `esc($content, 'html')` 또는 허용 태그 화이트리스트 필터를 적용한다.
- 차트는 Chart.js를 사용하며 Primary `#0F6E56`, Secondary `#1D9E75`를 우선한다. 집계 데이터는 컨트롤러에서 분리해 전달하고 민감 데이터는 API 분리를 검토한다.
- 엑셀 처리는 PhpSpreadsheet를 사용한다. 업로드 파일은 `writable/uploads/`에 보관하고 처리 후 임시 파일을 삭제한다. 1만 행 이상은 청크 처리한다.

## 검증·CI·배포

검증 흐름은 `feature/* → dev → main`이며, `feature/* → dev` PR에는 CI가 없다. 로컬 검증이 실질적인 게이트다.

- 개발 중에는 변경 범위에 맞춰 `composer analyse`, PHPUnit 부분 실행을 수행한다.
- `dev` push 전에는 루트에서 `composer ci`, `frontapi/`에서 `composer check`를 실행한다.
- `.githooks/pre-push`는 `dev` push 시 위 검증을 강제하고 `main` 직접 push를 차단한다. 문서 전용 변경은 자동으로 검사 생략된다.
- `composer check`는 CS Fixer를 포함하지 않는다. 루트 전체 검증은 반드시 `composer ci`를 사용하고 스타일 문제는 `composer cs-fix`로 수정한다.
- GitHub Actions는 `dev → main` 배포 PR 또는 수동 실행에서만 실행된다. self-hosted macOS ARM64 러너에서 `backend`, `frontapi` 잡을 수행한다.
- CI의 MySQL 컨테이너는 임의 호스트 포트를 사용한다. 워크플로 수정 시 고정 포트를 추가하지 않는다. macOS의 `sed -i`는 빈 문자열 인자가 필요하다.
- `main` push 시 배포 워크플로가 SSH로 프로덕션에 배포한다. 배포는 `writable/` 생성 → `composer install --no-dev --optimize-autoloader` → `php spark migrate --all -f` → `php spark cache:clear` → Apache reload 순서다.
- `spark migrate`는 예외에도 종료 코드 0을 반환할 수 있으므로 배포 워크플로의 출력 검사 로직을 제거하거나 약화하지 않는다.
- 배포 계정과 Apache의 소유권 충돌을 피하기 위해 `writable/` 권한 변경은 best-effort이며, 서버에서는 setgid 그룹 구성을 유지한다.

## 인프라 참고

- 기본 클라우드 방향: ECS(Fargate) + RDS + ElastiCache(Redis) + SQS
- 운영 시크릿: AWS SSM Parameter Store 또는 Secrets Manager
- 로그: 구조화 JSON 로그 지향
- 헬스체크: DB·캐시 연결 상태를 포함한 `GET /health` 제공 권장
