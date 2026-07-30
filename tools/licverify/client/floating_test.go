package client

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestVerifyFloatingSuccess(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/v1/floating/verify" {
			t.Fatalf("예상치 못한 경로: %s", r.URL.Path)
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data": map[string]any{
				"valid": true, "reason": "OK",
				"remaining": map[string]int{"credit": 70}, "status": "active", "expire_date": nil,
			},
		})
	}))
	defer srv.Close()

	res, err := VerifyFloating(srv.URL, "KEY-F1")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if !res.Valid || res.Remaining["credit"] != 70 {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyFloatingInvalidKey(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]any{
			"status": "success",
			"data":   map[string]any{"valid": false, "reason": "INVALID_LICENSE_KEY", "remaining": map[string]int{}},
		})
	}))
	defer srv.Close()

	res, err := VerifyFloating(srv.URL, "NOPE")
	if err != nil {
		t.Fatalf("에러: %v", err)
	}
	if res.Valid || res.Reason != "INVALID_LICENSE_KEY" {
		t.Fatalf("결과 불일치: %+v", res)
	}
}

func TestVerifyFloatingNetworkFailure(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	srv.Close()

	if _, err := VerifyFloating(srv.URL, "KEY-F1"); err == nil {
		t.Fatal("서버 다운인데 err==nil")
	}
}
