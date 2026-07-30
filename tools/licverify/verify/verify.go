package verify

import (
	"crypto/ed25519"
	"encoding/base64"
)

// Result 는 오프라인 검증 결과다. Reason==ReasonOK 일 때만 Payload 가 채워진다.
type Result struct {
	Reason  Reason
	Payload *Payload
}

// VerifyFile 은 NLicense.lic 파일 바이트를 오프라인으로 검증한다.
//
// docs/nodelock-license-usage.md §4 알고리즘을 그대로 따른다:
//  1. 봉투 파싱 + alg 확인
//  2. Data 문자열(디코드 전)에 대한 서명 검증 — 실패 시 즉시 거부(페이로드 해석 이전)
//  3. 서명 통과 후에만 페이로드 디코드·파싱
//  4. 스키마(magic/version/license_type) 확인
//  5. system_id_check==true 면 host_id 일치 확인
//  6. expire_date 비교(null=무기한)
func VerifyFile(fileBytes []byte, pubKeyB64 string, localHostID string, today string) Result {
	pubKeyRaw, err := base64.StdEncoding.DecodeString(pubKeyB64)
	if err != nil || len(pubKeyRaw) != ed25519.PublicKeySize {
		return Result{Reason: ReasonInvalidFormat}
	}
	pubKey := ed25519.PublicKey(pubKeyRaw)

	env, err := ParseEnvelope(fileBytes)
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}
	if env.Alg != "Ed25519" {
		return Result{Reason: ReasonInvalidAlg}
	}
	if !env.VerifySignature(pubKey) {
		return Result{Reason: ReasonTampered}
	}

	decoded, err := env.DecodePayload()
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}
	payload, err := ParsePayload(decoded)
	if err != nil {
		return Result{Reason: ReasonInvalidFormat}
	}

	if reason := payload.ValidateSchema(); reason != ReasonOK {
		return Result{Reason: reason}
	}
	if payload.SystemIDCheck && payload.HostID != localHostID {
		return Result{Reason: ReasonHostMismatch}
	}
	if payload.ExpireDate != nil && *payload.ExpireDate < today {
		return Result{Reason: ReasonExpired}
	}

	return Result{Reason: ReasonOK, Payload: payload}
}
