# 노드락 라이센스 사용 절차 (클라이언트 검증 가이드)

이 문서는 클라이언트 프로그램이 발급받은 노드락 라이센스 파일(`NLicense.lic`)을
**읽어서 진위·유효성을 검증하는 방법**을 설명한다. 언어 무관하게 의사코드로 기술하며,
각 언어별 암호 라이브러리 매핑은 마지막 장을 참고한다.

- 관련 이슈: #59
- 서버측 서명 구현: [`app/Libraries/LicenseSigner.php`](../app/Libraries/LicenseSigner.php)
- 참조 검증 구현(PHP): [`frontapi/src/Support/LicenseVerifier.php`](../frontapi/src/Support/LicenseVerifier.php)

---

## 1. 개요

노드락(Node-Lock)은 **오프라인 설치형** 라이센스다. 특정 머신의 고유 식별자(`host_id`)에
바인딩된 서명 파일을 배포하며, 클라이언트는 인터넷 없이도 서명만으로 위조 여부와
사용 조건을 판정할 수 있다.

```
[발급 서버]                                   [클라이언트 머신]
  개인키(Ed25519)로 서명                          내장 공개키(Ed25519)로 검증
        │                                              ▲
        │  NLicense.lic 배포 (파일/다운로드)            │
        └──────────────────────────────────────────────┘
```

- **서명**: 서버가 **개인키**로 페이로드에 서명한다. 개인키는 `.env`/KMS 에만 존재하며 배포물에 포함되지 않는다.
- **검증**: 클라이언트는 프로그램에 내장된 **공개키**로만 검증한다. 개인키가 없으므로 위조가 불가능하다.
- 만료·상태·키 폐기 같은 **실시간 정보**가 필요하면 온라인 검증(4장)을 추가로 수행한다.

---

## 2. 라이센스 파일 포맷

`NLicense.lic` 는 UTF-8 JSON **봉투(envelope)** 다.

```json
{
  "v": 1,
  "alg": "Ed25519",
  "data": "<base64(payload JSON)>",
  "sig":  "<base64(detached signature)>"
}
```

| 필드   | 설명                                                                 |
|--------|----------------------------------------------------------------------|
| `v`    | 봉투 포맷 버전 (현재 `1`)                                             |
| `alg`  | 서명 알고리즘. 반드시 `"Ed25519"` 여야 한다.                         |
| `data` | **페이로드 JSON을 base64 인코딩한 문자열**. 서명 대상이 바로 이 문자열이다. |
| `sig`  | `data` 문자열에 대한 Ed25519 detached 서명(base64).                  |

> **핵심**: 서명 대상은 `data`(base64 문자열) **그 자체**다. 페이로드 JSON을 다시 직렬화하지
> 않으므로 공백·키 순서 같은 정규화 문제에서 자유롭다. 검증 시 `data` 문자열을 **그대로**
> 서명 검증에 넣고, 통과한 뒤에야 base64 디코드하여 페이로드를 해석한다.

### 2.1 페이로드 필드

`data` 를 base64 디코드하면 다음 구조의 JSON 페이로드가 나온다
(빌드 전략: [`StandardNodeLockStrategy`](../app/Licensing/Strategy/StandardNodeLockStrategy.php)).

