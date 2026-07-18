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
