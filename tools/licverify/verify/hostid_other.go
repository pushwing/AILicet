//go:build !darwin && !windows

package verify

import (
	"fmt"
	"os"
	"strings"
)

var machineIDPaths = []string{
	"/etc/machine-id",
	"/var/lib/dbus/machine-id",
}

func rawMachineUUID() (string, error) {
	for _, p := range machineIDPaths {
		b, err := os.ReadFile(p)
		if err != nil {
			continue
		}
		if s := strings.TrimSpace(string(b)); s != "" {
			return s, nil
		}
	}

	return "", fmt.Errorf("machine-id 를 찾을 수 없습니다")
}
