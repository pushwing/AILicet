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
