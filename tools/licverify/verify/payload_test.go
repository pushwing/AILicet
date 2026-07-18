package verify

import "testing"

func validPayloadJSON() []byte {
	return []byte(`{
		"magic": "AILICET", "payload_version": 1, "license_type": "nodelock",
		"product_code": "PT001", "product_name": "tES LAB", "host_id": "HOST-A",
		"system_id_check": true, "period_code": "perpetual", "expire_date": null,
		"modules": ["MD001"], "limits": {"count": 100}, "is_trial": false,
		"license_sn": "SN-1", "license_key": "KEY-A1", "issue_date": "2026-01-01"
	}`)
}

func TestParsePayloadValid(t *testing.T) {
	p, err := ParsePayload(validPayloadJSON())
	if err != nil {
		t.Fatalf("파싱 실패: %v", err)
	}
	if p.Magic != "AILICET" || p.HostID != "HOST-A" || p.LicenseKey != "KEY-A1" {
		t.Fatalf("필드 매핑 오류: %+v", p)
	}
	if len(p.Modules) != 1 || p.Modules[0] != "MD001" {
		t.Fatalf("modules 매핑 오류: %v", p.Modules)
	}
	if p.Limits["count"] != 100 {
		t.Fatalf("limits 매핑 오류: %v", p.Limits)
	}
}

func TestParsePayloadRejectsInvalidJSON(t *testing.T) {
	if _, err := ParsePayload([]byte("not json")); err == nil {
		t.Fatal("잘못된 JSON 인데 에러 없음")
	}
}

func TestValidateSchemaOK(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	if got := p.ValidateSchema(); got != ReasonOK {
		t.Fatalf("정상 페이로드인데 %q 반환", got)
	}
}

func TestValidateSchemaRejectsWrongMagic(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.Magic = "OTHER"
	if got := p.ValidateSchema(); got != ReasonNotAilicet {
		t.Fatalf("got=%q want=%q", got, ReasonNotAilicet)
	}
}

func TestValidateSchemaRejectsUnsupportedVersion(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.PayloadVersion = 2
	if got := p.ValidateSchema(); got != ReasonUnsupportedVersion {
		t.Fatalf("got=%q want=%q", got, ReasonUnsupportedVersion)
	}
}

func TestValidateSchemaRejectsNonNodelock(t *testing.T) {
	p, _ := ParsePayload(validPayloadJSON())
	p.LicenseType = "floating"
	if got := p.ValidateSchema(); got != ReasonNotNodelock {
		t.Fatalf("got=%q want=%q", got, ReasonNotNodelock)
	}
}
