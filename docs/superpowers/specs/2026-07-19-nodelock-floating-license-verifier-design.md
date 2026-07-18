# 발급된 라이센스 검증 데스크톱 앱 (#112) — 설계

> 내부 테스트용 도구다. 고객 배포·코드사이닝·공증은 이번 스코프 밖.

## 목표

발급된 노드락 라이센스 파일(`NLicense.lic`)과 플로팅 라이센스(license_key)를 사내에서
빠르게 검증할 수 있는 크로스플랫폼(Windows/Mac) GUI 도구를 만든다. 노드락은 오프라인
서명 검증을 우선하고, 두 종류 모두 온라인 재확인을 지원한다.

## 범위

**포함**:
- `frontapi`에 무인증(public) 온라인 검증 엔드포인트 2개 신규 추가
- `tools/licverify/` — Go + Fyne 데스크톱 GUI 앱 (신규 Go 모듈)
- 오프라인 노드락 검증 로직(순수 Go, 외부 의존 없음) + 회귀 테스트
- 온라인 재확인 클라이언트(HTTP) + 플로팅 조회
- frontapi 신규 엔드포인트 테스트, `docs/nodelock-license-usage.md` 갱신

**제외(이번 스코프 밖)**:
- macOS 공증(notarization), Windows 코드사이닝(`sign.sh` 등 배포 인프라)
- Windows GUI 바이너리 실제 빌드/배포(사내 Windows 머신에서 네이티브 빌드 전제, 문서만 안내)
- 라이센스 발급·재발급·정지 등 관리 기능(이미 Admin에 존재)
- 플로팅 activation/analysis 생명주기(체크아웃·체크인) — 조회(effectiveness)만 다룸

## 설계

### 1. 백엔드 — `frontapi` 신규 무인증 엔드포인트

기존 `/api/v1/licenses/effectiveness`, `/api/v1/floating/effectiveness`는 AITessera 포털용
(JWT 필수)으로 그대로 두고, 데스크톱 앱 전용 무인증 경로를 신설한다. license_key(+host_id)
조합 자체가 사실상의 조회 키이고, 기존 `RateLimitMiddleware`(IP+path 기준, 전역 적용)가
무차별 대입을 억제하므로 별도 인증 없이 노출한다.

| 신규 라우트 | 요청 | 내부 위임 | 응답 |
|---|---|---|---|
| `POST /api/v1/nodelock/verify` | `{license_key, host_id}` | `NodeLockAuthService::effectiveness()` | `{valid, reason, status, expire_date}` |
| `POST /api/v1/floating/verify` | `{license_key}` (host_id 불필요) | `FloatingAuthService::effectiveness()` | `{valid, remaining, ...}` |

- 신규 컨트롤러: `NodeLockVerifyController`, `FloatingVerifyController` (기존 `Effectiveness`/
  `FloatingEffectiveness` 컨트롤러와 동일 검증·위임 패턴, 생성자 주입 서비스 재사용).
- `frontapi/src/Routes.php`에 두 라우트 추가.
- `JwtAuthMiddleware::PUBLIC_ROUTES`에 `['POST', '/api/v1/nodelock/verify']`,
  `['POST', '/api/v1/floating/verify']` 추가.
- OpenAPI 어트리뷰트: `security: []`(무인증 명시), 파라미터 검증 실패 시 기존과 동일하게
  `422 VALIDATION_ERROR`.
- `frontapi/tests/NodeLockApiTest.php`에 무인증 통과 케이스 추가(신규 경로, `auth=false`로도
  200), `FloatingApiTest.php`(있으면) 동일 패턴으로 신규 경로 테스트 추가.
- `docs/nodelock-license-usage.md` 5장을 "5.1 포털용(JWT)" / "5.2 데스크톱 도구용(무인증)"으로
  재구성.

### 2. 데스크톱 앱 — `tools/licverify/` (신규 Go 모듈, Go + Fyne)

```
tools/licverify/
  go.mod                    module aicura.com/licverify
  verify/
    envelope.go             봉투 파싱({v,alg,data,sig}) + Ed25519 검증(crypto/ed25519, stdlib)
    payload.go               페이로드 구조체(docs/nodelock-license-usage.md §2.1 필드 전체) + 스키마 검증
    hostid.go                host_id 산출 — tools/hostid와 동일 규칙(SHA-256 namespace, 8바이트, 대문자 4자 그룹)
    verify.go                VerifyFile(fileBytes, pubKey, localHostID, today) — §4 알고리즘 그대로
  client/
    nodelock_verify.go       POST /api/v1/nodelock/verify 호출(5초 타임아웃)
    floating_verify.go       POST /api/v1/floating/verify 호출
  ui/
    app.go                   Fyne 메인 윈도우 — 탭 2개(노드락 / 플로팅)
    nodelock_tab.go          파일 열기 다이얼로그 → 오프라인 검증 결과 표시 → "온라인 재확인" 버튼
    floating_tab.go          license_key 입력 → "조회" 버튼 → valid/remaining 표시
  main.go                    진입점(공개키 embed + app 기동)
  Makefile                   mac(네이티브 go build) / test(go test ./verify/... ./client/...)
  README.md                  실행법, 공개키 교체 방법, Windows는 Windows 머신에서 `go build` 안내
```

