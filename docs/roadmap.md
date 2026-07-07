# AILicet 개발 로드맵

> `docs/ailicet-architecture.md` 설계 문서를 기반으로 개발 항목을 단계별로 분리한 로드맵.
> 각 개발 항목은 GitHub 이슈로 등록되어 추적된다.

- 작성일: 2026-07-06
- 관련 이슈: #1 (레거시 분석)
- 설계 근거: [docs/ailicet-architecture.md](ailicet-architecture.md)

---

## 마일스톤 개요

| Phase | 목표 | 산출물 |
|-------|------|--------|
| **P0 기반** | 프로젝트 뼈대 · 인증 · 공통 UI | 개발환경, DB 스키마, AITessera 연동, 레이아웃 |
| **P1 마스터관리** | 상품/모듈/코드 관리 | 코드성 데이터 CRUD |
| **P2 라이센스 코어** | 발급 엔진(도메인) | 노드락 서명 생성, 플로팅 키 발급, 상태관리 |
| **P3 Admin** | 운영자 백오피스 | 회원관리, 라이센스 발급/관리 UI |
| **P4 대행사** | 대리점 페이지 | 하위 고객·라이센스 관리 |
| **P5 고객** | 고객 페이지 | 회원가입, 내 라이센스, 고객센터 |
| **P6 frontApi** | 클라이언트 인증 API | pure PHP, 노드락/플로팅 검증 |
| **P7 운영자동화** | CRON · 알림 · 로그 | 만료·부정사용 감지, 로그 파이프라인 |

의존 순서: **P0 → P1 → P2 → (P3·P4·P5·P6 병렬) → P7**

---

## 개발 항목 (= 이슈 단위)

### P0 · 기반
1. **CI4 프로젝트 스캐폴딩 & 개발환경** — FrankenPHP(`make serve`), `.env`, composer, PHPStan/PHPUnit, CI 워크플로우
2. **DB 스키마 마이그레이션 설계** — `license`/`history`/`licenseMap`/`customer`/`product`/`productModule`/`audit_log`. 타입별 흩어진 컬럼은 JSON config로 정규화, 인덱스 정의
3. **AITessera JWT 인증 연동** — `AdminAuthFilter`(세션)/`JwtAuthFilter`(Bearer), AITessera 토큰 검증 위임, 역할(Admin/대행사/고객) 인가
4. **공통 레이아웃·UI 셋업** — `ui/aicura.css`, 권한별 레이아웃, AG Grid/Chart.js 공통 세팅, `BaseAdminController::render()`

### P1 · 마스터관리
5. **상품·모듈 코드 관리 (Admin)** — 상품(노드락/플로팅 타입) CRUD, 모듈 관리, 라이센스 기간정책(periodCode) 관리

### P2 · 라이센스 코어
6. **노드락 라이센스 생성 엔진** — Ed25519 서명 + JSON 페이로드, S3 저장, 상품/버전별 Strategy 패턴, 개인키 KMS 보관
7. **플로팅 라이센스 발급** — 관리키(licenseKey) 생성, 키-온리 배포, activateTerm/checkTerm 정책
8. **라이센스 상태 관리** — 정지/종료/연장/재발급 + `history` 이력, 트랜잭션(Service 레이어)

### P3 · Admin
9. **Admin 회원관리** — 대행사/고객 가입·리스트·검색·페이징
10. **Admin 라이센스 관리 UI** — 발급 폼(노드락/플로팅), 리스트(검색/페이징), 상세보기·수정

### P4 · 대행사
11. **대행사 페이지** — 하위 고객 관리 + 라이센스 발급/조회 (Admin 축소판, 소유권 스코프 제한)

### P5 · 고객
12. **고객 페이지 + 고객센터** — 간단 회원가입(이메일 인증), 내 라이센스 조회, 이메일 문의·내 문의내역

### P6 · frontApi (pure PHP)
13. **frontApi 기반 구조** — pure PHP(PSR-7/15 + fast-route), PDO prepared statement, AITessera 토큰 검증, Rate Limit
14. **노드락 인증 API** — `getLicenseInfo`/`effectiveness`/`bypass`(사용정보 수집)
15. **플로팅 인증 API** — `activation`/`effectiveness`(잔여 카운트·크레딧)/`analysisStart`·`analysisEnd`(차감)

### P7 · 운영자동화
16. **CRON 배치 & 알림** — 만료 15/10일 알림, 종료 처리, 부정사용 감지, 슬랙 알림 (Spark Command + Scheduler)
17. **로그 수집 파이프라인** — `POST /api/v1/logs` → Redis 큐 → Consumer → 원시파일(`writable/logs/raw/`) + DB

---

## 범위 제외 / 보류 (중장기)

- 고객 **결제** 기능 (마인드맵상 중장기 계획)
- 알림 채널 확장(sms/브라우저 푸시) — 1차는 email/슬랙 우선
- 서버 지역화(멀티 리전), DDOS 정밀 제어

---

## 진행 관리

- 개발은 `feature/*` → `dev` → `main` 흐름 (설계: CLAUDE.md 「Git 워크플로우」)
- 각 이슈 완료 시 `docs/` 관련 문서 갱신 + 이슈 코멘트로 작업내역 기록 후 클로즈
