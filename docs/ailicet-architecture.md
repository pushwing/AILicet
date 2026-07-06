# AILicet 아키텍처 설계 문서

> 이슈 #1 「레거시 코드 분석」의 결과물. 레거시 3개 시스템(nebulaApi · nebulaFrontApi · LicenseMakerSource-cpp)과
> 기획 문서(마인드맵 · 데이터흐름도)를 분석해 신규 라이센스 매니저 **AILicet** 의 설계 방향을 정리한다.

- 작성일: 2026-07-06
- 관련 이슈: #1
- 참고 문서: `docs/라이센스 관리 솔루션_202003-v001.pdf`(마인드맵), `docs/nebula-데이터흐름도.pptx`, `docs/ailicet_intro.md`

---

## 1. 개요 — 무엇을 만드는가

레거시는 3개 시스템으로 구성되어 있으며, AILicet은 이를 아래와 같이 계승·재편한다.

| 레거시 | 역할 | 레거시 스택 | AILicet 대응 |
|--------|------|------------|--------------|
| **nebulaApi** | 운영자용 라이센스 발급·관리 백오피스 + API | CI3 (manager/v10/v20 3계층) | **CodeIgniter 4 서버렌더링** (어드민/대행사/고객) |
| **nebulaFrontApi** | 클라이언트 프로그램이 호출하는 인증 API | Pure PHP (자체 REST.php) | **frontApi (pure PHP)** |
| **LicenseMakerSource-cpp** | 노드락 라이센스 파일(.dat) 생성기 | C++ (AES-256 하드코딩) | **서버측 Ed25519 서명 방식으로 교체** |

### 전체 데이터 흐름

```
운영자(Admin) ──발급──> [AILicet 서버(CI4)] ──라이센스키/파일──> 고객
                             │  ├ 노드락: 서명 파일 생성 → S3 저장 → 다운로드
                             │  └ 플로팅: 관리키 발급 → 온라인 활성화/유효성 체크
클라이언트 프로그램 ──인증──> [frontApi(pure PHP)] ── DB
                             └ CRON: 만료 알림 / 부정사용 감지 / 슬랙 알림
회원 인증 ──────────────> [AITessera] (JWT HS256 인증 서비스 위임)
```

---

## 2. 라이센스 2종 동작 원리 (레거시 실측)

### 노드락 (Node-Locked, 오프라인 설치형)
- `hostId`(머신 고유값 32자) + 상품/모듈/기간을 담은 **암호화 파일 1개**를 발급 → S3 저장 → 고객 다운로드
- 클라이언트가 **오프라인에서 파일만으로** 검증: 매직값(`tESLAB`) → hostId 일치 → 만료일 → 모듈 비트플래그
- 재발급 시 hostId 변경 가능. 이전 키를 계속 쓰면 CRON이 **부정사용**으로 감지 → 슬랙 알림

### 플로팅 (Floating, 온라인 체크형)
- 파일 없이 **관리키(licenseKey 38~40자)만 배포**
- 클라이언트가 서버에 순차 호출:
  1. `activation` — hostId 등록
  2. `effectiveness` — 유효성 + 잔여 카운트/크레딧 확인
  3. `analysisStart` / `analysisEnd` — 사용량 차감
- `activateTerm`(기본 24h)·`checkTerm`(기본 30m) 간격으로 재확인

---

## 3. 이슈 요구사항별 설계 결정

### 3-1. 어드민 / 대행사 / 고객 페이지 (CI4 서버렌더링) — 요구사항 1·6
레거시는 운영자 화면만 있었으나 마인드맵은 **3개 역할**을 요구한다. 권한 계층으로 분리한다.

| 역할 | 범위 | 컨트롤러 경로 |
|------|------|--------------|
| **Admin** | 회원(대행사/고객) 가입·관리, 라이센스 발급(노드락/플로팅), 코드관리(상품/모듈) | `app/Controllers/Admin/` |
| **대행사(대리점)** | 자기 하위 고객 관리 + 라이센스 발급/조회 (Admin 축소판) | `app/Controllers/Agency/` |
| **고객** | 간단 회원가입, 내 라이센스 조회, 고객센터(이메일 문의) | `app/Controllers/Client/` |

- 모두 `BaseAdminController::render()` 계열 사용(공통 데이터 `authUser` 병합 규칙 준수)
- 목록 화면은 AG Grid, 통계는 Chart.js (프로젝트 UI 규칙)

### 3-2. frontApi (pure PHP) — 요구사항 2
- 레거시 nebulaFrontApi 구조(REST + 버전 디렉토리)를 계승
- **치명적 SQL 인젝션 제거**: 레거시는 `rawQuery`에 파라미터를 문자열로 직접 결합 → 전부 **PDO prepared statement**로 교체
- AITessera와 동일한 pure-PHP 스택(PSR-7/15 + `nikic/fast-route`) 채택으로 일관성 확보

### 3-3. 회원 인증은 AITessera 위임 — 요구사항 3
- AITessera = **pure PHP 8.4+, JWT HS256(`lcobucci/jwt`)** 인증 서비스
- 레거시의 자체 JWT(`include/jwt.php`) + 외부 Laxus/Conclave 위임 구조를 **AITessera 단일 위임**으로 대체
- frontApi·CI4 양쪽 모두 **AITessera 발급 토큰을 검증만** 수행 (레거시의 Laxus 자리 = AITessera)

