package main

import (
	"regexp"
	"testing"
)

// formatPattern 은 출력 호스트ID 형식(XXXX-XXXX-XXXX-XXXX, 대문자 hex)이다.
var formatPattern = regexp.MustCompile(`^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$`)

// TestFormatHostIDDeterministic 은 동일 입력이 항상 동일 출력을 내는지(불변성) 검증한다.
func TestFormatHostIDDeterministic(t *testing.T) {
	const uuid = "564D0102-0304-0506-0708-090A0B0C0D0E"
	if a, b := FormatHostID(uuid), FormatHostID(uuid); a != b {
		t.Fatalf("동일 입력에 다른 출력: %q != %q", a, b)
	}
}

// TestFormatHostIDFormat 은 출력이 고정 포맷(19자)을 따르는지 검증한다.
func TestFormatHostIDFormat(t *testing.T) {
	id := FormatHostID("564D0102-0304-0506-0708-090A0B0C0D0E")
	if len(id) != 19 {
		t.Fatalf("길이 19가 아님: %d (%q)", len(id), id)
	}
	if !formatPattern.MatchString(id) {
		t.Fatalf("포맷 불일치: %q", id)
	}
}

// TestFormatHostIDNormalization 은 대소문자·공백이 정규화되어 같은 값이 나오는지 검증한다.
func TestFormatHostIDNormalization(t *testing.T) {
	base := FormatHostID("ABCDEF12-3456")
	cases := []string{"abcdef12-3456", "  ABCDEF12-3456  ", "AbCdEf12-3456"}
	for _, c := range cases {
		if got := FormatHostID(c); got != base {
			t.Errorf("정규화 실패: FormatHostID(%q)=%q, 기준=%q", c, got, base)
		}
	}
}

// TestFormatHostIDDistinct 은 서로 다른 입력이 다른 출력을 내는지 검증한다.
func TestFormatHostIDDistinct(t *testing.T) {
	if FormatHostID("uuid-a") == FormatHostID("uuid-b") {
		t.Fatal("서로 다른 입력이 같은 호스트ID 를 냄")
	}
}

// TestKnownVector 는 알고리즘 회귀 방지용 고정 벡터다.
//
// ⚠️ 이 값이 바뀌면 산출 규칙이 변경된 것이며, 기존 발급 라이센스가 전부 무효가 된다.
// 규칙을 의도적으로 바꾼 게 아니라면 절대 이 기대값을 수정해서 통과시키지 말 것.
func TestKnownVector(t *testing.T) {
	// SHA-256("AILICET-NODELOCK-v1:564D0102-0304-0506-0708-090A0B0C0D0E") 앞 8바이트.
	const input = "564D0102-0304-0506-0708-090A0B0C0D0E"
	const want = "7121-0B91-F5B6-AA1B"
	if got := FormatHostID(input); got != want {
		t.Fatalf("고정 벡터 불일치: got=%q want=%q (산출 규칙이 바뀌었는지 확인)", got, want)
	}
}
