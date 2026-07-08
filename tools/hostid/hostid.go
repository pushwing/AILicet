package main

import (
	"crypto/sha256"
	"fmt"
	"strings"
)

// namespace 는 호스트ID 산출 방식의 버전 네임스페이스다.
//
// ⚠️ 이 값과 아래 산출 규칙은 절대 변경하면 안 된다.
// 노드락 라이센스는 발급 시점의 호스트ID 와 실행 시점 산출값을 비교해 정품 여부를
// 판단하므로, 규칙이 바뀌면 같은 컴퓨터라도 다른 호스트ID 가 나와 기존 라이센스가
// 전부 무효가 된다. 불가피하게 알고리즘을 바꿔야 하면 반드시 버전을 올린다(v1 → v2).
const namespace = "AILICET-NODELOCK-v1"

// FormatHostID 는 원시 머신 UUID 를 제품 전용 호스트ID 로 변환한다.
//
// 순수 함수 — 동일 입력에 항상 동일 출력을 보장한다(불변성). OS 별 취득 로직
// (rawMachineUUID)과 분리해 두어 단위 테스트로 규칙을 고정한다.
//
// 규칙:
//  1. 입력 UUID 를 대문자로 정규화하고 앞뒤 공백을 제거한다.
//  2. "namespace:정규화값" 을 SHA-256 해시한다.
//  3. 해시 앞 8바이트(16 hex)를 대문자로 만들어 XXXX-XXXX-XXXX-XXXX 로 묶는다.
func FormatHostID(rawUUID string) string {
	normalized := strings.ToUpper(strings.TrimSpace(rawUUID))
	sum := sha256.Sum256([]byte(namespace + ":" + normalized))
	hexStr := fmt.Sprintf("%X", sum[:8]) // 앞 8바이트 → 16 hex 문자

	return group4(hexStr)
}

// group4 는 16자 문자열을 4자씩 하이픈으로 묶는다(XXXX-XXXX-XXXX-XXXX).
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
