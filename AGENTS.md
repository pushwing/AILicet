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

- PHP: CLI 8.4.22, FrankenPHP 내장 8.5.7, CI `backend` 잡 PHP 8.5 (`composer.json` 요구사항 `^8.4`)
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

`.env`에는 최소한 `app.baseURL = http://localhost:8301/`, `database.default.hostname`, `database.default.database=aicura`, `database.default.username`, `database.default.password`, 32자 이상 `JWT_SECRET`을 설정한다. 리치 에디터 기능에는 선택적으로 `TINYMCE_API_KEY`를 설정한다. `.env`와 시크릿은 커밋하지 않는다.

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
| `app/Services/` | 유스케이스별 비즈니스 로직 |
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

현재 JWT는 레거시 `JwtLibrary`의 HMAC-SHA256 구현을 사용한다. 신규 JWT·서명·암호화 구현을 직접 추가하지 않으며, 인증 라이브러리 교체는 별도 보안 검토 작업으로만 수행한다.

```php
Auth::setUserId((int) $payload['sub']);

$userId = $this->authUserId();
```

## Admin 뷰

- UI 컴포넌트와 CSS 클래스는 `docs/ui-guide.md`, 브랜드 요소는 `assets/logo/`와 `docs/design-system.md`, 실제 구성은 `ui/components.html`을 참고한다.
- 레이아웃에는 `ui/aicura.css`를 포함한다.
- Admin 컨트롤러에서는 반드시 `BaseAdminController::render()`를 쓴다. 기본 `view()`를 직접 호출하면 `authUser`를 포함한 공통 `$viewData`가 누락된다.
- 목록 화면은 AG Grid Community와 기본 테마 `ag-theme-alpine`을 사용한다. 서버사이드 페이징에는 `serverSideDatasource`를 적용하고, HTML 셀 렌더링은 `cellRenderer`를 사용하며 `innerHTML` 직접 조작은 금지한다.
- 리치 텍스트는 빌드 없는 Tiptap ES 모듈 CDN 방식을 사용한다. 폼 제출에는 숨김 input 또는 `editor.getHTML()`을 쓰고, 기존 데이터는 `editor.commands.setContent(savedHtml)`로 로드한다. 저장·출력 시 `esc($content, 'html')` 또는 허용 태그 화이트리스트 필터를 적용한다. 구현은 `app/Views/admin/campaigns/show.php`를 참고한다.
- 차트는 Chart.js를 사용하며 Primary `#0F6E56`, Secondary `#1D9E75`를 우선한다. 집계 데이터는 컨트롤러에서 분리해 전달하고 민감 데이터는 API 분리를 검토한다.
- 엑셀 처리는 PhpSpreadsheet를 사용한다. 업로드 파일은 `writable/uploads/`에 보관하고 처리 후 임시 파일을 삭제한다. 1만 행 이상은 청크 처리한다.

## 검증·CI·배포

검증 흐름은 `feature/* → dev → main`이며, `feature/* → dev` PR에는 CI가 없다. 로컬 검증이 실질적인 게이트다.

