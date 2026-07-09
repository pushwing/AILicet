//go:build !darwin && !windows

package main

import (
	"fmt"
	"os"
	"strings"
)

// platformDesc 는 사용자에게 보여줄 플랫폼 설명이다.
const platformDesc = "기타 OS (machine-id)"

// machineIDPaths 는 리눅스 계열에서 머신 고유 ID 가 저장되는 경로다.
var machineIDPaths = []string{
	"/etc/machine-id",
	"/var/lib/dbus/machine-id",
}

// rawMachineUUID 는 리눅스 계열의 machine-id 를 반환한다(윈도우·맥 외 폴백).
//
// 이 프로그램의 배포 대상은 Windows·macOS 이지만, 개발·테스트 환경(리눅스/CI)에서도
// 빌드·동작하도록 폴백을 둔다. machine-id 는 OS 설치 시 생성되어 유지된다.
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
