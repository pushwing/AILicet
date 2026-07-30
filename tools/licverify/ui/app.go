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
