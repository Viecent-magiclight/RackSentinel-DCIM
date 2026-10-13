package tencent

import "testing"

// Vector aligned with Tencent Cloud API 3.0 signing walkthrough (payload hash + structure).
func TestAuthorizationDeterministic(t *testing.T) {
	secretID := "AKIDxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
	secretKey := "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
	service := "sms"
	host := "sms.tencentcloudapi.com"
	payload := `{"SmsSdkAppId":"1400000000"}`
	ts := int64(1551113065)
	auth := Authorization(secretID, secretKey, service, host, payload, ts)
	if auth == "" || !contains(auth, "TC3-HMAC-SHA256 Credential=") {
		t.Fatalf("unexpected auth: %s", auth)
	}
	// Same input must be stable
	if Authorization(secretID, secretKey, service, host, payload, ts) != auth {
		t.Fatal("signature not deterministic")
	}
}

func contains(s, sub string) bool {
	return len(s) >= len(sub) && (s == sub || len(sub) == 0 || indexOf(s, sub) >= 0)
}

func indexOf(s, sub string) int {
	for i := 0; i+len(sub) <= len(s); i++ {
		if s[i:i+len(sub)] == sub {
			return i
		}
	}
	return -1
}
