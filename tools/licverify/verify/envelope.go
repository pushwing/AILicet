package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"fmt"
)

// Envelope 는 NLicense.lic 파일의 봉투 포맷이다.
//
//	{ "v":1, "alg":"Ed25519", "data":"<base64(payload)>", "sig":"<base64(sig)>" }
//
// 서명 대상은 Data 문자열 그 자체(base64 디코드 전)다.
type Envelope struct {
	V    int    `json:"v"`
	Alg  string `json:"alg"`
	Data string `json:"data"`
	Sig  string `json:"sig"`
}

// ParseEnvelope 는 원시 파일 바이트를 봉투로 파싱한다. 형식 오류 시 에러를 반환한다.
func ParseEnvelope(raw []byte) (*Envelope, error) {
	var env Envelope
	if err := json.Unmarshal(raw, &env); err != nil {
		return nil, fmt.Errorf("봉투 JSON 파싱 실패: %w", err)
	}
	if env.Data == "" || env.Sig == "" {
		return nil, fmt.Errorf("봉투에 data 또는 sig 가 없습니다")
	}

	return &env, nil
}

// VerifySignature 는 Data 문자열(디코드 전)에 대한 Ed25519 detached 서명을 검증한다.
// alg 가 "Ed25519" 가 아니거나 서명이 위조·손상된 경우 false 를 반환한다.
func (e *Envelope) VerifySignature(pubKey ed25519.PublicKey) bool {
	if e.Alg != "Ed25519" {
		return false
	}
	sig, err := base64.StdEncoding.DecodeString(e.Sig)
	if err != nil {
		return false
	}

	return ed25519.Verify(pubKey, []byte(e.Data), sig)
}

// DecodePayload 는 서명 검증을 통과한 뒤에만 호출해야 한다 — Data 를 base64 디코드해
// 페이로드 JSON 원문 바이트를 반환한다.
func (e *Envelope) DecodePayload() ([]byte, error) {
	decoded, err := base64.StdEncoding.DecodeString(e.Data)
	if err != nil {
		return nil, fmt.Errorf("페이로드 base64 디코드 실패: %w", err)
	}

	return decoded, nil
}
