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
- `main`과 `dev`에 직접 push 금지

> ⚠️ **`dev → main` 배포 PR 을 Squash 로 머지하면 안 된다.** Squash 는 `dev` 커밋들을
> 새 커밋 하나로 눌러 `main` 을 `dev` 의 조상에서 이탈시킨다. 그러면 이후 `main`↔`dev`
> 동기화·배포마다 3-way 충돌이 재발한다. **반드시 merge commit** 으로 머지해
> `main` 이 `dev` 의 조상으로 유지되게 한다(배포 = fast-forward → 무충돌).

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
