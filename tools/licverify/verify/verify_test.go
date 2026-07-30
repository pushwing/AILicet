package verify

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/json"
	"testing"
)

func signedFile(t *testing.T, priv ed25519.PrivateKey, payload map[string]any) []byte {
	t.Helper()
	payloadJSON, _ := json.Marshal(payload)
	data := base64.StdEncoding.EncodeToString(payloadJSON)
	sig := ed25519.Sign(priv, []byte(data))
	raw, _ := json.Marshal(Envelope{V: 1, Alg: "Ed25519", Data: data, Sig: base64.StdEncoding.EncodeToString(sig)})

	return raw
}

func basePayload() map[string]any {
	return map[string]any{
		"magic": "AILICET", "payload_version": 1, "license_type": "nodelock",
		"product_code": "PT001", "product_name": "tES LAB", "host_id": "HOST-A",
		"system_id_check": true, "period_code": "perpetual", "expire_date": nil,
		"modules": []string{"MD001"}, "limits": map[string]int{"count": 100},
		"is_trial": false, "license_sn": "SN-1", "license_key": "KEY-A1", "issue_date": "2026-01-01",
	}
}

func TestVerifyFileOK(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	pubB64 := base64.StdEncoding.EncodeToString(pub)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, pubB64, "HOST-A", "2026-07-19")
	if res.Reason != ReasonOK {
		t.Fatalf("got=%q want=OK", res.Reason)
	}
	if res.Payload == nil || res.Payload.ProductCode != "PT001" {
		t.Fatalf("페이로드 반환 안 됨: %+v", res.Payload)
	}
}

func TestVerifyFileTamperedSignature(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	pubB64 := base64.StdEncoding.EncodeToString(pub)
	file := signedFile(t, priv, basePayload())

	var env map[string]any
	json.Unmarshal(file, &env)
	env["data"] = base64.StdEncoding.EncodeToString([]byte(`{"host_id":"HACKED"}`))
	tampered, _ := json.Marshal(env)

	res := VerifyFile(tampered, pubB64, "HOST-A", "2026-07-19")
	if res.Reason != ReasonTampered {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonTampered)
	}
}

func TestVerifyFileWrongPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	otherPub, _, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(otherPub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonTampered {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonTampered)
	}
}

func TestVerifyFileHostMismatch(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "OTHER-HOST", "2026-07-19")
	if res.Reason != ReasonHostMismatch {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonHostMismatch)
	}
}

func TestVerifyFileExpired(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	payload := basePayload()
	payload["expire_date"] = "2020-01-01"
	file := signedFile(t, priv, payload)

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonExpired {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonExpired)
	}
}

func TestVerifyFilePerpetualNeverExpires(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload()) // expire_date: nil

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2099-01-01")
	if res.Reason != ReasonOK {
		t.Fatalf("무기한 라이센스가 만료 처리됨: %q", res.Reason)
	}
}

func TestVerifyFileNotNodelock(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(nil)
	payload := basePayload()
	payload["license_type"] = "floating"
	file := signedFile(t, priv, payload)

	res := VerifyFile(file, base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonNotNodelock {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonNotNodelock)
	}
}

func TestVerifyFileInvalidPublicKey(t *testing.T) {
	_, priv, _ := ed25519.GenerateKey(nil)
	file := signedFile(t, priv, basePayload())

	res := VerifyFile(file, "not-base64!!", "HOST-A", "2026-07-19")
	if res.Reason != ReasonInvalidFormat {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonInvalidFormat)
	}
}

func TestVerifyFileInvalidEnvelopeJSON(t *testing.T) {
	pub, _, _ := ed25519.GenerateKey(nil)
	res := VerifyFile([]byte("not json"), base64.StdEncoding.EncodeToString(pub), "HOST-A", "2026-07-19")
	if res.Reason != ReasonInvalidFormat {
		t.Fatalf("got=%q want=%q", res.Reason, ReasonInvalidFormat)
	}
}
