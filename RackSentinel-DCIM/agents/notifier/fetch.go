package main

import (
	"bytes"
	"encoding/json"
	"io"
	"net/http"
	"time"

	"github.com/racksentinel/dcim/agents/notifier/tencent"
)

func fetchAlerts(cfg Config) []alertRow {
	since := time.Now().Add(-24 * time.Hour).Format("2006-01-02 15:04:05")
	body := map[string]interface{}{"since": since}
	b, _ := json.Marshal(body)
	req, _ := http.NewRequest(http.MethodPost, cfg.ServerURL+"?r=alerts.pending", bytes.NewReader(b))
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)
	var out struct {
		Alerts []alertRow `json:"alerts"`
	}
	json.Unmarshal(raw, &out)
	return out.Alerts
}

func fetchRecipients(cfg Config) []recipient {
	req, _ := http.NewRequest(http.MethodGet, cfg.ServerURL+"?r=notify.recipients", nil)
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)
	var out struct {
		Recipients []recipient `json:"recipients"`
	}
	json.Unmarshal(raw, &out)
	return out.Recipients
}

func ackRemote(cfg Config, alertID int) {
	body, _ := json.Marshal(map[string]interface{}{"id": alertID})
	req, _ := http.NewRequest(http.MethodPost, cfg.ServerURL+"?r=alerts.ack_remote", bytes.NewReader(body))
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return
	}
	resp.Body.Close()
}

func runDispatch(cfg Config, alert *alertRow, phone string, params []string) {
	if cfg.SmsTemplateID != "" {
		body, _ := json.Marshal(map[string]interface{}{
			"PhoneNumberSet": []string{phone}, "SmsSdkAppId": cfg.SmsSdkAppID,
			"SignName": cfg.SmsSign, "TemplateId": cfg.SmsTemplateID, "TemplateParamSet": params,
		})
		_, _ = tencent.DoRequest(cfg.SecretID, cfg.SecretKey, "sms", "sms.tencentcloudapi.com", "SendSms", string(body))
	}
	if cfg.VmsTemplateID != "" {
		action := "SendTtsVoice"
		if cfg.VoiceMode == "code" {
			action = "SendCodeVoice"
		}
		body, _ := json.Marshal(map[string]interface{}{
			"VoiceSdkAppid": cfg.VmsSdkAppID, "TemplateId": cfg.VmsTemplateID,
			"CalledNumber": phone, "TemplateParamSet": params,
		})
		_, _ = tencent.DoRequest(cfg.SecretID, cfg.SecretKey, "vms", "vms.tencentcloudapi.com", action, string(body))
	}
	if alert != nil {
		ackRemote(cfg, alert.ID)
	}
}
