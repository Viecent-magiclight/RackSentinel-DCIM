package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net/http"
	"os"
	"time"
)

type Config struct {
	ServerURL       string `json:"server_url"`
	Token           string `json:"token"`
	Interval        int    `json:"interval_sec"`
	SecretID        string `json:"secret_id"`
	SecretKey       string `json:"secret_key"`
	SmsSdkAppID     string `json:"sms_sdk_app_id"`
	SmsSign         string `json:"sms_sign"`
	SmsTemplateID   string `json:"sms_template_id"`
	VmsSdkAppID     string `json:"vms_sdk_app_id"`
	VmsTemplateID   string `json:"vms_template_id"`
	DedupeSec       int    `json:"dedupe_sec"`
}

type alertRow struct {
	ID          int    `json:"id"`
	Level       string `json:"level"`
	Title       string `json:"title"`
	Detail      string `json:"detail"`
	DeviceName  string `json:"device_name"`
	RackName    string `json:"rack_name"`
	CreatedAt   string `json:"created_at"`
}

func loadConfig(path string) (Config, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return Config{}, err
	}
	var c Config
	if err := json.Unmarshal(raw, &c); err != nil {
		return Config{}, err
	}
	if c.SecretID == "" {
		c.SecretID = os.Getenv("TENCENT_SECRET_ID")
	}
	if c.SecretKey == "" {
		c.SecretKey = os.Getenv("TENCENT_SECRET_KEY")
	}
	if c.Interval <= 0 {
		c.Interval = 30
	}
	if c.DedupeSec <= 0 {
		c.DedupeSec = 600
	}
	return c, nil
}

type recipient struct {
	Name     string `json:"name"`
	Phone    string `json:"phone"`
	SmsPhone string `json:"sms_phone"`
	MinLevel string `json:"min_level"`
}

func fetchRecipients(cfg Config) ([]recipient, error) {
	req, _ := http.NewRequest(http.MethodGet, cfg.ServerURL+"?r=notify.recipients", nil)
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)
	var out struct {
		Recipients []recipient `json:"recipients"`
	}
	if err := json.Unmarshal(raw, &out); err != nil {
		return nil, err
	}
	return out.Recipients, nil
}

func fetchAlerts(cfg Config) ([]alertRow, error) {
	since := time.Now().Add(-24 * time.Hour).Format("2006-01-02 15:04:05")
	body := map[string]interface{}{"since": since}
	b, _ := json.Marshal(body)
	req, _ := http.NewRequest(http.MethodPost, cfg.ServerURL+"?r=alerts.pending", bytesReader(b))
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)
	var out struct {
		Alerts []alertRow `json:"alerts"`
	}
	if err := json.Unmarshal(raw, &out); err != nil {
		return nil, err
	}
	return out.Alerts, nil
}

func bytesReader(b []byte) *reader { return &reader{b: b} }

type reader struct{ b []byte; i int }
func (r *reader) Read(p []byte) (int, error) {
	if r.i >= len(r.b) { return 0, io.EOF }
	n := copy(p, r.b[r.i:])
	r.i += n
	return n, nil
}

func main() {
	cfgPath := flag.String("config", "config.json", "config")
	test := flag.Bool("test", false, "send test SMS/VMS to TEST_PHONE env")
	flag.Parse()
	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	if *test {
		phone := os.Getenv("TEST_PHONE")
		if phone == "" || cfg.SecretID == "" || cfg.SecretKey == "" {
			fmt.Fprintln(os.Stderr, "need TEST_PHONE and Tencent credentials")
			os.Exit(1)
		}
		msg := []string{"RackSentinel", "测试告警", time.Now().Format("15:04")}
		if cfg.SmsTemplateID != "" {
			_ = sendSMS(cfg.SecretID, cfg.SecretKey, cfg.SmsSdkAppID, cfg.SmsSign, cfg.SmsTemplateID, phone, msg)
		}
		if cfg.VmsTemplateID != "" {
			_ = sendVMS(cfg.SecretID, cfg.SecretKey, cfg.VmsSdkAppID, cfg.VmsTemplateID, phone, msg)
		}
		fmt.Println("test dispatch attempted")
		return
	}

	seen := map[string]time.Time{}
	for {
		alerts, err := fetchAlerts(cfg)
		if err != nil {
			fmt.Fprintln(os.Stderr, err)
			time.Sleep(time.Duration(cfg.Interval) * time.Second)
			continue
		}
		rcpts, _ := fetchRecipients(cfg)
		for _, a := range alerts {
			key := fmt.Sprintf("%d:%s", a.ID, a.Title)
			if t, ok := seen[key]; ok && time.Since(t) < time.Duration(cfg.DedupeSec)*time.Second {
				continue
			}
			seen[key] = time.Now()
			params := []string{a.Title, a.DeviceName, a.Level}
			if cfg.SecretID == "" || cfg.SecretKey == "" {
				continue
			}
			for _, r := range rcpts {
				phone := r.Phone
				sms := r.SmsPhone
				if phone != "" && cfg.VmsTemplateID != "" {
					if err := sendVMS(cfg.SecretID, cfg.SecretKey, cfg.VmsSdkAppID, cfg.VmsTemplateID, phone, params); err != nil {
						fmt.Fprintln(os.Stderr, "vms:", err)
					}
				}
				if sms != "" && cfg.SmsTemplateID != "" {
					if err := sendSMS(cfg.SecretID, cfg.SecretKey, cfg.SmsSdkAppID, cfg.SmsSign, cfg.SmsTemplateID, sms, params); err != nil {
						fmt.Fprintln(os.Stderr, "sms:", err)
					}
				}
			}
		}
		time.Sleep(time.Duration(cfg.Interval) * time.Second)
	}
}
