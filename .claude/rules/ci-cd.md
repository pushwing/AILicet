# CI · CD · 인프라 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/ci-cd.md` 로 로드된다.

## 검증 게이트 — 어디서 무엇을 돌리는가

**검증은 로컬에서 끝낸다.** `feature/*` → `dev` PR 에는 CI 를 걸지 않고, CI 는 `dev` → `main` 배포 PR 에서만 돈다.

```
feature/*  ──[로컬 검증: git hooks]──▶  dev  ──[PR + CI]──▶  main
                    ↑                        ↑
              여기가 실질적 게이트        여기서만 CI 가 돈다
```

| 시점 | 무엇을 | 누가 |
|------|--------|------|
| 개발 중 | `composer analyse` + PHPUnit 부분 실행 (DB 불필요 범위) | 사람 / Claude, 수시로 |
| `dev` 푸시 전 | `composer ci`(루트) + `frontapi` `composer check` 필수 — 실패하면 푸시하지 않는다 | git hook(`pre-push`) 이 강제 |
| `feature/*` → `dev` PR | CI 없음. 코드 리뷰만 | — |
| `dev` → `main` PR | GitHub Actions 전체(`backend`·`frontapi` 잡: CS Fixer + PHPStan + PHPUnit) | CI |

`feature → dev` 에 CI 가 없다는 건 `dev` 브랜치가 검증받지 않은 코드를 받을 수 있다는 뜻이다. 그 상태로 여러 기능이 쌓인 뒤 배포 PR 에서 처음 CI 가 돌면 어느 커밋이 깨뜨렸는지 찾는 비용이 커지고 배포가 막힌다. **로컬 검증이 유일한 방어선이므로 생략은 규칙 위반이다.** Claude 가 작업할 때도 동일 — `dev` 로 올리는 PR 을 만들기 전에 위 명령을 실제로 실행하고 출력을 확인한 다음 진행한다. "통과할 것 같다"로 넘어가지 않는다.

### 훅으로 강제한다 — `.githooks/`

로컬 검증을 사람 기억에만 맡기면 반드시 빠진다. 훅이 저장소에 커밋돼 있으니 클론 직후 1회 활성화한다.

```bash
git config core.hooksPath .githooks
```

| 훅 | 동작 |
|----|------|
| `pre-commit` | 스테이징된 `*.php` 를 PHP-CS-Fixer 로 자동 수정 후 재-스테이징. 커밋을 막지는 않는다 |
| `pre-push` | 푸시 대상이 `dev` 일 때만 `composer ci`(루트) + `frontapi composer check` 실행. 실패 시 push 중단 |
| `pre-push` | `main` 직접 푸시는 무조건 차단. 배포는 `dev → main` PR(merge commit)로만 |

- `feature/*` 푸시는 검증하지 않는다 — 작업 중 빠른 반복을 막지 않기 위해서다.
- 문서 전용 변경(`*.md`, `docs/**`, `.claude/rules/**` 만 바뀐 푸시)은 `pre-push` 가 비교 대상 코드가 없다고 판단해 `composer` 검증을 자동으로 건너뛴다. 코드가 한 줄이라도 섞이면 즉시 전체 검증으로 돌아간다.
- 긴급 우회: `SKIP_HOOKS=1 git push ...`
- PHP·Composer 가 없는 환경에서는 해당 검증을 건너뛰고 CI(배포 PR)가 최종 검증한다.
- `git add -p` 로 부분 스테이징한 상태에서는 `pre-commit` 이 스테이징하지 않은 변경까지 커밋에 넣을 수 있다. 그때는 `SKIP_HOOKS=1` 을 쓴다.

## CI (GitHub Actions)

**배포 PR(`dev` → `main`)에서만** 자동 검증된다(+ `workflow_dispatch` 수동 실행). 정의: `.github/workflows/ci.yml` (단일 파일, `backend`·`frontapi` 두 잡).

```yaml
on:
  pull_request:
    branches: [main]     # dev 로 가는 PR 에서는 돌지 않는다
  workflow_dispatch:
```

`branches` 를 비워두거나 `dev` 를 추가하면 이 정책이 무의미해진다. `dev → main` 배포 PR 은 merge commit 으로 머지하므로(전역 규칙), CI 가 통과한 커밋 조합이 그대로 `main` 에 올라간다.

- **동시성**: 같은 ref 새 푸시 시 진행 중 실행 취소 (`concurrency.cancel-in-progress`)

### self-hosted 러너에서 돈다

GitHub 호스팅 러너(`ubuntu-latest`)가 아니라 **조직(`aivance-kr`) 레벨 self-hosted 러너 1개**를 등록해서 돈다. 두 잡 모두 `runs-on: [self-hosted, Linux, X64]`.

