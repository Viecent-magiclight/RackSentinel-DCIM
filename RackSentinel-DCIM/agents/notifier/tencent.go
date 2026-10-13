package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// TC3-HMAC-SHA256 — 参见 https://cloud.tencent.com/document/product/1128/37343

func sha256hex(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

func hmacsha256(key, msg string) []byte {
	mac := hmac.New(sha256.New, []byte(key))
	mac.Write([]byte(msg))
	return mac.Sum(nil)
}

func tencentRequest(secretID, secretKey, service, host, action, payload string) ([]byte, error) {
	algorithm := "TC3-HMAC-SHA256"
	timestamp := time.Now().Unix()
	date := time.Unix(timestamp, 0).UTC().Format("2006-01-02")

	httpRequestMethod := "POST"
	canonicalURI := "/"
	canonicalQueryString := ""
	canonicalHeaders := fmt.Sprintf("content-type:application/json; charset=utf-8\nhost:%s\n", host)
	signedHeaders := "content-type;host"
	hashedRequestPayload := sha256hex(payload)

	canonicalRequest := fmt.Sprintf("%s\n%s\n%s\n%s\n%s\n%s",
		httpRequestMethod, canonicalURI, canonicalQueryString, canonicalHeaders, signedHeaders, hashedRequestPayload)

	credentialScope := fmt.Sprintf("%s/%s/tc3_request", date, service)
	hashedCanonicalRequest := sha256hex(canonicalRequest)
	stringToSign := fmt.Sprintf("%s\n%d\n%s\n%s", algorithm, timestamp, credentialScope, hashedCanonicalRequest)

	secretDate := hmacsha256("TC3"+secretKey, date)
	secretService := hmacsha256(string(secretDate), service)
	secretSigning := hmacsha256(string(secretService), "tc3_request")
	signature := hex.EncodeToString(hmacsha256(string(secretSigning), stringToSign))

	authorization := fmt.Sprintf("%s Credential=%s/%s, SignedHeaders=%s, Signature=%s",
		algorithm, secretID, credentialScope, signedHeaders, signature)

	req, err := http.NewRequest(http.MethodPost, "https://"+host, strings.NewReader(payload))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json; charset=utf-8")
	req.Header.Set("Host", host)
	req.Header.Set("X-TC-Action", action)
	req.Header.Set("X-TC-Version", versionFor(service))
	req.Header.Set("X-TC-Timestamp", fmt.Sprintf("%d", timestamp))
	req.Header.Set("X-TC-Region", "ap-guangzhou")
	req.Header.Set("Authorization", authorization)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	return io.ReadAll(resp.Body)
}

func versionFor(service string) string {
	switch service {
	case "sms":
		return "2021-01-11"
	case "vms":
		return "2020-09-02"
	default:
		return "2020-09-02"
	}
}

func sendSMS(secretID, secretKey, sdkAppID, signName, templateID, phone string, params []string) error {
	body := map[string]interface{}{
		"PhoneNumberSet":   []string{phone},
		"SmsSdkAppId":      sdkAppID,
		"SignName":         signName,
		"TemplateId":       templateID,
		"TemplateParamSet": params,
	}
	payload, _ := json.Marshal(body)
	raw, err := tencentRequest(secretID, secretKey, "sms", "sms.tencentcloudapi.com", "SendSms", string(payload))
	if err != nil {
		return err
	}
	if !strings.Contains(string(raw), `"Code":"Ok"`) && !strings.Contains(string(raw), `"SendStatusSet"`) {
		return fmt.Errorf("sms response: %s", string(raw))
	}
	return nil
}

func sendVMS(secretID, secretKey, sdkAppID, templateID, phone string, params []string) error {
	body := map[string]interface{}{
		"VoiceSdkAppid":    sdkAppID,
		"TemplateId":       templateID,
		"CalledNumber":     phone,
		"TemplateParamSet": params,
	}
	payload, _ := json.Marshal(body)
	raw, err := tencentRequest(secretID, secretKey, "vms", "vms.tencentcloudapi.com", "SendTtsVoice", string(payload))
	if err != nil {
		return err
	}
	if strings.Contains(string(raw), `"Error"`) {
		return fmt.Errorf("vms response: %s", string(raw))
	}
	return nil
}