| 필드               | 타입          | 설명                                                        |
|--------------------|---------------|-------------------------------------------------------------|
| `magic`            | string        | 고정 문자열 `"AILICET"`. 파일 종류 식별용.                  |
| `payload_version`  | int           | 페이로드 스키마 버전 (현재 `1`).                            |
| `license_type`     | string        | `"nodelock"` 고정.                                          |
| `product_code`     | string        | 상품 코드.                                                  |
| `product_name`     | string        | 상품명.                                                     |
| `product_family`   | string\|null  | 상품군(선택).                                               |
| `host_id`          | string        | **바인딩된 머신 고유 식별자**. 로컬 머신 값과 일치해야 함.  |
| `system_id_check`  | bool          | `host_id` 검사 수행 여부(노드락은 항상 `true`).            |
| `version`          | string\|null  | 허용 제품 버전.                                             |
| `period_code`      | string        | 기간 정책 코드.                                             |
| `expire_date`      | string\|null  | 만료일 `YYYY-MM-DD`. `null`이면 무기한.                     |
| `support_end_date` | string\|null  | 기술지원 종료일 `YYYY-MM-DD`.                               |
| `modules`          | string[]      | 활성화된 모듈 코드 목록.                                    |
| `limits`           | object        | 사용량 제한. 예: `{ "count": 100, "credit": 5000 }`.       |
| `is_trial`         | bool          | 체험판 여부.                                                |
| `license_sn`       | string        | 자재(발급) 시리얼.                                          |
| `license_key`      | string        | 관리키. 온라인 검증(4장)에서 사용.                          |
| `issue_date`       | string        | 발급일 `YYYY-MM-DD`.                                        |
| `company_name`     | string\|null  | 고객사명.                                                   |
| `charge_name` / `charge_phone` / `charge_email` | string\|null | 담당자 정보. |

---

## 3. 공개키 준비

검증에는 base64 인코딩된 **32바이트 Ed25519 공개키**가 필요하다.

- 서버 발급 키페어는 `php spark license:keygen` 으로 생성한다
  ([`app/Commands/LicenseKeygen.php`](../app/Commands/LicenseKeygen.php)).
- 이때 출력되는 `license.ed25519PublicKey` 값(= frontApi 의 `LICENSE_ED25519_PUBLIC_KEY`)이 공개키다.
- **이 공개키만 클라이언트에 배포·내장한다. 개인키(`...SecretKey`)는 절대 포함하지 않는다.**
- 공개키는 노출되어도 안전하다(서명 생성 불가, 검증만 가능). 다만 **키 로테이션**을 대비해
  프로그램이 여러 공개키를 순차 시도할 수 있도록 설계하면 좋다.

---

## 4. 오프라인 검증 절차 (필수)

인터넷 연결 없이 파일만으로 수행하는 검증이다. **아래 순서를 반드시 지킨다.**

```
function verifyLicenseFile(fileText, publicKeyBytes, localHostId, today):

    # 1) 봉투 파싱
    env = jsonParse(fileText)
    if env is null: return INVALID_FORMAT
    if env.alg != "Ed25519": return INVALID_ALG

    # 2) 서명 검증 — data 문자열을 "그대로" 검증 대상으로 사용
    data = env.data                      # base64 문자열, 디코드 전
    sig  = base64Decode(env.sig)
    if data is not string or sig is null: return INVALID_FORMAT
    if not ed25519_verify_detached(sig, data, publicKeyBytes):
        return TAMPERED                  # 위조·손상 → 즉시 거부

    # 3) 서명 통과 후에야 페이로드 해석
    payload = jsonParse(base64Decode(data))
    if payload is null: return INVALID_FORMAT

    # 4) 파일 종류·스키마 확인
    if payload.magic != "AILICET":        return NOT_AILICET
    if payload.payload_version != 1:      return UNSUPPORTED_VERSION
    if payload.license_type != "nodelock": return NOT_NODELOCK

    # 5) 노드락 머신 바인딩 검사
    if payload.system_id_check == true and payload.host_id != localHostId:
        return HOST_MISMATCH

    # 6) 만료 검사 (null = 무기한)
    if payload.expire_date != null and payload.expire_date < today:
        return EXPIRED

    # 통과 — payload 로 모듈·제한·지원종료일 등을 적용
    return OK, payload
```

### 4.1 단계별 주의

1. **서명 먼저, 해석은 나중** — 서명 검증(2단계)을 통과하기 전에는 페이로드 내용을
   신뢰하지 않는다. 위 순서를 뒤집지 말 것.
2. **`data` 는 디코드 전 문자열로 검증** — base64 디코드한 바이트가 아니라 `data` **문자열**을
   서명 검증에 넣는다(서버가 문자열에 서명했기 때문).
