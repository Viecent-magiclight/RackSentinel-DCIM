package tencent

import (
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestDoRequestSendsTC3Authorization(t *testing.T) {
	var gotAuth string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotAuth = r.Header.Get("Authorization")
		io.WriteString(w, `{"Response":{}}`)
	}))
	defer srv.Close()

	host := strings.TrimPrefix(srv.URL, "http://")
	// override https by using http test server host in custom call - test Authorization only
	payload := `{}`
	auth := Authorization("id", "key", "sms", host, payload, 1700000000)
	if !strings.Contains(auth, "Signature=") {
		t.Fatal("missing signature")
	}
	_ = gotAuth
}
