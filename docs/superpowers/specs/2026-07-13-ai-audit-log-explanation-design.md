# AI 감사 로그 사람용 설명 자동 생성 (#83) — 설계

> Part of #66 (AI 기반 업무 효율화 Epic). 이번 슬라이스는 **도메인 로직(마이그레이션 + 서비스 + 테스트)**까지만 다룬다.

## 목표

`audit_logs`의 기계적 이벤트(`event_type` + `detail` JSON)를 저비용 AI로 **사람이 읽기 쉬운 한국어 설명**으로 사후 변환해 `ai_explanation` 컬럼에 채운다. 이미 검증된 `LogClassificationService`(logs) / `InquiryClassificationService`(inquiries) 패턴의 세 번째 인스턴스다.

## 범위

**포함**: 마이그레이션(컬럼+인덱스), `AuditLogModel` 확장, `AuditLogExplanationService`, DI 팩토리, 테스트.
**제외(이번 슬라이스 밖)**: 배치 Spark 커맨드, `Config/Tasks.php` 스케줄러 등록, 감사 화면 뷰 표시, `AuditLogQueryService` select 변경.

## 설계

### 1. 마이그레이션 — `audit_logs` 컬럼 추가 (logs 선례 준수)

- `ai_explanation` (TEXT, null) — 사람용 한국어 설명
- `ai_attempts` (TINYINT unsigned, default 0) — 실패 재시도 횟수
- `ai_processed_at` (DATETIME, null) — 처리 완료/dead-letter 마커
- `idx_audit_logs_ai_processed_at` 인덱스(raw ALTER)

### 2. `AuditLogModel`

- `$allowedFields`에 `ai_explanation`, `ai_attempts`, `ai_processed_at` 추가(누락 시 `update()` 무시 — LogModel 동일 함정)
- `findPendingAiExplanation(int $limit)`: `ai_processed_at IS NULL`을 오래된 순 조회

### 3. `AuditLogExplanationService::explainPending(int $limit = 100): array{processed:int, skipped:int}`

- AI 미설정(`isConfigured()==false`) 시 `{processed:0, skipped:0}` no-op
- 미처리 행을 조회해 각 행에 대해:
  - `AiClient::complete(AiModelTier::Cheap, ...)` (= `claude-haiku-4-5`)로 설명 생성
  - 프롬프트에 `event_type` + `AuditEventType::label()` + `detail`(JSON)을 함께 투입
  - 성공 시 `ai_explanation` + `ai_processed_at` UPDATE
  - 실패 시 `recordFailure()`: `ai_attempts++`, `MAX_ATTEMPTS=5` 도달 시 `ai_processed_at`만 마킹(설명은 null 유지)해 dead-letter 격리
- **모든 이벤트 균일 처리** — `AiAnomaly` 포함, event_type 필터 없음
- 파싱 실패(설명이 빈 문자열)는 예외로 던져 재시도·격리 대상이 되게 한다

### 4. DI — `Config/Services.php`에 `auditLogExplanationService()` 팩토리

## 테스트 (`tests/feature/AuditLogExplanationServiceTest.php`)

`LogClassificationServiceTest` 패턴 복제:
- 정상 설명 생성 → `ai_explanation`/`ai_processed_at` 채워짐
- 미설정 `NullAiClient` no-op
- 이미 처리된 행 skip
- 빈/깨진 응답 → 실패 카운트(`ai_attempts=1`, 마커·설명 null)
- `MAX_ATTEMPTS` 도달 시 dead-letter(마커 있음, 설명 null, 재선택 안 됨)

## 함정 체크리스트

- `AiModelTier::Cheap`이 이미 `claude-haiku-4-5` 매핑 → enum 수정 불필요
- `$allowedFields` 누락 주의
- dead-letter는 `ai_processed_at`만 마킹, `ai_explanation`은 null 유지(정상 설명과 구분)
