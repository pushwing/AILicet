package client

// NodeLockResult 는 POST /api/v1/nodelock/verify 응답이다.
type NodeLockResult struct {
	Valid      bool    `json:"valid"`
	Reason     string  `json:"reason"`
	Status     *string `json:"status"`
	ExpireDate *string `json:"expire_date"`
}

// VerifyNodeLock 은 frontApi 의 무인증 노드락 검증 엔드포인트를 호출한다.
func VerifyNodeLock(baseURL, licenseKey, hostID string) (*NodeLockResult, error) {
	var out NodeLockResult
	body := map[string]string{"license_key": licenseKey, "host_id": hostID}
	if err := postJSON(baseURL+"/api/v1/nodelock/verify", body, &out); err != nil {
		return nil, err
	}

	return &out, nil
}
