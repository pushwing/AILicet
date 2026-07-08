package main

import (
	"os/exec"
	"runtime"
)

// copyToClipboard 는 OS 기본 도구로 텍스트를 클립보드에 복사한다.
//
// macOS: pbcopy / Windows: clip / 그 외: xclip. 모두 OS 기본 또는 흔한 도구라
// 별도 Go 의존성이 없다. 복사 실패는 치명적이지 않다(호스트ID 는 화면에도 출력됨).
func copyToClipboard(text string) error {
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "darwin":
		cmd = exec.Command("pbcopy")
	case "windows":
		cmd = exec.Command("clip")
	default:
		cmd = exec.Command("xclip", "-selection", "clipboard")
	}

	in, err := cmd.StdinPipe()
	if err != nil {
		return err
	}
	if err := cmd.Start(); err != nil {
		return err
	}
	if _, err := in.Write([]byte(text)); err != nil {
		_ = in.Close()
		return err
	}
	if err := in.Close(); err != nil {
		return err
	}

	return cmd.Wait()
}
