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
