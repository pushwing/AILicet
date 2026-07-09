#!/usr/bin/env bash
#
# hostid 실행파일 코드사이닝 스크립트.
#
# 미서명 배포 시 macOS Gatekeeper·Windows SmartScreen 이 실행을 차단·경고한다.
# 이 스크립트는 dist/ 의 빌드 산출물에 서명한다. 실제 서명에는 유효한 인증서가
# 필요하며, 자격증명은 환경변수로 주입한다(코드·저장소에 넣지 말 것).
#
# 사용:
#   make            # 먼저 빌드
#   ./sign.sh mac       # macOS 바이너리 서명 + 공증
#   ./sign.sh windows   # Windows exe 서명
#   ./sign.sh all
#
# 필요 환경변수:
#   [macOS]   MAC_SIGN_IDENTITY   "Developer ID Application: 회사명 (TEAMID)"
#             AC_APPLE_ID         공증용 Apple ID
#             AC_TEAM_ID          팀 ID
#             AC_PASSWORD         앱 암호(app-specific password)
#   [Windows] WIN_PFX             코드사이닝 인증서(.pfx) 경로
#             WIN_PFX_PASSWORD    pfx 암호
#
set -euo pipefail

DIST="dist"
TARGET="${1:-all}"

sign_mac() {
    local bin="$DIST/hostid-mac"
    [[ -f "$bin" ]] || { echo "✗ $bin 없음 — 먼저 'make mac' 실행"; return 1; }
    : "${MAC_SIGN_IDENTITY:?MAC_SIGN_IDENTITY 미설정}"

    echo "▶ macOS 서명: $bin"
    codesign --force --timestamp --options runtime \
        --sign "$MAC_SIGN_IDENTITY" "$bin"
    codesign --verify --verbose "$bin"

    # 공증: 바레 CLI 바이너리는 stapling 불가 → zip 을 공증한다(Gatekeeper 는 온라인 확인).
    if [[ -n "${AC_APPLE_ID:-}" && -n "${AC_TEAM_ID:-}" && -n "${AC_PASSWORD:-}" ]]; then
        echo "▶ 공증 제출"
        ditto -c -k "$bin" "$DIST/hostid-mac.zip"
        xcrun notarytool submit "$DIST/hostid-mac.zip" \
            --apple-id "$AC_APPLE_ID" --team-id "$AC_TEAM_ID" \
            --password "$AC_PASSWORD" --wait
        echo "✓ 공증 완료 (배포: $DIST/hostid-mac.zip)"
    else
        echo "· 공증 자격증명(AC_*) 미설정 — 서명만 수행"
    fi
}

sign_windows() {
    local bin="$DIST/hostid.exe"
    [[ -f "$bin" ]] || { echo "✗ $bin 없음 — 먼저 'make windows' 실행"; return 1; }
    : "${WIN_PFX:?WIN_PFX 미설정}"
    : "${WIN_PFX_PASSWORD:?WIN_PFX_PASSWORD 미설정}"

    echo "▶ Windows 서명: $bin"
    if command -v signtool >/dev/null 2>&1; then
        # Windows 호스트: 공식 signtool
        signtool sign /fd SHA256 /f "$WIN_PFX" /p "$WIN_PFX_PASSWORD" \
            /tr http://timestamp.digicert.com /td SHA256 "$bin"
    elif command -v osslsigncode >/dev/null 2>&1; then
        # macOS·Linux 교차 서명: osslsigncode (brew install osslsigncode)
        osslsigncode sign -pkcs12 "$WIN_PFX" -pass "$WIN_PFX_PASSWORD" \
            -t http://timestamp.digicert.com \
            -in "$bin" -out "$DIST/hostid-signed.exe"
        mv "$DIST/hostid-signed.exe" "$bin"
    else
        echo "✗ signtool 또는 osslsigncode 필요 (brew install osslsigncode)"; return 1
    fi
    echo "✓ Windows 서명 완료"
}

case "$TARGET" in
    mac)     sign_mac ;;
    windows) sign_windows ;;
    all)     sign_mac; sign_windows ;;
    *)       echo "사용: $0 {mac|windows|all}"; exit 1 ;;
esac