- **러너 구성**: 저장소별로 러너를 따로 두지 않고, `aivance-kr` 조직에 등록된 self-hosted 러너 1개를 AILicet 을 포함한 모든 저장소가 공유한다.
- **MySQL**: 각 잡에서 `docker run` 으로 직접 기동하고 `if: always()` 스텝으로 정리한다(Linux 러너는 `services:` 도커 컨테이너도 지원하지만, 아래 포트 사유로 `docker run` 방식을 유지한다).
- **포트**: 고정 포트를 쓰지 않는다. 이 러너는 여러 저장소가 **공유**해서, 특정 포트를 박아두면 여러 저장소가 동시에 CI를 돌릴 때 `port is already allocated` 로 충돌한다. 대신 `docker run -p 127.0.0.1::3306`(호스트 포트 생략)으로 도커가 빈 포트를 임의 배정하게 하고, `docker inspect` 로 실제 배정된 포트를 읽어 `$GITHUB_ENV` 에 저장해 이후 스텝(`.env` 구성, PHPUnit `DB_PORT`)에서 사용한다.
- **sed 문법**: 러너가 Linux(GNU sed)라 워크플로는 `sed -i "..."`(GNU 문법)를 쓴다. macOS BSD sed 는 `-i` 뒤에 빈 문자열 인자(`sed -i '' "..."`)가 필요해 문법이 다르므로, 로컬 macOS 에서 워크플로 스크립트를 그대로 실행하면 깨진다 — 주의.
- **호스팅 러너로 되돌리려면**: `runs-on` 을 `ubuntu-latest` 로 바꾸면 된다(포트도 표준값 `3306`으로 원복 가능. `docker run` 을 `services:` 블록으로 바꿔도 되지만 필수는 아니다).

### `backend` 잡 — PHP 8.5 · CS Fixer · PHPStan · PHPUnit

루트 CI4 프로젝트를 검증한다. 다음 순서로 실행한다.

1. MySQL 컨테이너 기동(`docker run -p 127.0.0.1::3306 mysql:8.0`, 임의 포트) → `mysqladmin ping` 으로 헬스 대기
2. setup-php `8.5` (확장: `mbstring intl mysqli curl dom xml tokenizer`, 커버리지 `pcov`)
   - `phpunit.dist.xml` 이 `failOnWarning` + `<coverage>` 를 켜 두어 커버리지 드라이버 없으면 경고→실패 → `pcov` 필수
3. Composer 캐시 → `composer install`
4. `composer cs` (php-cs-fixer `--dry-run` 스타일 검사)
5. `env` → `.env` 복사 후 CI용 DB(호스트 `127.0.0.1`·앞 단계에서 배정된 포트)·`JWT_SECRET` 주입
6. `writable/` 하위 디렉토리 생성 (git 미추적, `WRITEPATH` 보장)
7. `composer analyse` (PHPStan level 6)
8. `composer test` (PHPUnit 단위·DB 통합)
9. 컨테이너 정리 (`if: always()`)

### `frontapi` 잡 — pure PHP · PHPStan · PHPUnit

`frontapi/` 작업 디렉토리(클라이언트 프로그램용 라이선스 인증 API, 순수 PHP·CI4 미사용)를 검증한다.

1. MySQL 컨테이너 기동(`docker run -p 127.0.0.1::3306 mysql:8.0`, 임의 포트) → 헬스 대기
2. setup-php `8.5` (확장: `mbstring intl pdo_mysql curl dom xml tokenizer`)
3. `composer install`
4. `composer analyse` (PHPStan level 6)
5. `composer test` (PHPUnit — DB 접속정보는 `DB_HOST=127.0.0.1`·앞 단계에서 배정된 `DB_PORT` 등 `DB_*` env 로 주입)
6. 컨테이너 정리 (`if: always()`)

### 로컬 사전 검증 명령 (참고)

`dev` 푸시 전 검증은 위 `pre-push` 훅이 자동 실행하지만, 훅 없이 수동으로 돌릴 때는 동일 명령을 직접 실행한다.

```bash
composer ci                        # = CS Fixer + analyse + test (루트 백엔드) — CI backend 잡과 동일 순서
cd frontapi && composer check      # frontapi(pure PHP) — analyse + test
```

> ⚠️ `composer check`(analyse+test)는 **CS Fixer를 포함하지 않아** 스타일 위반을 놓친다. 루트 검증은 반드시 `composer ci`를 쓴다. CS 위반은 `composer cs-fix`로 자동 수정 후 커밋한다.

> 새 PHP 코드는 PHPStan level 6 통과 + 관련 PHPUnit 테스트가 그린이어야 배포 PR 의 CI 를 통과한다. 새 기능에는 `tests/` 테스트를 함께 작성한다.

