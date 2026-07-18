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
