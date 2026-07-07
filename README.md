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
                   ┌──────────▼──────────┐
                   │  AITessera (JWT HS256)│
                   └──────────────────────┘
```

- **회원 인증**은 [AITessera](https://github.com/pushwing/AITessera)(JWT) 에 위임 — AILicet 은 공유 시크릿으로 토큰을 검증만 한다.
- **노드락**(오프라인): Ed25519 서명 파일 배포 → 클라이언트가 공개키로 오프라인 검증.
- **플로팅**(온라인): 관리키만 배포 → frontApi 로 온라인 활성화·유효성·사용량 차감.

## 기술 스택

| 영역 | 채택 |
|------|------|
| 콘솔 | CodeIgniter 4 · PHP 8.4+ · FrankenPHP(권장)/CI4 내장 서버 |
| frontApi | pure PHP · PSR-7(`nyholm/psr7`) · `nikic/fast-route` · `relay/relay` · `php-di` |
| DB | MySQL 8 (콘솔=Query Builder / frontApi=PDO prepared) |
| 인증 | AITessera JWT(HS256) 위임 검증(`JwtLibrary`) |
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
- **#4 AITessera JWT 인증** — `JwtLibrary`(HS256, alg 고정), `JwtAuthFilter`/`AdminAuthFilter`, 역할 인가, 도메인 예외
- **#5 공통 레이아웃·UI** — `aicura.css` 디자인 시스템, 권한별 레이아웃, 로그인, 샘플 대시보드(AG Grid+Chart.js)

### P1 · 마스터관리
- **#6 상품·모듈 관리** — 상품/모듈 CRUD, 기간정책, 활성 상품 캐시(TTL 1h)

### P2 · 라이센스 코어
- **#7 노드락 생성 엔진** — Ed25519 서명(개인키 KMS/env), Strategy 패턴, 저장소 추상화(로컬/S3), 발급 이력
- **#8 플로팅 발급** — 키-온리, activate/check term, 활성화 캐시 토큰, 세그플러스(크레딧/카운트)
- **#9 상태 관리** — 정지/종료/보관/연장/재발급, 이전 키 폐기, 트랜잭션·이력

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

---

## 로컬 개발

### 콘솔 (CodeIgniter 4)

```bash
cp env .env          # 아래 필수 키 설정
composer install
php spark migrate
make serve            # FrankenPHP (포트 8300) — 또는 make serve-spark
```

`.env` 필수 키: `app.baseURL`, `database.default.*`, `JWT_SECRET`(AITessera 서명키와 동일),
`license.ed25519*`(`php spark license:keygen`).

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
2. AILicet `.env` 에 아래를 설정한다 — `JWT_SECRET` 은 **AITessera 서명키와 동일**해야 토큰 검증이 된다.
   ```env
   aitessera.baseURL = http://127.0.0.1:9300
   JWT_SECRET        = <AITessera 와 동일한 시크릿>
   ```
   > `aitessera.baseURL` 이 설정되면 데모 빠른 로그인은 비활성화되고 실제 AITessera 인증을 사용한다.
3. AITessera 운영자 계정으로 로그인하면 토큰이 세션에 저장되고 회원 계정 관리가 동작한다.

> ⚠️ 개발에서 AITessera 연동(서버-투-서버 호출)을 확인할 때는 **`make serve`(FrankenPHP)** 를 권장한다.
> `make serve-spark`(PHP 내장 서버)는 단일 스레드라 서버-투-서버 HTTP 호출이 불안정하다.

## 검증

```bash
composer check                   # 콘솔: PHPStan level 6 + PHPUnit
cd frontapi && composer check    # frontApi: PHPStan + PHPUnit
```

`dev`·`main` push/PR 마다 GitHub Actions 가 backend·frontapi 잡을 병렬 검증한다.

## Git 워크플로우

`feature/* → (Squash) → dev → (Merge commit) → main`
자세한 규칙은 [CLAUDE.md](CLAUDE.md) 참고.
