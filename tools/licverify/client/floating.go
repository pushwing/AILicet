package client

// FloatingResult 는 POST /api/v1/floating/verify 응답이다.
type FloatingResult struct {
	Valid      bool           `json:"valid"`
	Reason     string         `json:"reason"`
	Remaining  map[string]int `json:"remaining"`
	Status     *string        `json:"status"`
	ExpireDate *string        `json:"expire_date"`
}

// VerifyFloating 은 frontApi 의 무인증 플로팅 검증 엔드포인트를 호출한다.
func VerifyFloating(baseURL, licenseKey string) (*FloatingResult, error) {
	var out FloatingResult
	body := map[string]string{"license_key": licenseKey}
	if err := postJSON(baseURL+"/api/v1/floating/verify", body, &out); err != nil {
		return nil, err
	}

	return &out, nil
}
