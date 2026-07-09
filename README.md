# AILicet

> **Permission, verified.** — 온·오프라인을 아우르는 라이선스 인증·관리 서비스
>
> 이름은 "허가되다"라는 뜻의 라틴어 *licet* 에서 따왔다.

AILicet 은 성형·토탈 광고 솔루션(AIvance 제품군)을 위한 **라이선스 발급·인증·관리 시스템**이다.
운영자·대행사·고객이 사용하는 서버렌더링 콘솔(CodeIgniter 4)과, 클라이언트 프로그램이 호출하는
인증 API(프레임워크 없는 pure PHP)로 구성된다.

---

## 아키텍처

```
┌────────────────────────────┐        ┌──────────────────────────┐
│  AILicet 콘솔 (CodeIgniter4) │        │  frontApi (pure PHP)      │
│  운영자 / 대행사 / 고객       │        │  클라이언트 프로그램용 인증 │
│  - 회원·상품·라이센스 관리    │        │  - 노드락/플로팅 검증      │
│  - 발급 엔진(Ed25519)        │        │  - PSR-7/15 + fast-route   │
│  - CRON 배치·알림·로그 소비   │        │  - PDO prepared statement  │
└──────────────┬─────────────┘        └───────────┬──────────────┘
               │        공유 MySQL / Redis          │
               └──────────────┬────────────────────┘
                              │  회원 인증 위임
                   ┌───────────────────────────┐
                   │  AITessera (JWT RS256/HS256)│
                   └───────────────────────────┘
```

