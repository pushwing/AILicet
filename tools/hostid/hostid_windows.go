//go:build windows

package main

import (
	"fmt"
	"os/exec"
	"strings"
	"syscall"
)

// platformDesc 는 사용자에게 보여줄 플랫폼 설명이다.
const platformDesc = "Windows (MachineGuid)"

var (
	kernel32               = syscall.NewLazyDLL("kernel32.dll")
	procSetConsoleOutputCP = kernel32.NewProc("SetConsoleOutputCP")
)

// init 는 콘솔 출력 코드페이지를 UTF-8(65001)로 바꾼다.
//
// 한국어 Windows 의 cmd.exe 는 기본 코드페이지가 949 라, UTF-8 로 인코딩된 한글·박스
// 문자(─, ✓)가 깨져 출력된다. 고객이 호스트ID 를 정확히 읽고 복사해야 하므로 UTF-8 로
// 강제한다.
func init() {
	_, _, _ = procSetConsoleOutputCP.Call(uintptr(65001))
}

// rawMachineUUID 는 Windows 레지스트리의 MachineGuid 를 반환한다.
//
// MachineGuid(HKLM\SOFTWARE\Microsoft\Cryptography\MachineGuid)는 OS 설치 시
// 생성되어 재부팅·업데이트에도 유지되는 안정적인 머신 식별자다. reg.exe 는 Windows
// 기본 제공 도구라 별도 의존성이 없다.
//
// HideWindow 로 콘솔 창 깜빡임을 억제한다.
func rawMachineUUID() (string, error) {
	// /reg:64 로 64비트 레지스트리 뷰를 명시한다. 이 옵션이 없으면 32비트 프로세스에서
	// WOW6432Node 로 리디렉션되어 MachineGuid 가 없거나 다른 값이 나올 수 있다(불변성 위반).
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

	// 출력 예: "    MachineGuid    REG_SZ    xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
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
