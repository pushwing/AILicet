# 라이센스 검증 데스크톱 앱 (#112) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 노드락 라이센스 파일 오프라인 검증 + 노드락/플로팅 온라인 무인증 확인을 지원하는 내부 테스트용 크로스플랫폼(Win/Mac) 데스크톱 GUI 도구(`tools/licverify`)와, 그 도구가 호출하는 `frontapi`의 신규 무인증 엔드포인트 2개를 구현한다.

**Architecture:** `frontapi`(순수 PHP, PSR-15)에 기존 `NodeLockAuthService`/`FloatingAuthService`를 재사용하는 무인증 컨트롤러 2개를 추가한다. 데스크톱 앱은 신규 Go 모듈(`tools/licverify`)로, GUI 의존성 없는 순수 로직(`verify/`, `client/` 패키지)과 Fyne 기반 GUI(`ui/`)를 분리해 로직만 완전히 테스트한다.

**Tech Stack:** PHP 8.4+ / PSR-15 (frontapi, 기존 스택), Go 1.22+ (`crypto/ed25519` stdlib, `fyne.io/fyne/v2` GUI 툴킷)

## Global Constraints

- 스펙 문서: `docs/superpowers/specs/2026-07-19-nodelock-floating-license-verifier-design.md` (모든 세부 규칙의 원본)
- frontapi PHP: `declare(strict_types=1)`, PSR-12, 생성자 주입, 기존 `EffectivenessController`/`FloatingEffectivenessController`와 동일한 패턴 재사용(서비스 로직 변경 금지, 컨트롤러만 신설)
- 신규 무인증 엔드포인트는 **기존 포털용 엔드포인트와 별도 경로**로 추가한다 — 기존 라우트의 인증 요구사항은 절대 변경하지 않는다
- Go: 외부 의존성 최소화(`verify`/`client` 패키지는 stdlib만), `tools/hostid`와 **1비트도 다르지 않은** host_id 산출 규칙(고정 벡터 `564D0102-0304-0506-0708-090A0B0C0D0E` → `7121-0B91-F5B6-AA1B`)
- 서명 검증 대상은 `data` 필드의 **base64 문자열 그 자체**(디코드 전) — 디코드된 바이트에 서명 검증을 걸면 안 됨
- 공개키만 embed, 개인키는 어떤 경로로도 포함하지 않는다
- 이번 스코프는 **내부 테스트용** — 코드사이닝·공증·Windows GUI 크로스컴파일 자동화는 제외
- 커밋 메시지: 이모지 + Conventional Commits 접두어 + 한국어 (예: `✨ feat: ...`)

---

## Task 1: frontapi — 노드락 무인증 검증 엔드포인트

**Files:**
- Create: `frontapi/src/Controller/NodeLockVerifyController.php`
- Modify: `frontapi/src/Routes.php`
- Modify: `frontapi/src/Middleware/JwtAuthMiddleware.php`
- Test: `frontapi/tests/NodeLockApiTest.php`

**Interfaces:**
- Consumes: 기존 `App\Service\NodeLockAuthService::effectiveness(string $licenseKey, string $hostId): array{valid:bool, reason:string, status:?string, expire_date:?string}` (변경 없음)
- Produces: `POST /api/v1/nodelock/verify` — 무인증, 요청 `{license_key, host_id}`, 응답은 `/api/v1/licenses/effectiveness`와 동일 포맷

- [ ] **Step 1: 실패하는 테스트 작성**

`frontapi/tests/NodeLockApiTest.php`의 `testValidationErrorOnMissingParams()` 메서드 뒤(클래스 닫는 `}` 앞)에 추가:

```php
    public function testPublicVerifyEndpointWorksWithoutAuth(): void
    {
        $res  = $this->post('/api/v1/nodelock/verify', ['license_key' => 'KEY-A1', 'host_id' => 'HOST-A'], false);
        $body = $this->data($res);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($body['data']['valid']);
        $this->assertSame('OK', $body['data']['reason']);
    }

    public function testPublicVerifyEndpointHostMismatchWithoutAuth(): void
    {
        $body = $this->data($this->post('/api/v1/nodelock/verify', ['license_key' => 'KEY-A1', 'host_id' => 'WRONG'], false));

        $this->assertFalse($body['data']['valid']);
        $this->assertSame('HOST_MISMATCH', $body['data']['reason']);
    }
```

(기존 `post(string $path, array $body, bool $auth = true)` 헬퍼가 이미 무인증 호출을 지원하므로 테스트 헬퍼 수정은 불필요.)

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd frontapi && vendor/bin/phpunit --filter testPublicVerifyEndpointWorksWithoutAuth`
Expected: FAIL — 라우트가 없어 404(`NOT_FOUND`) 또는 라우팅 예외

- [ ] **Step 3: 컨트롤러 작성**

Create `frontapi/src/Controller/NodeLockVerifyController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Input;
use App\Http\Json;
use App\Service\NodeLockAuthService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 노드락 — 데스크톱 검증 도구(tools/licverify)용 무인증 유효성 확인.
 *
 * /api/v1/licenses/effectiveness(AITessera 포털용, JWT 필수)와 동일 로직이지만
 * 로그인 세션이 없는 독립 클라이언트가 호출할 수 있도록 인증 없이 노출한다.
 * license_key+host_id 조합이 사실상의 조회 키이며, RateLimitMiddleware(IP+path
 * 기준, 전역 적용)가 무차별 대입을 억제한다.
 */
