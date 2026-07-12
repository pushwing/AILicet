# 테스트 · 정적 분석 규칙

> 이 문서는 `CLAUDE.md` 에서 `@.claude/rules/testing.md` 로 로드된다.

## 정적 분석 (PHPStan)

코드 작성 후 반드시 정적 분석을 통과해야 한다.

```bash
composer analyse          # PHPStan 단독 실행
composer check            # PHPStan + PHPUnit 순차 실행
composer ci               # CS Fixer + PHPStan + PHPUnit (푸시 전 권장)
```

- 분석 레벨: **6** (`phpstan.neon`)
- 분석 대상: `app/` (Views 제외)
- 새 클래스·메서드 작성 시 `array<string, mixed>` 등 제네릭 타입 명시 필수
- `@phpstan-ignore` 주석으로 억제 금지 — 원인을 찾아 코드를 수정할 것

## 테스트

```bash
composer test                 # PHPUnit 단독 실행
```

- 단위 테스트: `tests/unit/` — 외부 의존성 Mock
- 통합 테스트: `tests/feature/` — `CIUnitTestCase` + DB 트랜잭션 롤백
- 커버리지 목표: **Service 레이어 80% 이상**
- 테스트 DB는 `.env.testing` 별도 설정 — 운영 DB 절대 사용 금지
- 새 기능 구현 시 테스트 코드를 함께 작성한다
