package verify

import (
	"regexp"
	"testing"
)

var formatPattern = regexp.MustCompile(`^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$`)

func TestFormatHostIDDeterministic(t *testing.T) {
	const uuid = "564D0102-0304-0506-0708-090A0B0C0D0E"
	if a, b := FormatHostID(uuid), FormatHostID(uuid); a != b {
		t.Fatalf("동일 입력에 다른 출력: %q != %q", a, b)
	}
}

func TestFormatHostIDFormat(t *testing.T) {
	id := FormatHostID("564D0102-0304-0506-0708-090A0B0C0D0E")
	if len(id) != 19 {
		t.Fatalf("길이 19가 아님: %d (%q)", len(id), id)
	}
	if !formatPattern.MatchString(id) {
		t.Fatalf("포맷 불일치: %q", id)
	}
}

func TestFormatHostIDNormalization(t *testing.T) {
	base := FormatHostID("ABCDEF12-3456")
	cases := []string{"abcdef12-3456", "  ABCDEF12-3456  ", "AbCdEf12-3456"}
	for _, c := range cases {
		if got := FormatHostID(c); got != base {
			t.Errorf("정규화 실패: FormatHostID(%q)=%q, 기준=%q", c, got, base)
		}
	}
}

// TestKnownVector 는 tools/hostid 와 100% 동일한 산출 규칙임을 보장하는 회귀 테스트다.
// ⚠️ 이 값이 바뀌면 기존 발급 라이센스가 전부 무효가 된다. 절대 기대값을 고쳐서 통과시키지 말 것.
func TestKnownVector(t *testing.T) {
	const input = "564D0102-0304-0506-0708-090A0B0C0D0E"
	const want = "7121-0B91-F5B6-AA1B"
	if got := FormatHostID(input); got != want {
		t.Fatalf("고정 벡터 불일치: got=%q want=%q (tools/hostid 와 산출 규칙이 달라졌는지 확인)", got, want)
	}
}