final class NodeLockVerifyController
{
    public function __construct(
        private readonly Json $json,
        private readonly NodeLockAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/nodelock/verify', summary: '노드락 유효성 확인(무인증, 데스크톱 도구용)', tags: ['License'], responses: [
        new OA\Response(response: 200, description: '유효성 결과(valid/reason)'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body       = Input::json($request);
        $licenseKey = trim((string) ($body['license_key'] ?? ''));
        $hostId     = trim((string) ($body['host_id'] ?? ''));
        if ($licenseKey === '' || $hostId === '') {
            return $this->json->error('VALIDATION_ERROR', 'license_key 와 host_id 는 필수입니다.', 422);
        }

        return $this->json->success($this->service->effectiveness($licenseKey, $hostId));
    }
}
```

- [ ] **Step 4: 라우트 등록**

Modify `frontapi/src/Routes.php` — import 추가:

```php
use App\Controller\LogController;
use App\Controller\NodeLockVerifyController;
use App\Controller\OpenApiController;
```

(알파벳 순서 유지 — `LogController`와 `OpenApiController` 사이)

같은 파일에서 노드락 라우트 블록 수정:

```php
        // 노드락 인증
        ['POST', '/api/v1/licenses/info', LicenseInfoController::class],
        ['POST', '/api/v1/licenses/effectiveness', EffectivenessController::class],
        ['POST', '/api/v1/licenses/bypass', BypassController::class],
        ['POST', '/api/v1/nodelock/verify', NodeLockVerifyController::class],
```

- [ ] **Step 5: PUBLIC_ROUTES 등록**

Modify `frontapi/src/Middleware/JwtAuthMiddleware.php`의 `PUBLIC_ROUTES` 배열:

```php
    private const array PUBLIC_ROUTES = [
        ['GET', '/health'],
        ['GET', '/api/docs'],
        ['GET', '/api/v1/openapi.json'],
        ['POST', '/api/v1/nodelock/verify'],
    ];
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `cd frontapi && vendor/bin/phpunit --filter NodeLockApiTest`
Expected: PASS (전체 — 기존 케이스 포함 회귀 없음 확인)

- [ ] **Step 7: 커밋**

```bash
git add frontapi/src/Controller/NodeLockVerifyController.php frontapi/src/Routes.php frontapi/src/Middleware/JwtAuthMiddleware.php frontapi/tests/NodeLockApiTest.php
git commit -m "✨ feat: 노드락 무인증 검증 엔드포인트 추가 (#112)"
```

---

## Task 2: frontapi — 플로팅 무인증 검증 엔드포인트

**Files:**
- Create: `frontapi/src/Controller/FloatingVerifyController.php`
- Modify: `frontapi/src/Routes.php`
- Modify: `frontapi/src/Middleware/JwtAuthMiddleware.php`
- Test: `frontapi/tests/FloatingApiTest.php`

**Interfaces:**
- Consumes: 기존 `App\Service\FloatingAuthService::effectiveness(string $licenseKey): array{valid:bool, reason:string, remaining:array<string,int>, status:?string, expire_date:?string}` (변경 없음)
- Produces: `POST /api/v1/floating/verify` — 무인증, 요청 `{license_key}`(host_id 불필요), 응답은 `/api/v1/floating/effectiveness`와 동일 포맷

- [ ] **Step 1: 테스트 헬퍼에 무인증 옵션 추가 + 실패하는 테스트 작성**

Modify `frontapi/tests/FloatingApiTest.php`의 `call()` 메서드:

```php
    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function call(string $path, array $body, bool $auth = true): array
    {
        $req = (new Psr17Factory())->createServerRequest('POST', $path, ['REMOTE_ADDR' => '127.0.0.1']);
        if ($auth) {
            $req = $req->withHeader('Authorization', 'Bearer ' . $this->token);
        }
        $req->getBody()->write((string) json_encode($body));

        $res  = $this->app->handle($req);
        $data = json_decode((string) $res->getBody(), true);

        return ['status' => $res->getStatusCode(), 'body' => is_array($data) ? $data : []];
    }
```

`testInvalidKey()` 메서드 뒤(클래스 닫는 `}` 앞)에 추가:

```php
    public function testPublicVerifyEndpointWorksWithoutAuth(): void
    {
        $r = $this->call('/api/v1/floating/verify', ['license_key' => 'KEY-F1'], false);

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['body']['data']['valid']);
        $this->assertSame(100, $r['body']['data']['remaining']['credit']);
    }

    public function testPublicVerifyEndpointInvalidKeyWithoutAuth(): void
    {
        $r = $this->call('/api/v1/floating/verify', ['license_key' => 'NOPE'], false);

        $this->assertSame('INVALID_LICENSE_KEY', $r['body']['data']['reason']);
    }
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd frontapi && vendor/bin/phpunit --filter testPublicVerifyEndpointWorksWithoutAuth`
Expected: FAIL — 라우트 없음(404) 또는 무인증 시 401(라우트가 기존 `/floating/effectiveness`로 착각되지 않아야 함 — 신규 경로라 아직 미존재)

- [ ] **Step 3: 컨트롤러 작성**

Create `frontapi/src/Controller/FloatingVerifyController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Input;
use App\Http\Json;
use App\Service\FloatingAuthService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 플로팅 — 데스크톱 검증 도구(tools/licverify)용 무인증 유효성 확인(잔여 카운트/크레딧).
 *
 * /api/v1/floating/effectiveness(AITessera 포털용, JWT 필수)와 동일 로직을
 * 로그인 세션 없는 독립 클라이언트가 호출할 수 있도록 무인증으로 노출한다.
 */
final class FloatingVerifyController
{
    public function __construct(
        private readonly Json $json,
        private readonly FloatingAuthService $service,
    ) {
    }

    #[OA\Post(path: '/api/v1/floating/verify', summary: '플로팅 유효성 확인(무인증, 데스크톱 도구용)', tags: ['Floating'], responses: [
        new OA\Response(response: 200, description: '유효성 결과(valid/remaining)'),
        new OA\Response(response: 422, description: '필수 파라미터 누락'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body       = Input::json($request);
        $licenseKey = trim((string) ($body['license_key'] ?? ''));
        if ($licenseKey === '') {
            return $this->json->error('VALIDATION_ERROR', 'license_key 는 필수입니다.', 422);
        }

        return $this->json->success($this->service->effectiveness($licenseKey));
    }
}
```

- [ ] **Step 4: 라우트 등록**

Modify `frontapi/src/Routes.php` — import 추가(알파벳 순서: `FloatingVerifyController`는 `HealthController` 앞, `EffectivenessController`/`FloatingEffectivenessController` 뒤):

```php
use App\Controller\FloatingEffectivenessController;
use App\Controller\FloatingVerifyController;
use App\Controller\HealthController;
```

플로팅 라우트 블록 수정:

```php
        // 플로팅 인증
        ['POST', '/api/v1/floating/activation', ActivationController::class],
        ['POST', '/api/v1/floating/effectiveness', FloatingEffectivenessController::class],
        ['POST', '/api/v1/floating/verify', FloatingVerifyController::class],
        ['POST', '/api/v1/floating/analysis/start', AnalysisStartController::class],
        ['POST', '/api/v1/floating/analysis/end', AnalysisEndController::class],
```

- [ ] **Step 5: PUBLIC_ROUTES 등록**

Modify `frontapi/src/Middleware/JwtAuthMiddleware.php`:

```php
    private const array PUBLIC_ROUTES = [
        ['GET', '/health'],
        ['GET', '/api/docs'],
        ['GET', '/api/v1/openapi.json'],
        ['POST', '/api/v1/nodelock/verify'],
        ['POST', '/api/v1/floating/verify'],
    ];
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `cd frontapi && vendor/bin/phpunit --filter FloatingApiTest`
Expected: PASS (전체 — 기존 케이스 포함 회귀 없음 확인, `call()` 시그니처 변경으로 인한 기존 호출 영향 없어야 함)

- [ ] **Step 7: frontapi 전체 검증**

Run: `cd frontapi && composer check`
Expected: PHPStan level 6 통과 + 전체 PHPUnit 그린

- [ ] **Step 8: 커밋**

```bash
git add frontapi/src/Controller/FloatingVerifyController.php frontapi/src/Routes.php frontapi/src/Middleware/JwtAuthMiddleware.php frontapi/tests/FloatingApiTest.php
git commit -m "✨ feat: 플로팅 무인증 검증 엔드포인트 추가 (#112)"
```

---

## Task 3: 문서 갱신 — `docs/nodelock-license-usage.md`

**Files:**
- Modify: `docs/nodelock-license-usage.md`

**Interfaces:**
- Consumes: Task 1·2에서 확정된 `POST /api/v1/nodelock/verify`, `POST /api/v1/floating/verify` 경로
- Produces: 없음(문서만)

- [ ] **Step 1: 5장을 포털용/도구용으로 재구성**

`docs/nodelock-license-usage.md`의 5장(`## 5. 온라인 검증 절차 (권장)`) 도입부 바로 뒤,
`### 5.1 유효성 검증` 앞에 아래 단락을 삽입:

```markdown
> 아래 엔드포인트는 **AITessera 포털 세션(JWT)** 을 가진 호출자용이다. 로그인 세션이
> 없는 독립 데스크톱 도구(`tools/licverify`, 내부 테스트용)는 **무인증** 버전인
> `POST /api/v1/nodelock/verify`(§5.4), `POST /api/v1/floating/verify`(§5.4)를 사용한다.
```

- [ ] **Step 2: §5.4 신규 섹션 추가**

`### 5.3 사용정보 수집 — POST /api/v1/licenses/bypass` 섹션 뒤, `---` 구분선 앞에 추가:

```markdown
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
```

- [ ] **Step 3: 커밋**

문서 전용 변경이지만 이번 이슈의 feature 브랜치 작업 중 일부이므로 feature 브랜치에 커밋한다(전역 "문서 전용 dev 직접 커밋" 예외는 코드 변경이 전혀 없는 독립 커밋에만 적용 — 이번 브랜치는 이미 코드 변경을 포함하므로 예외 대상 아님):

```bash
git add docs/nodelock-license-usage.md
git commit -m "📝 docs: 데스크톱 도구용 무인증 검증 엔드포인트 문서화 (#112)"
```

---

## Task 4: `tools/licverify` — Go 모듈 스캐폴드 + host_id 산출

**Files:**
- Create: `tools/licverify/go.mod`
- Create: `tools/licverify/verify/hostid.go`
- Create: `tools/licverify/verify/hostid_darwin.go`
- Create: `tools/licverify/verify/hostid_windows.go`
- Create: `tools/licverify/verify/hostid_other.go`
- Test: `tools/licverify/verify/hostid_test.go`

**Interfaces:**
- Produces: `package verify` 내 `func FormatHostID(rawUUID string) string`, `func LocalHostID() (string, error)` — 이후 모든 Task가 `verify` 패키지를 이 위치에서 import(`aicura.com/licverify/verify`)

- [ ] **Step 1: Go 모듈 초기화**

```bash
mkdir -p tools/licverify/verify
cd tools/licverify && go mod init aicura.com/licverify
```

`go.mod` 내용이 아래와 같은지 확인(버전만 다르면 직접 맞춘다):

```
module aicura.com/licverify

go 1.22
```

- [ ] **Step 2: 실패하는 테스트 작성 — 고정 벡터 회귀**

Create `tools/licverify/verify/hostid_test.go`:

```go
package verify

import (
	"regexp"
	"testing"
)

var formatPattern = regexp.MustCompile(`^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$`)

func TestFormatHostIDDeterministic(t *testing.T) {
	const uuid = "564D0102-0304-0506-0708-090A0B0C0D0E"
	if a, b := FormatHostID(uuid), FormatHostID(uuid); a != b {
		t.Fatalf("동일 입력에 다른 출력: %q != %q", a, b)
	}
}

func TestFormatHostIDFormat(t *testing.T) {
	id := FormatHostID("564D0102-0304-0506-0708-090A0B0C0D0E")
	if len(id) != 19 {
		t.Fatalf("길이 19가 아님: %d (%q)", len(id), id)
	}
	if !formatPattern.MatchString(id) {
		t.Fatalf("포맷 불일치: %q", id)
	}
}

func TestFormatHostIDNormalization(t *testing.T) {
	base := FormatHostID("ABCDEF12-3456")
	cases := []string{"abcdef12-3456", "  ABCDEF12-3456  ", "AbCdEf12-3456"}
	for _, c := range cases {
		if got := FormatHostID(c); got != base {
			t.Errorf("정규화 실패: FormatHostID(%q)=%q, 기준=%q", c, got, base)
		}
	}
}

// TestKnownVector 는 tools/hostid 와 100% 동일한 산출 규칙임을 보장하는 회귀 테스트다.
// ⚠️ 이 값이 바뀌면 기존 발급 라이센스가 전부 무효가 된다. 절대 기대값을 고쳐서 통과시키지 말 것.
func TestKnownVector(t *testing.T) {
	const input = "564D0102-0304-0506-0708-090A0B0C0D0E"
	const want = "7121-0B91-F5B6-AA1B"
	if got := FormatHostID(input); got != want {
		t.Fatalf("고정 벡터 불일치: got=%q want=%q (tools/hostid 와 산출 규칙이 달라졌는지 확인)", got, want)
	}
}
```

- [ ] **Step 3: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./verify/...`
Expected: FAIL — `undefined: FormatHostID`

- [ ] **Step 4: hostid.go 구현 (tools/hostid와 동일 규칙)**

Create `tools/licverify/verify/hostid.go`:

```go
package verify

import (
	"crypto/sha256"
	"fmt"
	"strings"
)

// namespace 는 호스트ID 산출 방식의 버전 네임스페이스다.
//
// ⚠️ 이 값과 아래 산출 규칙은 tools/hostid 와 절대 달라지면 안 된다.
// 노드락 라이센스는 발급 시점의 호스트ID 와 실행 시점 산출값을 비교해 정품 여부를
// 판단하므로, 규칙이 바뀌면 같은 컴퓨터라도 다른 호스트ID 가 나와 기존 라이센스가
// 전부 무효가 된다.
const namespace = "AILICET-NODELOCK-v1"

// FormatHostID 는 원시 머신 UUID 를 제품 전용 호스트ID 로 변환한다(순수 함수).
func FormatHostID(rawUUID string) string {
	normalized := strings.ToUpper(strings.TrimSpace(rawUUID))
	sum := sha256.Sum256([]byte(namespace + ":" + normalized))
	hexStr := fmt.Sprintf("%X", sum[:8])

	return group4(hexStr)
}

// LocalHostID 는 현재 머신의 host_id 를 계산한다(OS별 원시 UUID 취득 + 포맷).
func LocalHostID() (string, error) {
	raw, err := rawMachineUUID()
	if err != nil {
		return "", err
	}

	return FormatHostID(raw), nil
}

func group4(s string) string {
	var b strings.Builder
	for i := 0; i < len(s); i += 4 {
		if i > 0 {
			b.WriteByte('-')
		}
		end := i + 4
		if end > len(s) {
			end = len(s)
		}
		b.WriteString(s[i:end])
	}

	return b.String()
}
```

Create `tools/licverify/verify/hostid_darwin.go`:

```go
//go:build darwin

package verify

import (
	"fmt"
	"os/exec"
	"regexp"
)

var iOPlatformUUIDPattern = regexp.MustCompile(`"IOPlatformUUID"\s*=\s*"([^"]+)"`)

func rawMachineUUID() (string, error) {
	out, err := exec.Command("ioreg", "-rd1", "-c", "IOPlatformExpertDevice").Output()
	if err != nil {
		return "", fmt.Errorf("ioreg 실행 실패: %w", err)
	}

	m := iOPlatformUUIDPattern.FindSubmatch(out)
	if m == nil {
		return "", fmt.Errorf("IOPlatformUUID 를 찾을 수 없습니다")
	}

	return string(m[1]), nil
}
```

Create `tools/licverify/verify/hostid_windows.go`:

```go
//go:build windows

package verify

import (
	"fmt"
	"os/exec"
	"strings"
	"syscall"
)

func rawMachineUUID() (string, error) {
	cmd := exec.Command(
		"reg", "query",
		`HKLM\SOFTWARE\Microsoft\Cryptography`,
		"/v", "MachineGuid",
		"/reg:64",
	)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}

