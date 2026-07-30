package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"testing"
)

// buildEnvelope 는 서버(sodium_crypto_sign_*)와 동일한 방식으로 봉투를 만든다:
// data = base64(payloadJSON), sig = Ed25519(data 문자열의 바이트).
func buildEnvelope(t *testing.T, priv ed25519.PrivateKey, payload map[string]any) []byte {
	t.Helper()
	payloadJSON, err := json.Marshal(payload)
	if err != nil {
		t.Fatal(err)
	}
	data := base64.StdEncoding.EncodeToString(payloadJSON)
	sig := ed25519.Sign(priv, []byte(data))

	env := Envelope{V: 1, Alg: "Ed25519", Data: data, Sig: base64.StdEncoding.EncodeToString(sig)}
	raw, err := json.Marshal(env)
	if err != nil {
		t.Fatal(err)
	}

	return raw
}

func TestParseEnvelopeAndVerifyValidSignature(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatalf("파싱 실패: %v", err)
	}
	if !env.VerifySignature(pub) {
		t.Fatal("정상 서명인데 검증 실패")
	}
}

func TestVerifySignatureRejectsTamperedData(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	env.Data = base64.StdEncoding.EncodeToString([]byte(`{"host_id":"HACKED"}`))

	if env.VerifySignature(pub) {
		t.Fatal("위조된 data 인데 검증 통과함")
	}
}

func TestVerifySignatureRejectsWrongPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	otherPub, _, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	if env.VerifySignature(otherPub) {
		t.Fatal("다른 공개키인데 검증 통과함")
	}
}

func TestVerifySignatureRejectsWrongAlg(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	env.Alg = "HS256"

	if env.VerifySignature(pub) {
		t.Fatal("alg 가 Ed25519 가 아닌데 검증 통과함")
	}
}

func TestParseEnvelopeRejectsInvalidJSON(t *testing.T) {
	if _, err := ParseEnvelope([]byte("not json")); err == nil {
		t.Fatal("잘못된 JSON 인데 에러 없음")
	}
}

func TestDecodePayloadRoundTrip(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	raw := buildEnvelope(t, priv, map[string]any{"host_id": "HOST-A", "magic": "AILICET"})

	env, err := ParseEnvelope(raw)
	if err != nil {
		t.Fatal(err)
	}
	if !env.VerifySignature(pub) {
		t.Fatal("검증 실패")
	}

	decoded, err := env.DecodePayload()
	if err != nil {
		t.Fatalf("디코드 실패: %v", err)
	}

	var payload map[string]any
	if err := json.Unmarshal(decoded, &payload); err != nil {
		t.Fatalf("페이로드 JSON 파싱 실패: %v", err)
	}
	if payload["host_id"] != "HOST-A" {
		t.Fatalf("host_id 불일치: %v", payload["host_id"])
	}
}