- **회원 인증**은 [AITessera](https://github.com/pushwing/AITessera)(JWT) 에 위임 — AILicet 은 토큰을 **검증만** 한다. 검증은 비대칭키(RS256) 공개키 방식이며, 무중단 전환을 위해 대칭키(HS256)도 과도기 동안 병행 허용한다([이슈 #55](https://github.com/pushwing/AILicet/issues/55)).
- **노드락**(오프라인): Ed25519 서명 파일 배포 → 클라이언트가 공개키로 오프라인 검증.
- **플로팅**(온라인): 관리키만 배포 → frontApi 로 온라인 활성화·유효성·사용량 차감.

## 기술 스택

| 영역 | 채택 |
|------|------|
| 콘솔 | CodeIgniter 4 · PHP 8.4+ · FrankenPHP(권장)/CI4 내장 서버 |
| frontApi | pure PHP · PSR-7(`nyholm/psr7`) · `nikic/fast-route` · `relay/relay` · `php-di` |
| DB | MySQL 8 (콘솔=Query Builder / frontApi=PDO prepared) |
| 인증 | AITessera JWT 위임 검증 — RS256 공개키(전환기 HS256 병행), `JwtVerifier` |
| 라이센스 서명 | Ed25519(libsodium) |
| 캐시·큐 | Redis(`predis`) |
| 배치 | `codeigniter4/tasks` 스케줄러 + Spark Command |
| UI | 자체 디자인 시스템(`aicura.css`) · AG Grid · Chart.js |
| 품질 | PHPStan level 6 · PHPUnit · GitHub Actions CI |

---

## 구현 범위 (이슈 #2–#18, #33)

### P0 · 기반
- **#2 스캐폴딩 & CI** — CI4 프로젝트, `make serve`, composer 스크립트(analyse/test/check), GitHub Actions
- **#3 DB 스키마** — customers/products/product_modules/licenses/license_history/customer_license/audit_logs 마이그레이션, 도메인 Backed Enum, 타입별 컬럼 → JSON config 정규화
- **#4 AITessera JWT 인증** — `JwtAuthFilter`/`AdminAuthFilter`, 역할 인가, 도메인 예외
- **#55 JWT 비대칭키(RS256) 전환** — AITessera 토큰 검증을 `JwtVerifier`(RS256 공개키, alg 서버 강제로 혼동 공격 차단)로 전환. 무중단 전환 위해 `JWT_VERIFY_ALGOS` 로 HS256+RS256 병행 후 RS256 단독으로 축소. 자체 발급 토큰은 `JwtLibrary`(HS256) 로 분리
- **#5 공통 레이아웃·UI** — `aicura.css` 디자인 시스템, 권한별 레이아웃, 로그인, 샘플 대시보드(AG Grid+Chart.js)

### P1 · 마스터관리
- **#6 상품·모듈 관리** — 상품/모듈 CRUD, 기간정책, 활성 상품 캐시(TTL 1h)

### P2 · 라이센스 코어
- **#7 노드락 생성 엔진** — Ed25519 서명(개인키 KMS/env), Strategy 패턴, 저장소 추상화(로컬/S3), 발급 이력
- **#8 플로팅 발급** — 키-온리, activate/check term, 활성화 캐시 토큰, 세그플러스(크레딧/카운트)
- **#9 상태 관리** — 정지/종료/보관/연장/재발급, 이전 키 폐기, 트랜잭션·이력
- **#74 노드락 호스트ID 유틸리티** — 고객 PC(윈도우/맥)의 머신 고유 UUID(`IOPlatformUUID`/`MachineGuid`)를 SHA-256 해시해 고정 포맷(`XXXX-XXXX-XXXX-XXXX`)의 호스트ID 를 산출·클립보드 복사하는 Go CLI(`tools/hostid`). 발급·재발급 입력 경계에서 형식 검증(`NodeLockIssueRequest::normalizeHostId()`). 산출 규칙은 라이센스 런타임 검증(`system_id_check`)과 맞물려 **불변**

### P3 · Admin (운영자)
- **#10 회원관리** — 대행사/고객 CRUD, 검색·페이징(meta 표준)
- **#11 라이센스 관리 UI** — 노드락/플로팅 발급, 목록·상세·상태변경, 서명 파일 다운로드
- **#33 회원 계정 관리(AITessera 연동)** — 운영자 토큰으로 회원 목록/상세/수정 + 운영자/대행사/일반회원 계정 생성

### P4 · 대행사 / P5 · 고객
- **#12 대행사 페이지** — 소유권 스코프(자기 하위 고객·라이센스만), 발급/조회
- **#13 고객 페이지 + 고객센터** — 자가가입(이메일 인증), 내 라이센스, 문의

### P6 · frontApi (pure PHP)
- **#14 기반 구조** — 미들웨어 파이프라인(ErrorHandler→RateLimit→JwtAuth→Routing), Rate Limit, Swagger
- **#15 노드락 인증 API** — getLicenseInfo / effectiveness(호스트·폐기키 검증) / bypass(사용 로그), 공개키 검증 정합
- **#16 플로팅 인증 API** — activation(이중활성화 차단·캐시토큰) / effectiveness(잔여) / analysis start·end(차감·멱등)

### P7 · 운영자동화
- **#17 CRON 배치·알림** — 만료 알림/자동 종료/부정사용 감지 → 슬랙(dead-letter), 스케줄러
- **#18 로그 파이프라인** — frontApi `POST /logs` → Redis 큐 → CI4 소비자(원시파일+DB), dead-letter

### P8 · AI 업무 효율화 (#66)
- **공용 AI 클라이언트(멀티 제공자)** — `AiClient` 인터페이스 + `AnthropicAiClient`(Claude Messages API) + `GroqAiClient`(OpenAI 호환) + `NullAiClient`(no-op). `Services::aiClient()`가 `AI_PROVIDER`(anthropic|groq)로 제공자를 선택하고, 해당 키 미설정 시 no-op → 키 없이도 파이프라인·테스트가 안전하게 동작. 서비스는 구체 모델명 대신 `AiModelTier`(Cheap/Reasoning)를 넘기고 각 클라이언트가 제공자별 모델로 매핑. 프롬프트·파싱은 Service 책임 → 후속 서브이슈가 재사용
- **수집 로그 자동 분류·요약**(#66 1순위) — `logs:consume`이 저장한 로그를 별도 배치 `ai:classify-logs`(5분 주기)가 미분류분만 골라 저비용 등급으로 카테고리(`LogCategory` 화이트리스트)·요약을 채워 넣는다. `ai_processed_at` 마커로 재분류 방지 겸 개별 실패 자동 재시도
- **부정사용 이상 탐지 보강**(#80) — 규칙 기반 `AbuseDetectionService`가 못 잡는 행동 패턴 이상을, `AiAbuseDetectionService`가 사용 로그를 라이선스별 일일 집계(고유 호스트·IP 수, 사용 빈도, 시간대)해 추론 등급으로 판단한다. 이상 건은 `audit_logs`에 `ai_anomaly`로 기록되고 `license:daily` 배치에서 운영팀 Slack **초안**으로 통보 — 실제 정지는 운영자가 기존 화면에서 **사람 확정**(human-in-the-loop). 같은 날 재실행 중복은 `existsSince`로 방지
- AI 실제 호출 검증은 후속(현재 인터페이스·파이프라인·스텁 완성, 미설정 시 no-op)

---

## 로컬 개발

### 콘솔 (CodeIgniter 4)

```bash
cp env .env          # 아래 필수 키 설정
composer install
php spark migrate
make serve            # FrankenPHP (포트 8300) — 또는 make serve-spark
```

`.env` 필수 키: `app.baseURL`, `database.default.*`, JWT 검증 키(아래 참고),
`license.ed25519*`(`php spark license:keygen`).

**JWT 검증 키** — AITessera 토큰 검증 방식은 `JWT_VERIFY_ALGOS`(기본 `HS256,RS256`)로 제어한다.
- `JWT_PUBLIC_KEY_PATH` — RS256 검증용 AITessera **공개키(PEM)** 경로. AITessera 의 `jwt:keygen` 으로 생성한 공개키만 배치한다(개인키 금지).
- `JWT_SECRET` — HS256 검증용 공유 시크릿(전환기 한정, AITessera 서명키와 동일). RS256 단독 전환 후 `JWT_VERIFY_ALGOS=RS256` 으로 좁히면 불필요.
- `LICENSE_TOKEN_SECRET` — 자체 발급 토큰(플로팅 활성화 등)용 HS256 시크릿. 미설정 시 `JWT_SECRET` 폴백.

**AI 연동(선택)** — 로그 분류·요약, 부정사용 이상 탐지 등 AI 기능용. 미설정 시 안전하게 비활성(no-op).
- `AI_PROVIDER` — `anthropic`(기본) 또는 `groq`. 제공자를 하나 선택한다.
- `ANTHROPIC_API_KEY` / `ai.baseURL` — Claude 사용 시. baseURL 기본 `https://api.anthropic.com`.
- `GROQ_API_KEY` / `groq.baseURL` — Groq 사용 시. baseURL 기본 `https://api.groq.com`.
  ```bash
  php spark ai:classify-logs --limit 100   # 로그 분류(스케줄러는 5분 주기 자동)
  php spark license:daily                   # 일일 배치 — 부정사용 이상 탐지 포함
  ```

### frontApi (pure PHP)

```bash
cd frontapi
cp env.example .env
composer install
php -S localhost:8400 -t public   # http://localhost:8400/api/docs
```

### 개발용 데모 로그인

`CI_ENVIRONMENT=development` + AITessera 미설정 시, 로그인 화면(`/admin/login`)에서
**운영자 / 대행사 / 일반회원** 역할로 바로 로그인할 수 있다(비밀번호 아무거나).
대행사·고객 화면의 데이터를 보려면 데모 데이터를 시드한다:

```bash
php spark db:seed DemoSeeder     # 대행사(user_id=2)·고객(user_id=3) + 샘플 라이센스
```

| 역할 | 로그인 이메일 | 접근 영역 |
|------|--------------|-----------|
| 운영자 | `operator@demo.test` | `/admin/*` 전체 |
| 대행사 | `agency@demo.test` | `/agency/*` (자기 하위 고객·라이센스) |
| 일반회원 | `client@demo.test` | `/client/*` (내 라이센스·고객센터) |

> 로그인 화면의 빠른 로그인 버튼으로도 각 역할에 즉시 접속할 수 있다.
> 운영 환경은 AITessera 인증으로 로그인하며, 데모 로그인은 개발 환경 전용이다.

### AITessera 실제 로그인 (회원 계정 관리)

운영자 화면의 **회원 계정 관리**(`/admin/accounts`, AITessera 회원 API 연동)는 실제 AITessera
로그인으로 발급된 토큰이 필요하다. 데모 로그인은 토큰이 없어 이 화면은 안내만 표시된다.

1. [AITessera](https://github.com/pushwing/AITessera) 를 실행한다(예: `php -S localhost:9300 -t public`, 운영자 계정 시드).
2. AILicet `.env` 에 아래를 설정한다 — AITessera 의 서명 방식(`JWT_ALGO`)에 맞춰 검증 키를 준비한다.
   ```env
   aitessera.baseURL = http://127.0.0.1:9300

   # RS256 (권장) — AITessera 의 jwt:keygen 공개키(PEM)만 배치
   JWT_VERIFY_ALGOS    = HS256,RS256          # 전환기: 둘 다 허용 → 완료 후 RS256
   JWT_PUBLIC_KEY_PATH = writable/keys/aitessera_public.pem

   # HS256 (전환기·레거시) — AITessera 서명키와 동일한 값
   JWT_SECRET          = <AITessera 와 동일한 시크릿>
   ```
   > `aitessera.baseURL` 이 설정되면 데모 빠른 로그인은 비활성화되고 실제 AITessera 인증을 사용한다.
   > 무중단 전환 절차: AILicet 을 `HS256,RS256` 병행으로 배포 → AITessera `JWT_ALGO=RS256` 전환 → 기존 HS256 토큰 만료 → AILicet `JWT_VERIFY_ALGOS=RS256` 으로 축소.
3. AITessera 운영자 계정으로 로그인하면 토큰이 세션에 저장되고 회원 계정 관리가 동작한다.

### 호스트ID 유틸리티 (tools/hostid)

노드락 라이센스 발급용 호스트ID 산출 프로그램(윈도우/맥 범용). 고객이 실행하면 머신
고유값으로부터 고정 포맷 호스트ID 를 만들어 화면에 표시하고 클립보드에 복사한다.
Go 1.22+ 필요하며 순수 stdlib 라 외부 의존성·인터넷 없이 빌드된다.

```bash
cd tools/hostid
make            # dist/ 에 mac(universal) + windows(amd64) 실행파일 생성
make test       # 산출 규칙 회귀 테스트
make sign       # 배포용 코드사이닝(자격증명 env 주입 — sign.sh 헤더 참고)
```

- 산출 규칙: `SHA-256("AILICET-NODELOCK-v1:" + 정규화된 UUID)` → 앞 8바이트 → `XXXX-XXXX-XXXX-XXXX`
- **불변 규칙** — 재부팅·OS업데이트·재실행에 동일값. 라이센스 런타임 검증기는 반드시 동일
  규칙으로 재현해야 한다(불일치 시 전체 `HOST_MISMATCH`). 상세: `tools/hostid/README.md`
- 미서명 배포 시 Gatekeeper·SmartScreen 이 차단하므로 배포 전 `make sign` 권장

## 검증

```bash
composer check                   # 콘솔: PHPStan level 6 + PHPUnit
cd frontapi && composer check    # frontApi: PHPStan + PHPUnit
cd tools/hostid && make test     # 호스트ID 유틸리티: Go 단위 테스트
```

`dev`·`main` push/PR 마다 GitHub Actions 가 backend·frontapi 잡을 병렬 검증한다.

## Git 워크플로우

`feature/* → (Squash) → dev → (Merge commit) → main`
자세한 규칙은 [CLAUDE.md](CLAUDE.md) 참고.
