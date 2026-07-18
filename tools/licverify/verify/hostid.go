package verify

import (
	"crypto/sha256"
	"fmt"
	"strings"
)

// namespace 는 호스트ID 산출 방식의 버전 네임스페이스다.
//
// ⚠️ 이 값과 아래 산출 규칙은 tools/hostid 와 절대 달라지면 안 된다.
// 노드락 라이센스는 발급 시점의 호스트ID 와 실행 시점 산출값을 비교해 정품 여부를
// 판단하므로, 규칙이 바뀌면 같은 컴퓨터라도 다른 호스트ID 가 나와 기존 라이센스가
// 전부 무효가 된다.
const namespace = "AILICET-NODELOCK-v1"

// FormatHostID 는 원시 머신 UUID 를 제품 전용 호스트ID 로 변환한다(순수 함수).
func FormatHostID(rawUUID string) string {
	normalized := strings.ToUpper(strings.TrimSpace(rawUUID))
	sum := sha256.Sum256([]byte(namespace + ":" + normalized))
	hexStr := fmt.Sprintf("%X", sum[:8])

	return group4(hexStr)
}

// LocalHostID 는 현재 머신의 host_id 를 계산한다(OS별 원시 UUID 취득 + 포맷).
func LocalHostID() (string, error) {
	raw, err := rawMachineUUID()
	if err != nil {
		return "", err
	}

	return FormatHostID(raw), nil
}

func group4(s string) string {
	var b strings.Builder
	for i := 0; i < len(s); i += 4 {
		if i > 0 {
			b.WriteByte('-')
		}
		end := i + 4
		if end > len(s) {
			end = len(s)
		}
		b.WriteString(s[i:end])
	}

	return b.String()
}
