package tencent

import (
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

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

func DoRequest(secretID, secretKey, service, host, action, payload string) ([]byte, error) {
	ts := time.Now().Unix()
	auth := Authorization(secretID, secretKey, service, host, payload, ts)
	req, err := http.NewRequest(http.MethodPost, "https://"+host, strings.NewReader(payload))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json; charset=utf-8")
	req.Header.Set("Host", host)
	req.Header.Set("X-TC-Action", action)
	req.Header.Set("X-TC-Version", versionFor(service))
	req.Header.Set("X-TC-Timestamp", fmt.Sprintf("%d", ts))
	req.Header.Set("X-TC-Region", "ap-guangzhou")
	req.Header.Set("Authorization", auth)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	return io.ReadAll(resp.Body)
}