3. **`host_id` 산출** — `localHostId` 는 발급 시 사용한 것과 **완전히 동일한 규칙**으로 현재
   머신에서 계산해야 한다. 표준 산출 규칙은 `tools/hostid` 유틸리티가 정의한다(고객이 발급
   신청 시 이 도구로 호스트ID 를 뽑아 제출한다):
   - macOS `IOPlatformUUID` / Windows `MachineGuid` 를 대문자·trim 정규화 →
     `SHA-256("AILICET-NODELOCK-v1:" + 정규화값)` → 앞 8바이트 대문자 hex 를
     `XXXX-XXXX-XXXX-XXXX` 로 포맷.
   - 회귀 방지 고정 벡터: 입력 `564D0102-0304-0506-0708-090A0B0C0D0E` → `7121-0B91-F5B6-AA1B`.
   - 런타임 검증기는 반드시 이 규칙을 그대로 재현해야 한다. 다른 방식(MAC·디스크 시리얼 등)을
     쓰면 발급값과 달라져 모든 라이센스가 `HOST_MISMATCH` 로 실패한다. 상세: `tools/hostid/README.md`.
4. **날짜 비교** — 서버는 UTC 기준(`YYYY-MM-DD`)으로 만료를 기록한다. 문자열 사전식 비교로
   충분하다(`"2026-07-08" < "2026-07-09"`).
5. **거부 사유 로깅** — 어떤 단계에서 실패했는지 로그로 남기면 지원 대응이 쉽다.

---

## 5. 온라인 검증 절차 (권장)

오프라인 검증은 파일 발급 **시점**의 스냅샷만 본다. 발급 후 서버에서 **정지·해지되거나
재발급으로 키가 폐기**된 경우는 온라인 검증으로만 잡을 수 있다. frontApi(`AITessera`)가
관련 엔드포인트를 제공한다.

- 인증: 모든 엔드포인트는 `Authorization: Bearer <JWT>` 필요.
- 경로 정의: [`frontapi/src/Routes.php`](../frontapi/src/Routes.php)

> 아래 엔드포인트는 **AITessera 포털 세션(JWT)** 을 가진 호출자용이다. 로그인 세션이
> 없는 독립 데스크톱 도구(`tools/licverify`, 내부 테스트용)는 **무인증** 버전인
> `POST /api/v1/nodelock/verify`(§5.4), `POST /api/v1/floating/verify`(§5.4)를 사용한다.

### 5.1 유효성 검증 — `POST /api/v1/licenses/effectiveness`

노드락의 **실시간 유효성**을 판정한다(상태·만료·호스트 바인딩·키 폐기 여부).

요청:
```json
{ "license_key": "<payload.license_key>", "host_id": "<로컬 host_id>" }
```

응답:
```json
{ "status": "success",
  "data": { "valid": true, "reason": "OK", "status": "active", "expire_date": "2026-12-31" } }
```

`valid=false` 일 때 `reason` 값과 의미
([`NodeLockAuthService::effectiveness`](../frontapi/src/Service/NodeLockAuthService.php)):

| reason              | 의미                                             |
|---------------------|--------------------------------------------------|
| `INVALID_LICENSE_KEY` | 존재하지 않는 관리키                           |
| `REVOKED_KEY`       | 재발급으로 폐기된 이전 키 (최신 키만 유효)       |
| `NOT_NODELOCK`      | 노드락 라이센스가 아님                           |
| `INACTIVE`          | 상태가 `active` 아님(정지/해지/보관)             |
| `EXPIRED`           | 서버 기준 만료됨                                 |
| `HOST_MISMATCH`     | 등록된 `host_id` 와 불일치                       |

> 라이센스 상태값: `active` / `suspended` / `terminated` / `archived`
> ([`LicenseStatus`](../app/Enums/LicenseStatus.php)).

### 5.2 상세 조회 — `POST /api/v1/licenses/info`

화면 표시용 상세(민감정보 제외: 상품·버전·기간·상태·만료·모듈)를 반환한다.

요청: `{ "license_key": "<payload.license_key>" }`
없으면 `404 NOT_FOUND`.

### 5.3 사용정보 수집 — `POST /api/v1/licenses/bypass`

검증과 별개로, 사용 로그를 서버 원시 로그로 적재하는 텔레메트리 엔드포인트(선택).

### 5.4 데스크톱 도구용 무인증 확인 (내부 테스트용)

