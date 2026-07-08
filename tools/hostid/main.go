// Command hostid 는 노드락 라이센스 발급용 컴퓨터 호스트ID 를 산출·출력한다.
//
// 고객이 자신의 PC(Windows·macOS)에서 실행하면 머신 고유 UUID 로부터 고정 포맷의
// 호스트ID 를 만들어 화면에 표시하고 클립보드에 복사한다. 고객은 이 값을 발급
// 담당자에게 전달하고, 담당자는 라이센스 발급 화면(host_id)에 입력한다.
//
// 산출 규칙은 hostid.go 에 고정되어 있다. 노드락 라이센스 실행 검증도 동일 규칙으로
// 호스트ID 를 재계산해 발급값과 비교하므로, 규칙은 절대 바뀌면 안 된다.
package main

import (
	"bufio"
	"fmt"
	"os"
	"runtime"
)

func main() {
	raw, err := rawMachineUUID()
	if err != nil {
		fmt.Fprintf(os.Stderr, "오류: 머신 식별자를 읽지 못했습니다: %v\n", err)
		pause()
		os.Exit(1)
	}

	hostID := FormatHostID(raw)

	fmt.Println("AILICET 노드락 호스트ID")
	fmt.Println("────────────────────────")
	fmt.Printf("플랫폼:    %s\n", platformDesc)
	fmt.Printf("호스트ID:  %s\n", hostID)

	if err := copyToClipboard(hostID); err == nil {
		fmt.Println("✓ 클립보드에 복사되었습니다. 발급 담당자에게 전달하세요.")
	} else {
		fmt.Println("(클립보드 복사 실패 — 위 호스트ID 를 직접 복사해 전달하세요.)")
	}

	pause()
}

// pause 는 Windows 에서 실행파일을 더블클릭했을 때 콘솔 창이 즉시 닫혀
// 결과를 못 보는 것을 막기 위해 엔터 입력을 기다린다.
func pause() {
	if runtime.GOOS != "windows" {
		return
	}
	fmt.Print("\n엔터를 누르면 종료합니다...")
	_, _ = bufio.NewReader(os.Stdin).ReadString('\n')
}
