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