### 3-4. 플로팅 배포 방식 — 요구사항 4 (미결정 → 결정)
**결정: 키만 배포한다.**

- 플로팅은 정의상 상시 온라인 → **서버가 진실의 원천(source of truth)**
- 파일까지 배포하면 (1) 오프라인 위·변조 여지 (2) 갱신 때마다 재배포 부담
- 네트워크 순단 대비: 최초 activation 응답에 **짧은 수명 캐시 토큰**(checkTerm 동안 유효한 서명 JWT) 발급
- 파일이 반드시 필요한 것은 **노드락뿐**

### 3-5. 노드락 생성 방식 — 요구사항 5 (미결정 → 결정)
**결정: C++ 생성기(LicenseMaker)를 폐기하고 서버측 Ed25519 서명 방식으로 교체한다.**

현재 C++의 문제(실측):
- AES-256 **대칭키가 바이너리에 하드코딩**(`"DAEGULE JSHTECH..."` 셔플) → 역어셈블 시 **누구나 위조 파일 생성 가능**
- 키 교체 = 재컴파일, 발급 이력·취소 불가

권장안 — **비대칭 서명(Ed25519)**:
```
[서버(PHP)]  개인키로 라이센스 JSON에 서명 → { data(base64), sig } 파일 배포
[클라이언트] 내장 공개키로 서명 검증 (개인키 없음 → 위조 불가)
```
- 개인키는 서버에만 존재 → 파일이 유출돼도 위조 불가(대칭키와 근본적으로 다름), **KMS/Vault 보관**
- PHP `sodium_crypto_sign`(libsodium 내장)으로 수 줄 구현, 재컴파일 불필요, 발급이력 DB 기록
- **레거시도 이미 이 방향으로 이행 중**: 최신 상품(테슬랩 3.2+/아쿠아 2.1.3+)은 C++을 버리고 서버 PHP의 RSA+AES 하이브리드로 전환함 → RSA 하이브리드보다 단순·고속인 **Ed25519**로 마무리

---

## 4. 계승 / 개선 매트릭스

| 구분 | 레거시에서 계승 | 반드시 개선 |
|------|----------------|-------------|
| DB | license / history / licenseMap / customer / product / productModule 스키마 | 타입별 흩어진 컬럼(limitCount·Credit) → JSON config, invalidLicense → 통합 audit_log |
| 보안 | RSA+AES 하이브리드 아이디어 | **SQL 인젝션 제거(PDO)**, 하드코딩 키 → KMS, 고정 IV → 랜덤 IV |
| 발급 | 상품/버전별 생성 분기 | 7갈래 if 분기 → **Strategy 패턴** |
| CRON | 만료 15/10일 알림·부정사용 감지·슬랙 | Controller 흉내 → **Spark Command + Redis Queue** |
| 로그 | S3 원시 로그 적재 | 비동기 큐 파이프라인(202 Accepted)으로 표준화 |

---

## 5. 핵심 DB 스키마 (레거시 복원 기반)

| 테이블 | 용도 | 주요 컬럼 |
|--------|------|----------|
| `license` | 라이센스 정보 | productId, version, periodCode, expireDate, supportEndDate, activateTerm, checkTerm, activateHistoryId, status, path |
| `history` | 발급/재발급 이력 | licenseId, type(1발급·4재발급), hostId, licenseSN, licenseKey, contents |
| `licenseMap` | 라이센스-고객 매핑 | licenseId, customerId, type |
| `customer` | 고객 정보 | name, chargeName, chargeEmail, chargePhone, companyName |
| `product` | 상품 정보 | name, productCode, productType, type(1노드락·2플로팅), version, moduleId |
| `productModule` | 상품-모듈 매핑 | productId, code, name |
| `invalidLicense` → `audit_log`(통합) | 부정사용/감사 기록 | licenseId, hostId, clientHostId, licenseKey |

> periodCode: 1=영구, 2=기간제한, 3=기간+카운트, 4=영구+카운트, 5=영구+크레딧
> status: 1=정상, 2=중지, 3=종료, 4=보관

---

## 6. 미결 항목 (마인드맵 `?` 표시 — 추가 확인 필요)

- **고객 결제**: 마인드맵에 "중장기 계획"으로 명시 → **1차 범위 제외**
- **알림 채널 우선순위**: sms / email / 브라우저 푸시 / 슬랙 중 착수 순서
- **인프라**: 서버 지역화(리전) 여부, DDOS 제어 수준(레거시는 미구현, AWS WAF 의존)

---

## 7. 결론

레거시의 기능은 완결되어 있으나 **① SQL 인젝션 ② 하드코딩 암호키 ③ 3계층 컨트롤러 중복**이 3대 기술 부채다.
AILicet은 이 셋을 걷어내고 다음 조합으로 재편한다.

> **CI4(어드민/대행사/고객) + pure PHP frontApi + AITessera 인증 위임 + Ed25519 노드락 서명 + 키-온리 플로팅**