- 개발 중에는 변경 범위에 맞춰 `composer analyse`, PHPUnit 부분 실행을 수행한다.
- `dev` push 전에는 루트에서 `composer ci`, `frontapi/`에서 `composer check`를 실행한다.
- `.githooks/pre-push`는 `dev` push 시 위 검증을 강제하고 `main` 직접 push를 차단한다. 문서 전용 변경은 자동으로 검사 생략된다.
- `.githooks/pre-commit`은 스테이징한 `*.php`에 PHP-CS-Fixer를 적용하고 다시 스테이징한다. `git add -p`로 부분 스테이징한 경우에는 스테이징하지 않은 변경까지 포함할 수 있으므로 `SKIP_HOOKS=1`을 사용한다.
- 긴급 우회는 `SKIP_HOOKS=1 git push ...`이며, PHP·Composer가 없는 환경에서는 로컬 검증을 건너뛰고 배포 PR CI가 최종 검증한다.
- `composer check`는 CS Fixer를 포함하지 않는다. 루트 전체 검증은 반드시 `composer ci`를 사용하고 스타일 문제는 `composer cs-fix`로 수정한다.
- GitHub Actions는 `.github/workflows/ci.yml`에서 `dev → main` 배포 PR 또는 수동 실행에만 수행하며, 같은 ref의 신규 실행은 이전 실행을 취소한다. self-hosted macOS ARM64 `mac-local-runner`에서 `backend`, `frontapi` 잡을 수행한다.
- self-hosted macOS 러너에서는 GitHub Actions의 `services:`를 사용하지 않는다. 각 잡에서 MySQL 8.0을 `docker run -p 127.0.0.1::3306`으로 기동하고, `docker inspect`로 배정된 포트를 읽어 `.env`와 `DB_PORT`에 전달하며 `if: always()`에서 정리한다. 고정 포트는 추가하지 않는다.
- `backend` 잡은 `pcov`와 `mbstring intl mysqli curl dom xml tokenizer` 확장을 사용하며 `writable/`을 생성한 뒤 CS Fixer → PHPStan → PHPUnit을 수행한다. `frontapi`는 순수 PHP 라이선스 인증 API이며 자체 `composer check`와 `DB_*` 환경변수를 사용한다. macOS의 `sed -i`는 빈 문자열 인자가 필요하다.
- `main` push 시 배포 워크플로가 SSH로 프로덕션에 배포한다. 서버 배포 순서는 `git reset --hard origin/main` → `writable/` 생성 → `composer install --no-dev --optimize-autoloader` → `php spark migrate --all -f` → `php spark cache:clear` → `sudo -n systemctl reload apache2`다.
- `spark migrate`는 예외에도 종료 코드 0을 반환할 수 있으므로 배포 워크플로의 출력 검사 로직을 제거하거나 약화하지 않는다.
- 배포 계정과 Apache의 소유권 충돌을 피하기 위해 `writable/` 권한 변경은 best-effort이며, 서버에서는 setgid 그룹 구성을 유지한다.

### 배포 운영 전제

- 배포 정의는 `.github/workflows/deploy.yml`이며 `main` push와 `workflow_dispatch`(롤백·재배포용 `ref`)로 실행한다. 동시성 그룹은 `deploy-production`, `cancel-in-progress: false`다.
- GitHub `production` 환경에는 `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PORT`, `DEPLOY_PATH`가 필요하다. 서버 저장소 리모트는 읽기 전용 deploy key를 이용한 SSH여야 한다.
- 대상은 Ubuntu + mod_php Apache 단일 서버다. Apache `DocumentRoot`는 `public/`이며, 배포 계정은 `systemctl reload apache2`를 비밀번호 없이 실행할 수 있어야 하고 `writable/`은 `www-data`가 쓸 수 있어야 한다.
- `writable/` 소유권 충돌 방지를 위해 `<DEPLOY_USER>:www-data` 그룹과 setgid 권한(`chmod -R 2775`)을 유지한다. 최초 배포 후 관리자 계정이 없으면 `php spark db:seed AdminUserSeeder`를 한 번 실행한다.
- `feature/*`는 `gh pr merge <PR번호> --squash --delete-branch`로 병합·삭제한다. GitHub 저장소의 `delete_branch_on_merge`는 `false`로 유지해 `dev`가 자동 삭제되지 않게 한다.

## 인프라 참고

- 기본 클라우드 방향: ECS(Fargate) + RDS + ElastiCache(Redis) + SQS
- 운영 시크릿: AWS SSM Parameter Store 또는 Secrets Manager
- 로그: 구조화 JSON 로그 지향
- 헬스체크: DB·캐시 연결 상태를 포함한 `GET /health` 제공 권장