	out, err := cmd.Output()
	if err != nil {
		return "", fmt.Errorf("reg query 실패: %w", err)
	}

	for _, line := range strings.Split(string(out), "\n") {
		if !strings.Contains(line, "MachineGuid") {
			continue
		}
		fields := strings.Fields(line)
		if len(fields) >= 3 {
			return fields[len(fields)-1], nil
		}
	}

	return "", fmt.Errorf("MachineGuid 를 찾을 수 없습니다")
}
```

Create `tools/licverify/verify/hostid_other.go`:

```go
//go:build !darwin && !windows

package verify

import (
	"fmt"
	"os"
	"strings"
)

var machineIDPaths = []string{
	"/etc/machine-id",
	"/var/lib/dbus/machine-id",
}

func rawMachineUUID() (string, error) {
	for _, p := range machineIDPaths {
		b, err := os.ReadFile(p)
		if err != nil {
			continue
		}
		if s := strings.TrimSpace(string(b)); s != "" {
			return s, nil
		}
	}

	return "", fmt.Errorf("machine-id 를 찾을 수 없습니다")
}
```

- [ ] **Step 5: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./verify/... -run HostID -v && go test ./verify/... -run KnownVector -v`
Expected: PASS 전부

- [ ] **Step 6: 커밋**

```bash
git add tools/licverify/go.mod tools/licverify/verify/hostid.go tools/licverify/verify/hostid_darwin.go tools/licverify/verify/hostid_windows.go tools/licverify/verify/hostid_other.go tools/licverify/verify/hostid_test.go
git commit -m "✨ feat: licverify Go 모듈 스캐폴드 + host_id 산출 로직 추가 (#112)"
```

---

## Task 5: `verify` 패키지 — 봉투 파싱 + Ed25519 서명 검증

**Files:**
- Create: `tools/licverify/verify/envelope.go`
- Test: `tools/licverify/verify/envelope_test.go`

**Interfaces:**
- Consumes: 없음(stdlib만)
- Produces: `type Envelope struct{ V int; Alg string; Data string; Sig string }`, `func ParseEnvelope(raw []byte) (*Envelope, error)`, `func (e *Envelope) VerifySignature(pubKey ed25519.PublicKey) bool`, `func (e *Envelope) DecodePayload() ([]byte, error)` — Task 7이 이 타입·함수를 그대로 사용

- [ ] **Step 1: 실패하는 테스트 작성**

Create `tools/licverify/verify/envelope_test.go`:

```go
package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"testing"
)

// buildEnvelope 는 서버(sodium_crypto_sign_*)와 동일한 방식으로 봉투를 만든다:
// data = base64(payloadJSON), sig = Ed25519(data 문자열의 바이트).
func buildEnvelope(t *testing.T, priv ed25519.PrivateKey, payload map[string]any) []byte {
	t.Helper()
	payloadJSON, err := json.Marshal(payload)
	if err != nil {
		t.Fatal(err)
	}
	data := base64.StdEncoding.EncodeToString(payloadJSON)
	sig := ed25519.Sign(priv, []byte(data))

	env := Envelope{V: 1, Alg: "Ed25519", Data: data, Sig: base64.StdEncoding.EncodeToString(sig)}
	raw, err := json.Marshal(env)
	if err != nil {
		t.Fatal(err)
	}

	return raw
}

func TestParseEnvelopeAndVerifyValidSignature(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatalf("파싱 실패: %v", err)
	}
	if !env.VerifySignature(pub) {
		t.Fatal("정상 서명인데 검증 실패")
	}
}

func TestVerifySignatureRejectsTamperedData(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	env.Data = base64.StdEncoding.EncodeToString([]byte(`{"host_id":"HACKED"}`))

	if env.VerifySignature(pub) {
		t.Fatal("위조된 data 인데 검증 통과함")
	}
}

func TestVerifySignatureRejectsWrongPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	otherPub, _, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	if env.VerifySignature(otherPub) {
		t.Fatal("다른 공개키인데 검증 통과함")
	}
}

func TestVerifySignatureRejectsWrongAlg(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	env.Alg = "HS256"

	if env.VerifySignature(pub) {
		t.Fatal("alg 가 Ed25519 가 아닌데 검증 통과함")
	}
}

func TestParseEnvelopeRejectsInvalidJSON(t *testing.T) {
	if _, err := ParseEnvelope([]byte("not json")); err == nil {
		t.Fatal("잘못된 JSON 인데 에러 없음")
	}
}

func TestDecodePayloadRoundTrip(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A", "magic": "AILICET"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	if !env.VerifySignature(pub) {
		t.Fatal("검증 실패")
	}

	decoded, err := env.DecodePayload()
	if err != nil {
		t.Fatalf("디코드 실패: %v", err)
	}

	var payload map[string]any
	if err := json.Unmarshal(decoded, &payload); err != nil {
		t.Fatalf("페이로드 JSON 파싱 실패: %v", err)
	}
	if payload["host_id"] != "HOST-A" {
		t.Fatalf("host_id 불일치: %v", payload["host_id"])
	}
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./verify/... -run Envelope`
Expected: FAIL — `undefined: Envelope`, `undefined: ParseEnvelope`

- [ ] **Step 3: envelope.go 구현**

Create `tools/licverify/verify/envelope.go`:

```go
package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"fmt"
)

// Envelope 는 NLicense.lic 파일의 봉투 포맷이다.
//
//	{ "v":1, "alg":"Ed25519", "data":"<base64(payload)>", "sig":"<base64(sig)>" }
//
// 서명 대상은 Data 문자열 그 자체(base64 디코드 전)다.
type Envelope struct {
	V    int    `json:"v"`
	Alg  string `json:"alg"`
	Data string `json:"data"`
	Sig  string `json:"sig"`
}

// ParseEnvelope 는 원시 파일 바이트를 봉투로 파싱한다. 형식 오류 시 에러를 반환한다.
func ParseEnvelope(raw []byte) (*Envelope, error) {
	var env Envelope
	if err := json.Unmarshal(raw, &env); err != nil {
		return nil, fmt.Errorf("봉투 JSON 파싱 실패: %w", err)
	}
	if env.Data == "" || env.Sig == "" {
		return nil, fmt.Errorf("봉투에 data 또는 sig 가 없습니다")
	}

	return &env, nil
}

// VerifySignature 는 Data 문자열(디코드 전)에 대한 Ed25519 detached 서명을 검증한다.
// alg 가 "Ed25519" 가 아니거나 서명이 위조·손상된 경우 false 를 반환한다.
func (e *Envelope) VerifySignature(pubKey ed25519.PublicKey) bool {
	if e.Alg != "Ed25519" {
		return false
	}
	sig, err := base64.StdEncoding.DecodeString(e.Sig)
	if err != nil {
		return false
	}

	return ed25519.Verify(pubKey, []byte(e.Data), sig)
}

// DecodePayload 는 서명 검증을 통과한 뒤에만 호출해야 한다 — Data 를 base64 디코드해
// 페이로드 JSON 원문 바이트를 반환한다.
func (e *Envelope) DecodePayload() ([]byte, error) {
	decoded, err := base64.StdEncoding.DecodeString(e.Data)
	if err != nil {
		return nil, fmt.Errorf("페이로드 base64 디코드 실패: %w", err)
	}

	return decoded, nil
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./verify/... -run Envelope -v`
Expected: PASS 전부(6개)

- [ ] **Step 5: 커밋**

```bash
git add tools/licverify/verify/envelope.go tools/licverify/verify/envelope_test.go
git commit -m "✨ feat: licverify 봉투 파싱 + Ed25519 서명 검증 추가 (#112)"
```

---

## Task 6: `verify` 패키지 — 페이로드 구조체 + 스키마 검증

**Files:**
- Create: `tools/licverify/verify/payload.go`
- Test: `tools/licverify/verify/payload_test.go`

**Interfaces:**
- Consumes: 없음
- Produces: `type Payload struct{...}`(전체 필드는 아래 Step 3 참고), `type Reason string` + 상수들, `func ParsePayload(raw []byte) (*Payload, error)`, `func (p *Payload) ValidateSchema() Reason` — Task 7이 그대로 사용

- [ ] **Step 1: 실패하는 테스트 작성**

Create `tools/licverify/verify/payload_test.go`:

```go
package verify

import "testing"

func validPayloadJSON() []byte {
	return []byte(`{
		"magic": "AILICET", "payload_version": 1, "license_type": "nodelock",
		"product_code": "PT001", "product_name": "tES LAB", "host_id": "HOST-A",
		"system_id_check": true, "period_code": "perpetual", "expire_date": null,
		"modules": ["MD001"], "limits": {"count": 100}, "is_trial": false,
		"license_sn": "SN-1", "license_key": "KEY-A1", "issue_date": "2026-01-01"
	}`)
}

func TestParsePayloadValid(t *testing.T) {
	p, err := ParsePayload(validPayloadJSON())
	if err != nil {
		t.Fatalf("파싱 실패: %v", err)
	}
	if p.Magic != "AILICET" || p.HostID != "HOST-A" || p.LicenseKey != "KEY-A1" {
		t.Fatalf("필드 매핑 오류: %+v", p)
	}
	if len(p.Modules) != 1 || p.Modules[0] != "MD001" {
		t.Fatalf("modules 매핑 오류: %v", p.Modules)
	}
	if p.Limits["count"] != 100 {
		t.Fatalf("limits 매핑 오류: %v", p.Limits)
	}
}

func TestParsePayloadRejectsInvalidJSON(t *testing.T) {
	if _, err := ParsePayload([]byte("not json")); err == nil {
		t.Fatal("잘못된 JSON 인데 에러 없음")
	}
}

func TestValidateSchemaOK(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	if got := p.ValidateSchema(); got != ReasonOK {
		t.Fatalf("정상 페이로드인데 %q 반환", got)
	}
}

func TestValidateSchemaRejectsWrongMagic(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.Magic = "OTHER"
	if got := p.ValidateSchema(); got != ReasonNotAilicet {
		t.Fatalf("got=%q want=%q", got, ReasonNotAilicet)
	}
}

func TestValidateSchemaRejectsUnsupportedVersion(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.PayloadVersion = 2
	if got := p.ValidateSchema(); got != ReasonUnsupportedVersion {
		t.Fatalf("got=%q want=%q", got, ReasonUnsupportedVersion)
	}
}

func TestValidateSchemaRejectsNonNodelock(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.LicenseType = "floating"
	if got := p.ValidateSchema(); got != ReasonNotNodelock {
		t.Fatalf("got=%q want=%q", got, ReasonNotNodelock)
	}
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./verify/... -run Payload`
Expected: FAIL — `undefined: ParsePayload`, `undefined: ReasonOK` 등

- [ ] **Step 3: payload.go 구현**

Create `tools/licverify/verify/payload.go`:

```go
package verify

import (
	"encoding/json"
	"fmt"
)

// Reason 은 검증 결과/실패 사유 코드다(docs/nodelock-license-usage.md §4와 동일 어휘).
type Reason string

const (
	ReasonOK                 Reason = "OK"
	ReasonInvalidFormat      Reason = "INVALID_FORMAT"
	ReasonInvalidAlg         Reason = "INVALID_ALG"
	ReasonTampered           Reason = "TAMPERED"
	ReasonNotAilicet         Reason = "NOT_AILICET"
	ReasonUnsupportedVersion Reason = "UNSUPPORTED_VERSION"
	ReasonNotNodelock        Reason = "NOT_NODELOCK"
	ReasonHostMismatch       Reason = "HOST_MISMATCH"
	ReasonExpired            Reason = "EXPIRED"
)

// Payload 는 노드락 라이센스 페이로드다(docs/nodelock-license-usage.md §2.1 필드 전체).
type Payload struct {
	Magic          string         `json:"magic"`
	PayloadVersion int            `json:"payload_version"`
	LicenseType    string         `json:"license_type"`
	ProductCode    string         `json:"product_code"`
	ProductName    string         `json:"product_name"`
	ProductFamily  *string        `json:"product_family"`
	HostID         string         `json:"host_id"`
	SystemIDCheck  bool           `json:"system_id_check"`
	Version        *string        `json:"version"`
	PeriodCode     string         `json:"period_code"`
	ExpireDate     *string        `json:"expire_date"`
	SupportEndDate *string        `json:"support_end_date"`
	Modules        []string       `json:"modules"`
	Limits         map[string]int `json:"limits"`
	IsTrial        bool           `json:"is_trial"`
	LicenseSN      string         `json:"license_sn"`
	LicenseKey     string         `json:"license_key"`
	IssueDate      string         `json:"issue_date"`
	CompanyName    *string        `json:"company_name"`
	ChargeName     *string        `json:"charge_name"`
	ChargePhone    *string        `json:"charge_phone"`
	ChargeEmail    *string        `json:"charge_email"`
}

// ParsePayload 는 서명 검증을 통과한 페이로드 JSON 바이트를 구조체로 파싱한다.
func ParsePayload(raw []byte) (*Payload, error) {
	var p Payload
	if err := json.Unmarshal(raw, &p); err != nil {
		return nil, fmt.Errorf("페이로드 JSON 파싱 실패: %w", err)
	}

	return &p, nil
}

// ValidateSchema 는 파일 종류·스키마 버전을 확인한다. 정상이면 ReasonOK.
func (p *Payload) ValidateSchema() Reason {
	if p.Magic != "AILICET" {
		return ReasonNotAilicet
	}
	if p.PayloadVersion != 1 {
		return ReasonUnsupportedVersion
	}
	if p.LicenseType != "nodelock" {
		return ReasonNotNodelock
	}

	return ReasonOK
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./verify/... -run Payload -v`
Expected: PASS 전부(6개)

- [ ] **Step 5: 커밋**

```bash
git add tools/licverify/verify/payload.go tools/licverify/verify/payload_test.go
git commit -m "✨ feat: licverify 페이로드 구조체 + 스키마 검증 추가 (#112)"
```

---

## Task 7: `verify` 패키지 — VerifyFile 오케스트레이터(오프라인 검증 전체 흐름)

**Files:**
- Create: `tools/licverify/verify/verify.go`
- Test: `tools/licverify/verify/verify_test.go`

**Interfaces:**
- Consumes: Task 5의 `Envelope`/`ParseEnvelope`, Task 6의 `Payload`/`ParsePayload`/`ValidateSchema`/`Reason` 상수들
- Produces: `type Result struct{ Reason Reason; Payload *Payload }`, `func VerifyFile(fileBytes []byte, pubKeyB64 string, localHostID string, today string) Result` — Task 10(GUI)이 그대로 호출

- [ ] **Step 1: 실패하는 테스트 작성**

Create `tools/licverify/verify/verify_test.go`:

```go
package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"testing"
)

func signedFile(t *testing.T, priv ed25519.PrivateKey, payload map[string]any) []byte {
	t.Helper()
	payloadJSON, _ := json.Marshal(payload)
	data := base64.StdEncoding.EncodeToString(payloadJSON)
	sig := ed25519.Sign(priv, []byte(data))
	raw, _ := json.Marshal(Envelope{V: 1, Alg: "Ed25519", Data: data, Sig: base64.StdEncoding.EncodeToString(sig)})

	return raw
}

func basePayload() map[string]any {
	return map[string]any{
		"magic": "AILICET", "payload_version": 1, "license_type": "nodelock",
		"product_code": "PT001", "product_name": "tES LAB", "host_id": "HOST-A",
		"system_id_check": true, "period_code": "perpetual", "expire_date": nil,
		"modules": []string{"MD001"}, "limits": map[string]int{"count": 100},
		"is_trial": false, "license_sn": "SN-1", "license_key": "KEY-A1", "issue_date": "2026-01-01",
	}
}

func TestVerifyFileOK(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	pubB64 := base64.StdEncoding.EncodeToString(pub)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, pubB64, "HOST-A", "2026-07-19")
	if res.Reason != ReasonOK {
		t.Fatalf("got=%q want=OK", res.Reason)
	}
	if res.Payload == nil || res.Payload.ProductCode != "PT001" {
		t.Fatalf("페이로드 반환 안 됨: %+v", res.Payload)
	}
}

func TestVerifyFileTamperedSignature(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	pubB64 := base64.StdEncoding.EncodeToString(pub)
	file := signedFile(t, priv, basePayload())

	var env map[string]any
	json.Unmarshal(file, &env)
	env["data"] = base64.StdEncoding.EncodeToString([]byte(`{"host_id":"HACKED"}`))
	tampered, _ := json.Marshal(env)

	res := VerifyFile(tampered, pubB64, "HOST-A", "2026-07-19")
	if res.Reason != ReasonTampered {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonTampered)
	}
}

func TestVerifyFileWrongPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	otherPub, _, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(otherPub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonTampered {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonTampered)
	}
}

func TestVerifyFileHostMismatch(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "OTHER-HOST", "2026-07-19")
	if res.Reason != ReasonHostMismatch {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonHostMismatch)
	}
}

func TestVerifyFileExpired(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	payload := basePayload()
	payload["expire_date"] = "2020-01-01"
	file := signedFile(t, priv, payload)

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonExpired {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonExpired)
	}
}

func TestVerifyFilePerpetualNeverExpires(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload()) // expire_date: nil

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2099-01-01")
	if res.Reason != ReasonOK {
		t.Fatalf("무기한 라이센스가 만료 처리됨: %q", res.Reason)
	}
}

func TestVerifyFileNotNodelock(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	payload := basePayload()
	payload["license_type"] = "floating"
	file := signedFile(t, priv, payload)

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonNotNodelock {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonNotNodelock)
	}
}

func TestVerifyFileInvalidPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, "not-base64!!", "HOST-A", "2026-07-19")
	if res.Reason != ReasonInvalidFormat {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonInvalidFormat)
	}
}

func TestVerifyFileInvalidEnvelopeJSON(t *testing.T) {
	pub, _, _ := ed25519.GenerateKey(nil)
	res := VerifyFile([]byte("not json"), base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonInvalidFormat {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonInvalidFormat)
	}
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./verify/... -run VerifyFile`
Expected: FAIL — `undefined: VerifyFile`, `undefined: Result`

- [ ] **Step 3: verify.go 구현 (docs/nodelock-license-usage.md §4 알고리즘 그대로)**

Create `tools/licverify/verify/verify.go`:

