# AILICET 호스트ID 유틸리티

노드락(Node-Lock) 라이센스 발급용 **컴퓨터 호스트ID 산출 프로그램**. 고객이 자신의
PC(Windows·macOS)에서 실행하면 머신 고유값으로부터 고정 포맷의 호스트ID 를 만들어
화면에 표시하고 클립보드에 복사한다.

## 목적 · 흐름

```
고객 PC                          발급 담당자
  │ hostid 실행                     │
  │ → 9F3A-1C7B-E204-8DD6 (예)      │
  │ ── 호스트ID 전달 ─────────────▶ │
  │                                 │ 라이센스 발급 화면 host_id 에 입력
  │                                 │ → 노드락 라이센스 발급
  │ ◀──────── 라이센스 파일 ────────│
```

발급된 노드락 라이센스는 실행 시점에 **같은 규칙으로 호스트ID 를 재계산**해 발급값과
비교하여 정품 여부를 판단한다(`system_id_check`). 따라서 이 프로그램의 산출 규칙은
라이센스 검증 로직과 반드시 일치해야 한다.

## 호스트ID 산출 규칙 (⚠️ 불변)

> **이 규칙은 절대 바꾸면 안 된다.** 규칙이 바뀌면 같은 컴퓨터라도 다른 호스트ID 가
> 나와 이미 발급된 모든 노드락 라이센스가 무효가 된다. 불가피하게 바꿔야 하면
> 네임스페이스 버전을 올린다(`v1` → `v2`).

1. **OS별 원시 머신 UUID 취득**
   | OS | 원본 | 취득 방법 |
   |----|------|-----------|
   | macOS | `IOPlatformUUID` (메인보드 고유) | `ioreg -rd1 -c IOPlatformExpertDevice` |
   | Windows | `MachineGuid` (OS 설치 시 생성) | 레지스트리 `HKLM\SOFTWARE\Microsoft\Cryptography\MachineGuid` |
   | 기타(리눅스/CI) | `machine-id` | `/etc/machine-id` |
2. **정규화**: 대문자 변환 + 앞뒤 공백 제거
3. **해시**: `SHA-256("AILICET-NODELOCK-v1:" + 정규화값)`
4. **포맷**: 해시 앞 8바이트(16 hex) → 대문자 → `XXXX-XXXX-XXXX-XXXX` (19자 고정)

이 값들은 재부팅·OS 업데이트·시간 경과에 바뀌지 않는다. MAC 주소·디스크 시리얼 등
휘발성·교체 가능 요소는 의도적으로 배제했다.

### 다른 언어로 재현할 때 (제품 클라이언트용)

라이센스 검증 클라이언트를 다른 언어로 구현할 때 아래를 그대로 재현하면 동일한
호스트ID 가 나온다. 회귀 방지용 고정 벡터:

```
입력 UUID : 564D0102-0304-0506-0708-090A0B0C0D0E
호스트ID  : 7121-0B91-F5B6-AA1B
```

즉 `SHA-256("AILICET-NODELOCK-v1:564D0102-0304-0506-0708-090A0B0C0D0E")` 의
앞 8바이트를 대문자 hex 로 만든 `71210B91F5B6AA1B` 를 4자씩 끊은 값이다.

## 빌드

Go 1.22+ 필요. 순수 stdlib 만 사용하므로 외부 의존성·인터넷 없이 빌드된다.

```bash
make            # dist/ 에 mac(universal) + windows 실행파일 생성
make mac        # macOS universal 만
make windows    # Windows amd64 만
make test       # 단위 테스트
```

교차 컴파일은 어느 OS 에서도 가능하다(예: macOS 에서 `make windows` 로 .exe 생성).

## 코드사이닝 (배포 전 필수)

미서명 실행파일은 macOS **Gatekeeper**·Windows **SmartScreen** 이 차단·경고하여, 고객이
실행을 못 하거나 불신할 수 있다. 배포 전 서명한다. 실제 서명에는 유효한 인증서가
필요하며, 자격증명은 **환경변수로만** 주입한다(저장소에 넣지 말 것).

```bash
# macOS: Developer ID + 공증(notarization)
export MAC_SIGN_IDENTITY="Developer ID Application: 회사명 (TEAMID)"
export AC_APPLE_ID="dev@회사.com" AC_TEAM_ID="TEAMID" AC_PASSWORD="app-specific-pw"

# Windows: 코드사이닝 인증서(.pfx). macOS 에서 교차 서명 시 osslsigncode 사용
#   brew install osslsigncode
export WIN_PFX="/path/to/codesign.pfx" WIN_PFX_PASSWORD="pfx-pw"

make            # 빌드
make sign       # 또는: ./sign.sh mac | ./sign.sh windows | ./sign.sh all
```

- macOS: `Developer ID Application` 인증서로 서명 후 `notarytool` 로 공증한다. 바레 CLI
  바이너리는 stapling 이 안 되므로 zip 을 공증한다(Gatekeeper 가 온라인으로 티켓 확인).
- Windows: `signtool`(Windows) 또는 `osslsigncode`(macOS·Linux 교차) 로 서명하며 타임스탬프
  서버(`/tr`)를 지정해 인증서 만료 후에도 서명이 유효하게 한다.
- 자격증명 미설정 시 스크립트는 어떤 변수가 없는지 명시하고 중단한다.

## 실행 · 배포

- **macOS**: 터미널에서 `./hostid-mac` 실행
- **Windows**: `hostid.exe` 더블클릭 또는 명령프롬프트에서 실행 (결과 확인 후 엔터로 종료)

산출된 호스트ID 는 자동으로 클립보드에 복사된다.

```
AILICET 노드락 호스트ID
────────────────────────
플랫폼:    macOS (IOPlatformUUID)
호스트ID:  9F3A-1C7B-E204-8DD6
✓ 클립보드에 복사되었습니다. 발급 담당자에게 전달하세요.
```

## 구성

| 파일 | 역할 |
|------|------|
| `main.go` | 진입점 — 산출·출력·클립보드 복사 |
| `hostid.go` | 산출 규칙(순수 함수) — 정규화·해시·포맷 |
| `hostid_darwin.go` | macOS 원시 UUID 취득 |
| `hostid_windows.go` | Windows 원시 UUID 취득 |
| `hostid_other.go` | 리눅스 등 폴백 |
| `clipboard.go` | OS별 클립보드 복사 |
| `hostid_test.go` | 산출 규칙 회귀 테스트(고정 벡터 포함) |
| `sign.sh` | 배포용 코드사이닝(macOS 공증 / Windows 서명) |