## CD (배포)

`main` push(= `dev → main` PR 머지) 시 프로덕션 서버로 **SSH 자동 배포**된다. 정의: `.github/workflows/deploy.yml` (`appleboy/ssh-action`).

> ⚠️ **동작 전제**: 아래 GitHub Secrets(`production` 환경)와 서버 사전 준비가 끝나야 실제 배포가 성공한다. Secrets 미설정 상태에서는 잡이 실패한다. 롤백·재배포는 `workflow_dispatch`(수동 실행)에서 `ref` 를 지정한다.

- **트리거**: `main` push + `workflow_dispatch`(수동·롤백, `ref` 입력)
- **동시성**: `deploy-production` 그룹 — 배포 동시 실행 1개, `cancel-in-progress: false`
- **러너**: `ci.yml` 과 동일하게 조직(`aivance-kr`) 레벨 self-hosted 러너(`[self-hosted, Linux, X64]`)에서 돈다. GitHub 호스팅 러너(`ubuntu-latest`)는 계정 결제/스펜딩 리밋 문제로 잡 자체가 시작되지 못한 사례(2026-07-30)가 있어 전환했다 — `appleboy/ssh-action` 은 러너에서 프로덕션 서버로 SSH 접속만 하므로 self-hosted 에서도 동일하게 동작한다.
- **대상**: Ubuntu + mod_php 아파치 단일 서버 (`appleboy/ssh-action`)

### 배포 절차 (`deploy.yml` 이 SSH 로 서버에서 자동 실행 — 수동 실행 시 동일 순서)

1. `git reset --hard origin/main` — 최신 main 반영
2. `writable/` 디렉토리 생성 — **반드시 composer/migrate 이전** (없으면 spark 부팅 실패 `WRITEPATH is not set correctly`)
3. `composer install --no-dev --optimize-autoloader`
4. `php spark migrate --all -f` — 출력을 grep 검사해 예외 감지 시 `exit 1` 로 배포 중단
5. `php spark cache:clear`
6. `sudo -n systemctl reload apache2` — OPcache 갱신(무중단)

> **spark migrate 함정**: DB 연결 실패·마이그레이션 예외가 나도 종료코드 0 을 반환한다. `set -e` 로 못 잡으므로 출력을 캡처해 예외 패턴(`[...Exception]`·`Unable to connect`·`Access denied`)을 직접 검사하고 실패 시 배포를 중단한다.

> **writable chmod 함정**: 런타임에 아파치(`www-data`)가 만든 `writable/cache`·`session` 파일은 배포 계정 소유가 아니라 `chmod -R 775 writable` 가 `Operation not permitted` 로 실패한다. `set -e` 로 배포가 중단되지 않도록 이 `chmod` 는 best-effort(`2>/dev/null || echo …`)로 처리한다. 근본 해결은 아래 서버 준비의 setgid 구성이다.

### 필요한 GitHub Secrets (`production` 환경 — 자동화 시)

`deploy.yml` 도입 시 아래 Secrets 가 필요하다(수동 배포에는 불필요).

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

### 브랜치 자동 삭제 정책

- **`feature/*` (→ `dev` 머지 후)**: **자동 삭제**한다. `--delete-branch` 로 머지해 머지 완료와 동시에 로컬·원격 feature 브랜치를 정리한다.
  ```bash
  gh pr merge <PR번호> --squash --delete-branch
  ```
  수동 UI 머지 시엔 머지 후 "Delete branch" 버튼으로 정리한다.
- **`dev` (→ `main` 머지 후)**: **삭제하지 않는다.** `dev` 가 사라지면 배포 흐름·다음 PR 기준 브랜치가 깨진다.
- **`main`**: 기본 브랜치라 삭제 불가.

> ⚠️ GitHub 저장소 설정 `delete_branch_on_merge` 는 **저장소 전체 일괄 적용**이라 feature 만 골라 자동삭제할 수 없다. 그래서 `dev` 보호를 위해 이 설정은 **`false`** 로 두고(→ `dev → main` 머지 시 `dev` 자동삭제 방지), `feature/*` 삭제는 머지 명령의 **`--delete-branch` 로 개별 처리**한다. (프라이빗+무료 플랜은 브랜치 보호·Ruleset API 가 Pro 필요라 사용 불가.)

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

## 클라우드·인프라 (참고)

- **AWS 기본 스택**: ECS(Fargate) + RDS + ElastiCache(Redis) + SQS
- **시크릿 관리**: `.env` 커밋 금지 — AWS SSM Parameter Store / Secrets Manager 사용
- **로그**: 구조화 로그(JSON) 지향
- **헬스체크**: `GET /health` 엔드포인트 (DB·캐시 연결 상태 포함) 제공 권장