```go
package verify

import (
	"crypto/ed25519"
	"encoding/base64"
)

// Result 는 오프라인 검증 결과다. Reason==ReasonOK 일 때만 Payload 가 채워진다.
type Result struct {
	Reason  Reason
	Payload *Payload
}

// VerifyFile 은 NLicense.lic 파일 바이트를 오프라인으로 검증한다.
//
// docs/nodelock-license-usage.md §4 알고리즘을 그대로 따른다:
//  1. 봉투 파싱 + alg 확인
//  2. Data 문자열(디코드 전)에 대한 서명 검증 — 실패 시 즉시 거부(페이로드 해석 이전)
//  3. 서명 통과 후에만 페이로드 디코드·파싱
//  4. 스키마(magic/version/license_type) 확인
//  5. system_id_check==true 면 host_id 일치 확인
//  6. expire_date 비교(null=무기한)
func VerifyFile(fileBytes []byte, pubKeyB64 string, localHostID string, today string) Result {
	pubKeyRaw, err := base64.StdEncoding.DecodeString(pubKeyB64)
	if err != nil || len(pubKeyRaw) != ed25519.PublicKeySize {
		return Result{Reason: ReasonInvalidFormat}
	}
	pubKey := ed25519.PublicKey(pubKeyRaw)

	env, err := ParseEnvelope(fileBytes)
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}
	if env.Alg != "Ed25519" {
		return Result{Reason: ReasonInvalidAlg}
	}
	if !env.VerifySignature(pubKey) {
		return Result{Reason: ReasonTampered}
	}

	decoded, err := env.DecodePayload()
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}
	payload, err := ParsePayload(decoded)
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}

	if reason := payload.ValidateSchema(); reason != ReasonOK {
		return Result{Reason: reason}
	}
	if payload.SystemIDCheck && payload.HostID != localHostID {
		return Result{Reason: ReasonHostMismatch}
	}
	if payload.ExpireDate != nil && *payload.ExpireDate < today {
		return Result{Reason: ReasonExpired}
	}

	return Result{Reason: ReasonOK, Payload: payload}
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./verify/... -v`
Expected: PASS 전부(hostid + envelope + payload + verify 전체)

- [ ] **Step 5: 커밋**

```bash
git add tools/licverify/verify/verify.go tools/licverify/verify/verify_test.go
git commit -m "✨ feat: licverify VerifyFile 오프라인 검증 오케스트레이터 추가 (#112)"
```

---

## Task 8: `client` 패키지 — 노드락 온라인 재확인

**Files:**
- Create: `tools/licverify/client/http.go`
- Create: `tools/licverify/client/nodelock.go`
- Test: `tools/licverify/client/nodelock_test.go`

**Interfaces:**
- Consumes: 없음(stdlib `net/http`만)
- Produces: `postJSON(url string, body any, out any) error`(패키지 내부용), `type NodeLockResult struct{ Valid bool; Reason string; Status *string; ExpireDate *string }`, `func VerifyNodeLock(baseURL, licenseKey, hostID string) (*NodeLockResult, error)` — Task 9·10이 `postJSON`/타입 패턴을 재사용

- [ ] **Step 1: 실패하는 테스트 작성**

Create `tools/licverify/client/nodelock_test.go`:

```go
package client

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestVerifyNodeLockSuccess(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/v1/nodelock/verify" {
			t.Fatalf("예상치 못한 경로: %s", r.URL.Path)
		}
		var body map[string]string
		json.NewDecoder(r.Body).Decode(&body)
		if body["license_key"] != "KEY-A1" || body["host_id"] != "HOST-A" {
			t.Fatalf("요청 바디 불일치: %v", body)
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data":   map[string]any{"valid": true, "reason": "OK", "status": "active", "expire_date": nil},
		})
	}))
	defer srv.Close()

	res, err := VerifyNodeLock(srv.URL, "KEY-A1", "HOST-A")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if !res.Valid || res.Reason != "OK" {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyNodeLockServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "error", "code": "VALIDATION_ERROR", "message": "host_id 는 필수입니다.",
		})
	}))
	defer srv.Close()

	_, err := VerifyNodeLock(srv.URL, "KEY-A1", "")
	if err == nil {
		t.Fatal("에러 응답인데 err==nil")
	}
}

func TestVerifyNodeLockNetworkFailure(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	srv.Close() // 즉시 종료 — 연결 불가 상태 재현

	if _, err := VerifyNodeLock(srv.URL, "KEY-A1", "HOST-A"); err == nil {
		t.Fatal("서버 다운인데 err==nil")
	}
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./client/... -run NodeLock`
Expected: FAIL — `undefined: VerifyNodeLock`

- [ ] **Step 3: http.go(공용 헬퍼) + nodelock.go 구현**

Create `tools/licverify/client/http.go`:

```go
package client

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// apiEnvelope 는 frontApi 표준 응답 포맷이다: { "status":"success"|"error", "data":..., "code":..., "message":... }
type apiEnvelope struct {
	Status  string          `json:"status"`
	Data    json.RawMessage `json:"data"`
	Code    string          `json:"code"`
	Message string          `json:"message"`
}

var httpClient = &http.Client{Timeout: 5 * time.Second}

// postJSON 은 body 를 JSON으로 POST 하고, 성공 응답의 data 를 out 에 언마샬한다.
// 에러 응답(status=="error")이거나 네트워크 실패 시 에러를 반환한다.
func postJSON(url string, body any, out any) error {
	payload, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("요청 인코딩 실패: %w", err)
	}

	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(payload))
	if err != nil {
		return fmt.Errorf("요청 생성 실패: %w", err)
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("서버에 연결할 수 없습니다: %w", err)
	}
	defer resp.Body.Close()

	var env apiEnvelope
	if err := json.NewDecoder(resp.Body).Decode(&env); err != nil {
		return fmt.Errorf("서버 응답을 해석할 수 없습니다: %w", err)
	}
	if env.Status != "success" {
		return fmt.Errorf("%s: %s", env.Code, env.Message)
	}

	return json.Unmarshal(env.Data, out)
}
```

Create `tools/licverify/client/nodelock.go`:

```go
package client

// NodeLockResult 는 POST /api/v1/nodelock/verify 응답이다.
type NodeLockResult struct {
	Valid      bool    `json:"valid"`
	Reason     string  `json:"reason"`
	Status     *string `json:"status"`
	ExpireDate *string `json:"expire_date"`
}

// VerifyNodeLock 은 frontApi 의 무인증 노드락 검증 엔드포인트를 호출한다.
func VerifyNodeLock(baseURL, licenseKey, hostID string) (*NodeLockResult, error) {
	var out NodeLockResult
	body := map[string]string{"license_key": licenseKey, "host_id": hostID}
	if err := postJSON(baseURL+"/api/v1/nodelock/verify", body, &out); err != nil {
		return nil, err
	}

	return &out, nil
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./client/... -v`
Expected: PASS 전부(3개)

- [ ] **Step 5: 커밋**

```bash
git add tools/licverify/client/http.go tools/licverify/client/nodelock.go tools/licverify/client/nodelock_test.go
git commit -m "✨ feat: licverify 노드락 온라인 재확인 클라이언트 추가 (#112)"
```

---

## Task 9: `client` 패키지 — 플로팅 온라인 확인

**Files:**
- Create: `tools/licverify/client/floating.go`
- Test: `tools/licverify/client/floating_test.go`

**Interfaces:**
- Consumes: Task 8의 `postJSON(url string, body any, out any) error`
- Produces: `type FloatingResult struct{ Valid bool; Reason string; Remaining map[string]int; Status *string; ExpireDate *string }`, `func VerifyFloating(baseURL, licenseKey string) (*FloatingResult, error)` — Task 10(GUI)이 그대로 호출

- [ ] **Step 1: 실패하는 테스트 작성**

Create `tools/licverify/client/floating_test.go`:

```go
package client

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestVerifyFloatingSuccess(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/v1/floating/verify" {
			t.Fatalf("예상치 못한 경로: %s", r.URL.Path)
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data": map[string]any{
				"valid": true, "reason": "OK",
				"remaining": map[string]int{"credit": 70}, "status": "active", "expire_date": nil,
			},
		})
	}))
	defer srv.Close()

	res, err := VerifyFloating(srv.URL, "KEY-F1")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if !res.Valid || res.Remaining["credit"] != 70 {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyFloatingInvalidKey(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data":   map[string]any{"valid": false, "reason": "INVALID_LICENSE_KEY", "remaining": map[string]int{}},
		})
	}))
	defer srv.Close()

	res, err := VerifyFloating(srv.URL, "NOPE")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if res.Valid || res.Reason != "INVALID_LICENSE_KEY" {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyFloatingNetworkFailure(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	srv.Close()

	if _, err := VerifyFloating(srv.URL, "KEY-F1"); err == nil {
		t.Fatal("서버 다운인데 err==nil")
	}
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `cd tools/licverify && go test ./client/... -run Floating`
Expected: FAIL — `undefined: VerifyFloating`

- [ ] **Step 3: floating.go 구현**

Create `tools/licverify/client/floating.go`:

```go
package client