`tools/licverify`(내부 테스트용 데스크톱 앱)처럼 로그인 세션이 없는 독립 클라이언트를
위한 경로다. **인증 헤더가 필요 없다** — license_key(+host_id)가 사실상의 조회 키이며,
`RateLimitMiddleware`(IP+path 기준)가 무차별 대입을 억제한다.

| 라우트 | 요청 | 응답 | 대응하는 포털용 엔드포인트 |
|---|---|---|---|
| `POST /api/v1/nodelock/verify` | `{license_key, host_id}` | §5.1과 동일 | `/api/v1/licenses/effectiveness` |
| `POST /api/v1/floating/verify` | `{license_key}` | 플로팅 valid/remaining | `/api/v1/floating/effectiveness` |

> 이 경로는 배포용 고객 도구가 아니라 **내부 테스트 목적**으로 추가되었다. 실제 고객
> 배포 시에는 별도 인증·서명·배포 파이프라인 검토가 필요하다.

---

## 6. 프로그램 기동 시 권장 시퀀스

```
프로그램 시작
   │
   ├─ 1. NLicense.lic 로드
   ├─ 2. 오프라인 검증(4장)  ── 실패 ─▶ 실행 차단 + 사유 안내
   │        │ OK
   ├─ 3. (온라인 가능 시) effectiveness 호출(5.1)
   │        ├─ valid=true ─▶ 정상 구동, 결과 캐시(예: 24h)
   │        ├─ valid=false ─▶ 사유별 처리(만료 안내/재발급 유도/차단)
   │        └─ 네트워크 실패 ─▶ 오프라인 결과로 유예(grace) 구동
   └─ 4. payload.modules / limits 적용 → 기능 게이팅
```

- **오프라인 우선, 온라인 보강**: 네트워크가 없어도 오프라인 검증이 통과하면 유예 기간 동안
  구동을 허용하고, 온라인 성공 시 최신 상태로 갱신한다.
- **캐시**: 온라인 결과를 짧게 캐시해 매 기동 호출 부하를 줄인다.

---

## 7. 언어별 Ed25519 라이브러리 매핑

핵심 연산은 **Ed25519 detached 서명 검증** + **base64 디코드** + **JSON 파싱** 세 가지뿐이다.

| 언어      | 라이브러리                     | 검증 함수(예)                                   |
|-----------|--------------------------------|-------------------------------------------------|
| C / C++   | libsodium                      | `crypto_sign_verify_detached(sig, data, len, pk)` |
| PHP       | ext-sodium (내장)              | `sodium_crypto_sign_verify_detached($sig, $data, $pk)` |
| Java      | BouncyCastle / `java.security` | `Ed25519Signer.verifySignature(...)`            |
| C#/.NET   | NSec / libsodium-net           | `SignatureAlgorithm.Ed25519.Verify(pk, data, sig)` |
| Go        | `crypto/ed25519`               | `ed25519.Verify(pub, data, sig)`                |
| Python    | PyNaCl                         | `VerifyKey(pk).verify(data, sig)`               |
| Rust      | `ed25519-dalek`                | `verifying_key.verify(data, &sig)`              |

> **libsodium 계열이 서버 구현(`sodium_crypto_sign_*`)과 100% 호환**되므로 신규 클라이언트는
> libsodium 사용을 권장한다. 서명 대상 `data` 는 **base64 문자열의 바이트열 그대로**임에 유의한다.

---

## 8. 참고 구현

- 서버 서명·검증 원본: [`app/Libraries/LicenseSigner.php`](../app/Libraries/LicenseSigner.php)
- 검증 참조(PHP, 그대로 이식 가능): [`frontapi/src/Support/LicenseVerifier.php`](../frontapi/src/Support/LicenseVerifier.php)
- 페이로드 구성: [`app/Licensing/Strategy/StandardNodeLockStrategy.php`](../app/Licensing/Strategy/StandardNodeLockStrategy.php)
- 온라인 유효성 로직: [`frontapi/src/Service/NodeLockAuthService.php`](../frontapi/src/Service/NodeLockAuthService.php)
