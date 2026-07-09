//go:build darwin

package main

import (
	"fmt"
	"os/exec"
	"regexp"
)

// platformDesc 는 사용자에게 보여줄 플랫폼 설명이다.
const platformDesc = "macOS (IOPlatformUUID)"

// iOPlatformUUIDPattern 은 ioreg 출력에서 IOPlatformUUID 값을 뽑아내는 정규식이다.
var iOPlatformUUIDPattern = regexp.MustCompile(`"IOPlatformUUID"\s*=\s*"([^"]+)"`)

// rawMachineUUID 는 macOS 의 IOPlatformUUID 를 반환한다.
//
// IOPlatformUUID 는 메인보드에 결속된 고유값으로, 재부팅·OS 재설치·업데이트에도
// 유지된다. ioreg 는 macOS 기본 제공 도구라 별도 설치나 관리자 권한이 필요 없다.
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
