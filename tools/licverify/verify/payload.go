package verify

import (
	"encoding/json"
	"fmt"
)

// Reason 은 검증 결과/실패 사유 코드다(docs/nodelock-license-usage.md §4와 동일 어휘).
type Reason string

const (
	ReasonOK                 Reason = "OK"
	ReasonInvalidFormat      Reason = "INVALID_FORMAT"
	ReasonInvalidAlg         Reason = "INVALID_ALG"
	ReasonTampered           Reason = "TAMPERED"
	ReasonNotAilicet         Reason = "NOT_AILICET"
	ReasonUnsupportedVersion Reason = "UNSUPPORTED_VERSION"
	ReasonNotNodelock        Reason = "NOT_NODELOCK"
	ReasonHostMismatch       Reason = "HOST_MISMATCH"
	ReasonExpired            Reason = "EXPIRED"
)

// Payload 는 노드락 라이센스 페이로드다(docs/nodelock-license-usage.md §2.1 필드 전체).
type Payload struct {
	Magic          string         `json:"magic"`
	PayloadVersion int            `json:"payload_version"`
	LicenseType    string         `json:"license_type"`
	ProductCode    string         `json:"product_code"`
	ProductName    string         `json:"product_name"`
	ProductFamily  *string        `json:"product_family"`
	HostID         string         `json:"host_id"`
	SystemIDCheck  bool           `json:"system_id_check"`
	Version        *string        `json:"version"`
	PeriodCode     string         `json:"period_code"`
	ExpireDate     *string        `json:"expire_date"`
	SupportEndDate *string        `json:"support_end_date"`
	Modules        []string       `json:"modules"`
	Limits         map[string]int `json:"limits"`
	IsTrial        bool           `json:"is_trial"`
	LicenseSN      string         `json:"license_sn"`
	LicenseKey     string         `json:"license_key"`
	IssueDate      string         `json:"issue_date"`
	CompanyName    *string        `json:"company_name"`
	ChargeName     *string        `json:"charge_name"`
	ChargePhone    *string        `json:"charge_phone"`
	ChargeEmail    *string        `json:"charge_email"`
}

// ParsePayload 는 서명 검증을 통과한 페이로드 JSON 바이트를 구조체로 파싱한다.
func ParsePayload(raw []byte) (*Payload, error) {
	var p Payload
	if err := json.Unmarshal(raw, &p); err != nil {
		return nil, fmt.Errorf("페이로드 JSON 파싱 실패: %w", err)
	}

	return &p, nil
}

// ValidateSchema 는 파일 종류·스키마 버전을 확인한다. 정상이면 ReasonOK.
func (p *Payload) ValidateSchema() Reason {
	if p.Magic != "AILICET" {
		return ReasonNotAilicet
	}
	if p.PayloadVersion != 1 {
		return ReasonUnsupportedVersion
	}
	if p.LicenseType != "nodelock" {
		return ReasonNotNodelock
	}

	return ReasonOK
}
