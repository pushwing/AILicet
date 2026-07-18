package client

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// apiEnvelope 는 frontApi 표준 응답 포맷이다: { "status":"success"|"error", "data":..., "code":..., "message":... }
type apiEnvelope struct {
	Status  string          `json:"status"`
	Data    json.RawMessage `json:"data"`
	Code    string          `json:"code"`
	Message string          `json:"message"`
}

var httpClient = &http.Client{Timeout: 5 * time.Second}

// postJSON 은 body 를 JSON으로 POST 하고, 성공 응답의 data 를 out 에 언마샬한다.
// 에러 응답(status=="error")이거나 네트워크 실패 시 에러를 반환한다.
func postJSON(url string, body any, out any) error {
	payload, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("요청 인코딩 실패: %w", err)
	}

	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(payload))
	if err != nil {
		return fmt.Errorf("요청 생성 실패: %w", err)
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("서버에 연결할 수 없습니다: %w", err)
	}
	defer resp.Body.Close()

	var env apiEnvelope
	if err := json.NewDecoder(resp.Body).Decode(&env); err != nil {
		return fmt.Errorf("서버 응답을 해석할 수 없습니다: %w", err)
	}
	if env.Status != "success" {
		return fmt.Errorf("%s: %s", env.Code, env.Message)
	}

	return json.Unmarshal(env.Data, out)
}