// FloatingResult 는 POST /api/v1/floating/verify 응답이다.
type FloatingResult struct {
	Valid      bool           `json:"valid"`
	Reason     string         `json:"reason"`
	Remaining  map[string]int `json:"remaining"`
	Status     *string        `json:"status"`
	ExpireDate *string        `json:"expire_date"`
}

// VerifyFloating 은 frontApi 의 무인증 플로팅 검증 엔드포인트를 호출한다.
func VerifyFloating(baseURL, licenseKey string) (*FloatingResult, error) {
	var out FloatingResult
	body := map[string]string{"license_key": licenseKey}
	if err := postJSON(baseURL+"/api/v1/floating/verify", body, &out); err != nil {
		return nil, err
	}

	return &out, nil
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `cd tools/licverify && go test ./client/... -v`
Expected: PASS 전부(6개 — nodelock 3 + floating 3)

- [ ] **Step 5: 커밋**

```bash
git add tools/licverify/client/floating.go tools/licverify/client/floating_test.go
git commit -m "✨ feat: licverify 플로팅 온라인 확인 클라이언트 추가 (#112)"
```

---

## Task 10: GUI — Fyne 앱 (노드락 탭 + 플로팅 탭)

**Files:**
- Create: `tools/licverify/ui/app.go`
- Create: `tools/licverify/ui/nodelock_tab.go`
- Create: `tools/licverify/ui/floating_tab.go`
- Create: `tools/licverify/main.go`

**Interfaces:**
- Consumes: Task 7의 `verify.VerifyFile`/`verify.LocalHostID`/`verify.Reason` 상수, Task 8의 `client.VerifyNodeLock`, Task 9의 `client.VerifyFloating`
- Produces: `func ui.BuildWindow(a fyne.App, pubKeyB64, baseURL string) fyne.Window` — `main.go`가 호출하는 유일한 진입점

> GUI는 자동 테스트 대상이 아니다(핵심 로직은 Task 5~9에서 이미 100% 커버). 이 Task의 검증은 "go build 성공 + 수동 골든 패스 확인"이다.

- [ ] **Step 1: Fyne 의존성 추가**

```bash
cd tools/licverify && go get fyne.io/fyne/v2@latest
```

- [ ] **Step 2: 노드락 탭 구현**

Create `tools/licverify/ui/nodelock_tab.go`:

```go
package ui

import (
	"fmt"
	"os"
	"time"

	"fyne.io/fyne/v2"
	"fyne.io/fyne/v2/container"
	"fyne.io/fyne/v2/dialog"
	"fyne.io/fyne/v2/widget"

	"aicura.com/licverify/client"
	"aicura.com/licverify/verify"
)

var reasonMessages = map[verify.Reason]string{
	verify.ReasonInvalidFormat:      "파일 형식이 올바르지 않습니다.",
	verify.ReasonInvalidAlg:         "지원하지 않는 서명 알고리즘입니다.",
	verify.ReasonTampered:           "서명이 위조되었거나 파일이 손상되었습니다.",
	verify.ReasonNotAilicet:         "AILICET 라이센스 파일이 아닙니다.",
	verify.ReasonUnsupportedVersion: "지원하지 않는 라이센스 스키마 버전입니다.",
	verify.ReasonNotNodelock:        "노드락 라이센스가 아닙니다.",
	verify.ReasonHostMismatch:       "이 라이센스는 다른 컴퓨터에 발급된 것입니다.",
	verify.ReasonExpired:            "라이센스가 만료되었습니다.",
}

func derefOr(s *string, fallback string) string {
	if s == nil {
		return fallback
	}

	return *s
}

func newNodeLockTab(win fyne.Window, pubKeyB64, baseURL string) fyne.CanvasObject {
	result := widget.NewLabel("파일을 선택하세요.")
	result.Wrapping = fyne.TextWrapWord

	var lastPayload *verify.Payload
	var lastHostID string

	openBtn := widget.NewButton("NLicense.lic 파일 열기", func() {
		d := dialog.NewFileOpen(func(reader fyne.URIReadCloser, err error) {
			if err != nil || reader == nil {
				return
			}
			defer reader.Close()

			data, readErr := os.ReadFile(reader.URI().Path())
			if readErr != nil {
				result.SetText("파일을 읽을 수 없습니다: " + readErr.Error())
				return
			}

			hostID, hostErr := verify.LocalHostID()
			if hostErr != nil {
				result.SetText("호스트ID 를 계산할 수 없습니다: " + hostErr.Error())
				return
			}
			lastHostID = hostID

			today := time.Now().UTC().Format("2006-01-02")
			res := verify.VerifyFile(data, pubKeyB64, hostID, today)
			lastPayload = res.Payload

			if res.Reason != verify.ReasonOK {
				result.SetText(fmt.Sprintf("검증 실패 [%s]\n%s", res.Reason, reasonMessages[res.Reason]))
				return
			}

			result.SetText(fmt.Sprintf(
				"검증 통과\n상품: %s (%s)\n모듈: %v\n만료일: %s\nhost_id: %s",
				res.Payload.ProductName, res.Payload.ProductCode, res.Payload.Modules,
				derefOr(res.Payload.ExpireDate, "무기한"), hostID,
			))
		}, win)
		d.Show()
	})

	onlineBtn := widget.NewButton("온라인 재확인", func() {
		if lastPayload == nil {
			result.SetText("먼저 오프라인 검증을 통과한 파일을 여세요.")
			return
		}
		res, err := client.VerifyNodeLock(baseURL, lastPayload.LicenseKey, lastHostID)
		if err != nil {
			result.SetText("온라인 확인 실패: " + err.Error())
			return
		}
		result.SetText(fmt.Sprintf("온라인 결과: valid=%v reason=%s status=%s", res.Valid, res.Reason, derefOr(res.Status, "-")))
	})

	return container.NewVBox(openBtn, onlineBtn, result)
}
```

- [ ] **Step 3: 플로팅 탭 구현**

Create `tools/licverify/ui/floating_tab.go`:

```go
package ui

import (
	"fmt"

	"fyne.io/fyne/v2"
	"fyne.io/fyne/v2/container"
	"fyne.io/fyne/v2/widget"

	"aicura.com/licverify/client"
)

func newFloatingTab(baseURL string) fyne.CanvasObject {
	entry := widget.NewEntry()
	entry.SetPlaceHolder("license_key 입력")

	result := widget.NewLabel("license_key 를 입력하고 조회를 누르세요.")
	result.Wrapping = fyne.TextWrapWord

	queryBtn := widget.NewButton("조회", func() {
		key := entry.Text
		if key == "" {
			result.SetText("license_key 를 입력하세요.")
			return
		}

		res, err := client.VerifyFloating(baseURL, key)
		if err != nil {
			result.SetText("조회 실패: " + err.Error())
			return
		}

		result.SetText(fmt.Sprintf(
			"valid=%v reason=%s status=%s remaining=%v",
			res.Valid, res.Reason, derefOr(res.Status, "-"), res.Remaining,
		))
	})

	return container.NewVBox(entry, queryBtn, result)
}
```

- [ ] **Step 4: 메인 윈도우 조립**

Create `tools/licverify/ui/app.go`:

```go
package ui

import (
	"fyne.io/fyne/v2"
	"fyne.io/fyne/v2/container"
)

// BuildWindow 는 노드락/플로팅 탭을 가진 메인 윈도우를 조립한다.
func BuildWindow(a fyne.App, pubKeyB64, baseURL string) fyne.Window {
	w := a.NewWindow("AILICET 라이센스 검증기 (내부 테스트용)")
	w.Resize(fyne.NewSize(480, 420))

	tabs := container.NewAppTabs(
		container.NewTabItem("노드락", newNodeLockTab(w, pubKeyB64, baseURL)),
		container.NewTabItem("플로팅", newFloatingTab(baseURL)),
	)
	w.SetContent(tabs)

	return w
}
```

- [ ] **Step 5: 진입점 작성**

Create `tools/licverify/main.go`:

```go
package main

import (
	"fyne.io/fyne/v2/app"

	"aicura.com/licverify/ui"
)

// publicKeyB64 는 라이센스 서명 검증용 Ed25519 공개키(base64, 32바이트).
// 발급: `php spark license:keygen` 출력의 license.ed25519PublicKey 값으로 교체할 것.
// 개인키는 절대 이 파일에 포함하지 않는다.
const publicKeyB64 = "REPLACE_WITH_ACTUAL_PUBLIC_KEY_BASE64"

// baseURL 은 frontApi(AITessera) 서버 주소. 내부 테스트 환경에 맞게 교체할 것.
const baseURL = "http://localhost:8080"

func main() {
	a := app.New()
	w := ui.BuildWindow(a, publicKeyB64, baseURL)
	w.ShowAndRun()
}
```

- [ ] **Step 6: 빌드 확인**

```bash
cd tools/licverify && go mod tidy && go build -o /tmp/licverify-smoketest .
```

Expected: 빌드 성공(exit 0), 경고성 링커 메시지(`ignoring duplicate libraries`)는 무해함

- [ ] **Step 7: 수동 골든 패스 확인 (테스트용 서명 라이센스 생성)**

1. 먼저 현재 머신의 실제 host_id를 구한다:

```bash
cat > /tmp/print_hostid.go << 'EOF'
package main

import (
	"fmt"

	"aicura.com/licverify/verify"
)

func main() {
	id, err := verify.LocalHostID()
	if err != nil {
		panic(err)
	}
	fmt.Println(id)
}
EOF
cd tools/licverify && go run /tmp/print_hostid.go
```

출력된 값(예: `9F3A-1C7B-E204-8DD6`)을 아래 2단계에서 `<실제_HOST_ID>` 자리에 그대로 사용한다.

2. 위에서 얻은 실제 host_id로 테스트 키페어 + 서명된 `test-nlicense.lic`을 생성한다:

```bash
cat > /tmp/gen_test_license.go << 'EOF'
package main

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"os"
)

func main() {
	pub, priv, _ := ed25519.GenerateKey(nil)
	payload := map[string]any{
		"magic": "AILICET", "payload_version": 1, "license_type": "nodelock",
		"product_code": "PT001", "product_name": "tES LAB", "host_id": os.Args[1],
		"system_id_check": true, "period_code": "perpetual", "expire_date": nil,
		"modules": []string{"MD001"}, "limits": map[string]int{"count": 100},
		"is_trial": false, "license_sn": "SN-TEST", "license_key": "KEY-TEST", "issue_date": "2026-01-01",
	}
	payloadJSON, _ := json.Marshal(payload)
	data := base64.StdEncoding.EncodeToString(payloadJSON)
	sig := ed25519.Sign(priv, []byte(data))
	env, _ := json.Marshal(map[string]any{"v": 1, "alg": "Ed25519", "data": data, "sig": base64.StdEncoding.EncodeToString(sig)})

	os.WriteFile("/tmp/test-nlicense.lic", env, 0644)
	fmt.Println("공개키(base64):", base64.StdEncoding.EncodeToString(pub))
}
EOF
go run /tmp/gen_test_license.go <실제_HOST_ID>
```

3. 출력된 공개키(base64)를 `tools/licverify/main.go`의 `publicKeyB64`에 **임시로** 붙여넣고 앱을 실행한다:

```bash
go run .
```

4. GUI에서 "NLicense.lic 파일 열기" → `/tmp/test-nlicense.lic` 선택 → "검증 통과" 메시지와 상품·모듈 정보가 표시되는지 확인한다.
5. 플로팅 탭에 임의 `license_key`를 입력해 "조회" 클릭 → 서버가 없으면 "서버에 연결할 수 없습니다" 오류가 표시되는지 확인한다(에러 처리 경로 확인).
6. 확인 후 `main.go`의 `publicKeyB64`를 다시 `REPLACE_WITH_ACTUAL_PUBLIC_KEY_BASE64` 플레이스홀더로 되돌린다(테스트용 키를 커밋하지 않는다).

- [ ] **Step 8: 커밋**

```bash
git add tools/licverify/ui/app.go tools/licverify/ui/nodelock_tab.go tools/licverify/ui/floating_tab.go tools/licverify/main.go tools/licverify/go.mod tools/licverify/go.sum
git commit -m "✨ feat: licverify Fyne GUI(노드락/플로팅 탭) 추가 (#112)"
```

---

## Task 11: 빌드 문서 — Makefile + README

**Files:**
- Create: `tools/licverify/Makefile`
- Create: `tools/licverify/README.md`

**Interfaces:**
- Consumes: 없음
- Produces: 없음(빌드·실행 안내 문서)

- [ ] **Step 1: Makefile 작성**

Create `tools/licverify/Makefile`:

```makefile
# AILICET 라이센스 검증기(내부 테스트용) 빌드
#
# make mac     → 현재 머신(mac)에서 네이티브 빌드
# make test    → 순수 로직(verify/client) 유닛 테스트
# make run     → 로컬에서 바로 실행

BINARY := licverify

.PHONY: mac test run clean

mac:
	go build -o $(BINARY) .
	@echo "생성: $(BINARY) (네이티브 mac 빌드)"

test:
	go test ./verify/... ./client/... -v

run:
	go run .

clean:
	rm -f $(BINARY)
```

- [ ] **Step 2: README 작성**

Create `tools/licverify/README.md`:

```markdown
# AILICET 라이센스 검증기 (내부 테스트용)

노드락 라이센스 파일(`NLicense.lic`)의 오프라인 서명 검증과, 노드락/플로팅 라이센스의
온라인 재확인을 지원하는 GUI 도구다. **내부 테스트용이며 고객 배포용이 아니다**
(코드사이닝·공증 미포함).

## 사전 준비

1. `main.go`의 `publicKeyB64`를 실제 운영 Ed25519 공개키로 교체한다
   (`php spark license:keygen` 출력의 `license.ed25519PublicKey` 값. 개인키는 절대 넣지 않는다).
2. `main.go`의 `baseURL`을 테스트할 frontApi(AITessera) 서버 주소로 맞춘다.

## 빌드 · 실행

```bash
make test   # 순수 로직(verify/client) 유닛 테스트
make mac    # macOS 네이티브 빌드 → ./licverify
make run    # 빌드 없이 바로 실행
```

## Windows 실행

Fyne은 CGO 기반 GUI 툴킷이라 macOS에서 Windows용으로 크로스컴파일하려면
mingw-w64 툴체인이 필요하다. 내부 테스트 단계에서는 **Windows 머신에서 직접**
아래처럼 네이티브 빌드하는 것을 권장한다:

```powershell
go build -o licverify.exe .
```

## 구조

| 경로 | 역할 |
|------|------|
| `verify/` | 오프라인 검증 순수 로직(Ed25519 서명, 스키마, host_id, 만료) — 외부 의존 없음 |
| `client/` | frontApi 온라인 재확인 HTTP 클라이언트 |
| `ui/` | Fyne GUI (노드락 탭 / 플로팅 탭) |
| `main.go` | 진입점(공개키·서버 주소 설정) |

## 참고 문서

- 검증 알고리즘 전체 스펙: `../../docs/nodelock-license-usage.md`
- 설계 배경: `../../docs/superpowers/specs/2026-07-19-nodelock-floating-license-verifier-design.md`
```

- [ ] **Step 3: 커밋**

```bash
git add tools/licverify/Makefile tools/licverify/README.md
git commit -m "📝 docs: licverify 빌드·실행 안내 추가 (#112)"
```

---

## 최종 검증 (PR 전 체크리스트)

- [ ] `cd frontapi && composer ci` (CS Fixer + PHPStan + PHPUnit) 그린
- [ ] `cd tools/licverify && go test ./verify/... ./client/... -v` 전체 PASS
- [ ] `cd tools/licverify && go build .` 성공
- [ ] Task 10 Step 7의 수동 골든 패스(정상 파일 검증 통과, 위조 파일 거부, 플로팅 네트워크 오류 처리) 확인 완료
- [ ] `main.go`에 테스트용 공개키가 남아있지 않고 플레이스홀더로 복원되어 있는지 확인
- [ ] `git status`로 `/tmp` 스크래치 파일이 저장소에 섞이지 않았는지 확인
