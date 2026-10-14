package tencent

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"time"
)

func SHA256Hex(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

func HMACSHA256(key, msg string) []byte {
	mac := hmac.New(sha256.New, []byte(key))
	mac.Write([]byte(msg))
	return mac.Sum(nil)
}

// Authorization builds TC3-HMAC-SHA256 Authorization header value.
func Authorization(secretID, secretKey, service, host, payload string, ts int64) string {
	algorithm := "TC3-HMAC-SHA256"
	date := time.Unix(ts, 0).UTC().Format("2006-01-02")
	canonicalHeaders := fmt.Sprintf("content-type:application/json; charset=utf-8\nhost:%s\n", host)
	signedHeaders := "content-type;host"
	hashedPayload := SHA256Hex(payload)
	canonicalRequest := fmt.Sprintf("%s\n%s\n%s\n%s\n%s\n%s",
		"POST", "/", "", canonicalHeaders, signedHeaders, hashedPayload)
	credentialScope := fmt.Sprintf("%s/%s/tc3_request", date, service)
	stringToSign := fmt.Sprintf("%s\n%d\n%s\n%s", algorithm, ts, credentialScope, SHA256Hex(canonicalRequest))
	secretDate := HMACSHA256("TC3"+secretKey, date)
	secretService := HMACSHA256(string(secretDate), service)
	secretSigning := HMACSHA256(string(secretService), "tc3_request")
	signature := hex.EncodeToString(HMACSHA256(string(secretSigning), stringToSign))
	return fmt.Sprintf("%s Credential=%s/%s, SignedHeaders=%s, Signature=%s",
		algorithm, secretID, credentialScope, signedHeaders, signature)
}
