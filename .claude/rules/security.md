# 보안 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/security.md` 로 로드된다.

## PHP 절대 금지 — 보안

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

## 필수 준수 사항

- 입력값은 반드시 CI4 Validation 또는 `esc()` 처리
- SQL은 CI4 Query Builder만 사용 (raw query 금지)
- 시크릿은 `.env`에서만 관리 (`env('KEY')`) — 코드 하드코딩 절대 금지
- POST 폼에는 `<?= csrf_field() ?>` 필수 (Admin 뷰)
- `allowedFields` 없는 Model 금지 — 의도치 않은 mass assignment 방지
- CSRF 예외 라우트 무분별 추가 금지 — 보호 구멍
- 민감 데이터(비밀번호·토큰)는 `password_hash()` 처리 후 JSON 응답·디버그 로그에서 제외
