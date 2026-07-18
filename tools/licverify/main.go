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
