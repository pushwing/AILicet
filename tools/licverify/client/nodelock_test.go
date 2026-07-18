package client

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestVerifyNodeLockSuccess(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/v1/nodelock/verify" {
			t.Fatalf("예상치 못한 경로: %s", r.URL.Path)
		}
		var body map[string]string
		json.NewDecoder(r.Body).Decode(&body)
		if body["license_key"] != "KEY-A1" || body["host_id"] != "HOST-A" {
			t.Fatalf("요청 바디 불일치: %v", body)
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data":   map[string]any{"valid": true, "reason": "OK", "status": "active", "expire_date": nil},
		})
	}))
	defer srv.Close()

	res, err := VerifyNodeLock(srv.URL, "KEY-A1", "HOST-A")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if !res.Valid || res.Reason != "OK" {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyNodeLockServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "error", "code": "VALIDATION_ERROR", "message": "host_id 는 필수입니다.",
		})
	}))
	defer srv.Close()

	_, err := VerifyNodeLock(srv.URL, "KEY-A1", "")
	if err == nil {
		t.Fatal("에러 응답인데 err==nil")
	}
}

func TestVerifyNodeLockNetworkFailure(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	srv.Close() // 즉시 종료 — 연결 불가 상태 재현

	if _, err := VerifyNodeLock(srv.URL, "KEY-A1", "HOST-A"); err == nil {
		t.Fatal("서버 다운인데 err==nil")
	}
}