- `verify/`, `client/`는 GUI 의존성 없는 순수 로직 → macOS/Windows/Linux 무관하게 `go test`로
  전량 커버 가능(이번 세션에서 검증).
- `ui/`(Fyne)는 macOS 네이티브 빌드로만 이번 세션에서 동작 확인. Fyne은 CGO 기반이라
  Windows 크로스컴파일은 mingw-w64 툴체인이 필요(미설치 확인됨) — 내부 테스트용이므로
  Windows 실행은 사내 Windows 머신에서 `go build .`로 직접 빌드하는 것을 README에 안내한다.
- 공개키는 `main.go`에 base64 상수로 embed(개인키 없음, 노출돼도 안전). 실제 운영 공개키로
  교체하는 절차를 README에 명시.

### 3. 오프라인 노드락 검증 알고리즘 (`docs/nodelock-license-usage.md` §4 이식)

1. 봉투 JSON 파싱, `alg != "Ed25519"` → `INVALID_ALG`
2. `data`(base64 **문자열 그대로**, 디코드 전)에 대해 `ed25519.Verify(pubKey, []byte(data), sig)`
   — 실패 시 즉시 `TAMPERED` (페이로드 해석 이전에 거부)
3. 서명 통과 후에만 `data` base64 디코드 → 페이로드 JSON 파싱
4. `magic=="AILICET"`, `payload_version==1`, `license_type=="nodelock"` 확인
5. `system_id_check==true`면 로컬 계산 host_id와 `payload.host_id` 일치 확인 → 불일치 시 `HOST_MISMATCH`
6. `expire_date`(문자열 사전식 비교, null=무기한) → 지났으면 `EXPIRED`
7. 통과 시 `OK` + 페이로드 반환, GUI가 상품·모듈·만료일·지원종료일을 표시

각 실패 사유는 GUI에 한국어 메시지로 매핑해 보여준다(예: `HOST_MISMATCH` →
"이 라이센스는 다른 컴퓨터에 발급된 것입니다").

### 4. 플로팅 확인 흐름

파일이 없으므로 오프라인 검증 대상이 아니다. GUI에서 `license_key`를 입력받아 곧바로
`client/floating_verify.go`로 `POST /api/v1/floating/verify` 호출, 응답의 `valid`/`remaining`
(count·credit)을 표시한다. 네트워크 실패 시 명확한 오류 메시지("서버에 연결할 수
없습니다")를 표시하고 재시도 버튼을 둔다.

## 테스트 전략

- **frontapi**: `NodeLockApiTest`/신규 `FloatingApiTest`(또는 기존 파일 확장)에 무인증 경로
  통과 케이스 + 기존 reason 케이스 재검증. `composer ci`(frontapi: `composer check`) 그린 확인.
- **licverify**:
  - `verify/` 테이블 테스트 — 정상 서명 / 위조 서명(`TAMPERED`) / 스키마 불일치(`NOT_AILICET`,
    `UNSUPPORTED_VERSION`, `NOT_NODELOCK`) / host 불일치(`HOST_MISMATCH`) / 만료(`EXPIRED`) /
    통과(`OK`, 페이로드 필드 확인).
  - `hostid.go` 회귀 테스트 — 고정 벡터 `564D0102-0304-0506-0708-090A0B0C0D0E` →
    `7121-0B91-F5B6-AA1B` (tools/hostid와 동일 결과 보장).
  - `client/` — `httptest.NewServer`로 목업, 타임아웃·비정상 응답(500, 잘못된 JSON) 케이스 포함.
  - GUI(`ui/`)는 자동 테스트 대상에서 제외, 이번 세션에서 `go build` + 수동 실행으로
    골든 패스(정상 파일 열기 → 결과 표시, 플로팅 조회)를 확인한다.

## 함정 체크리스트

- 서명 검증은 반드시 `data`(base64 인코드된 **문자열의 바이트**)를 대상으로 한다 — 디코드한
  페이로드 바이트에 서명 검증을 걸면 항상 실패한다.
- host_id 산출 규칙(SHA-256 네임스페이스·8바이트·포맷)은 `tools/hostid`와 **1비트도** 달라지면
  안 된다 — 고정 벡터 테스트로 고정.
- 공개키만 embed, 개인키(secret key)는 어떤 경로로도 포함하지 않는다.
- 신규 무인증 엔드포인트는 기존 포털용 엔드포인트와 **별도 경로**로 추가한다(기존 라우트의
  인증 요구사항은 변경하지 않는다).
- Fyne은 CGO 필요 — mac에서 Windows GUI 크로스컴파일은 시도하지 않는다(README에 명시).
