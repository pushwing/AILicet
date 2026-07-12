# Git 워크플로우 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/git-workflow.md` 로 로드된다.

```
feature/* → (PR) → dev → (PR) → main
```

- **PR 대상**: `feature/*` → `dev`
- **배포**: `dev` → `main` PR
- **머지 방식**:
  - `feature/*` → `dev`: **Squash and merge**
  - `dev` → `main`(배포): **Merge commit** (⚠️ Squash 금지 — 아래 주의)
- **머지 후**: `feature/*` 브랜치 자동 삭제
- `main`과 `dev`에 직접 push 금지 (단, **문서 전용 변경은 예외** — 아래 참조)

> ⚠️ **`dev → main` 배포 PR 을 Squash 로 머지하면 안 된다.** Squash 는 `dev` 커밋들을
> 새 커밋 하나로 눌러 `main` 을 `dev` 의 조상에서 이탈시킨다. 그러면 이후 `main`↔`dev`
> 동기화·배포마다 3-way 충돌이 재발한다. **반드시 merge commit** 으로 머지해
> `main` 이 `dev` 의 조상으로 유지되게 한다(배포 = fast-forward → 무충돌).

## 문서 전용 변경 예외 — `dev` 직접 반영

**문서만 변경된 경우**에는 `feature/*` 브랜치·PR 을 만들지 않고 `dev` 에 바로 커밋·push 한다. CI(PHPStan·PHPUnit·CS Fixer)에 영향이 없어 PR 검증이 불필요하기 때문이다.

### 예외 적용 대상 (모두 만족해야 함)

- 변경 파일이 **문서뿐**일 때만 적용:
  - `*.md` (`README.md`, `CLAUDE.md`, `.claude/rules/*.md`, `docs/**` 등)
  - 코드 주석 외 순수 문서 (다이어그램·이미지 등 문서 자산 포함)
- 아래가 **하나라도 포함되면 예외 불가 → 반드시 `feature/*` + PR**:
  - PHP·JS·CSS 등 소스 코드
  - `composer.json`·`phpstan.neon`·`phpunit.dist.xml`·`.github/**` 등 빌드·CI 설정
  - `.env` 관련 파일·마이그레이션·시더

### 절차

```bash
git checkout dev
git pull origin dev
# 문서 편집 후
git add <문서 파일들>
git commit -m "📝 docs: 변경 내용 요약"   # 커밋 접두어는 반드시 docs
git push origin dev
```

- 커밋 접두어는 **`docs`** 로 통일 (Conventional Commits)
- 코드 변경이 조금이라도 섞이면 이 예외를 쓰지 말고 `feature/*` 브랜치로 되돌린다

## 기능 개발 시작

```bash
git checkout dev
git pull origin dev
git checkout -b feature/기능명   # 예: feature/campaign-crud
```

## dev가 앞서간 경우 rebase

```bash
git rebase origin/dev
git push --force-with-lease origin feature/기능명
```

## 커밋 메시지 (Conventional Commits)

| 접두어 | 용도 |
|--------|------|
| `feat` | 새 기능 |
| `fix` | 버그 수정 |
| `refactor` | 리팩토링 |
| `docs` | 문서 |
| `chore` | 설정·빌드 |
| `test` | 테스트 |

자세한 내용: `docs/git-workflow.md`
