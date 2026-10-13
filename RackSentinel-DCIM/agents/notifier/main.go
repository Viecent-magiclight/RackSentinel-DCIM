package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"os"
	"time"
)

type Config struct {
	ServerURL     string `json:"server_url"`
	Token         string `json:"token"`
	Interval      int    `json:"interval_sec"`
	SecretID      string `json:"secret_id"`
	SecretKey     string `json:"secret_key"`
	SmsSdkAppID   string `json:"sms_sdk_app_id"`
	SmsSign       string `json:"sms_sign"`
	SmsTemplateID string `json:"sms_template_id"`
	VmsSdkAppID   string `json:"vms_sdk_app_id"`
	VmsTemplateID string `json:"vms_template_id"`
	DedupeSec     int    `json:"dedupe_sec"`
	RatePerHour   int    `json:"rate_per_hour"`
	VoiceMode     string `json:"voice_mode"`
}

type alertRow struct {
	ID         int    `json:"id"`
	Level      string `json:"level"`
	Title      string `json:"title"`
	DeviceName string `json:"device_name"`
	CreatedAt  string `json:"created_at"`
}

type recipient struct {
	Phone       string `json:"phone"`
	SmsPhone    string `json:"sms_phone"`
	MinLevel    string `json:"min_level"`
	EscalateMin int    `json:"escalate_min"`
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
	if c.RatePerHour <= 0 {
		c.RatePerHour = 30
	}
	return c, nil
}

func main() {
	cfgPath := flag.String("config", "config.json", "config")
	test := flag.Bool("test", false, "test send to TEST_PHONE")
	flag.Parse()
	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	if *test {
		phone := os.Getenv("TEST_PHONE")
		if phone == "" {
			fmt.Fprintln(os.Stderr, "TEST_PHONE required")
			os.Exit(1)
		}
		params := []string{"RackSentinel", "测试", time.Now().Format("H:i")}
		runDispatch(cfg, nil, phone, params)
		return
	}
	sent := map[string]time.Time{}
	hourStart := time.Now()
	hourCount := 0
	for {
		if time.Since(hourStart) > time.Hour {
			hourStart = time.Now()
			hourCount = 0
		}
		alerts, rcpts := fetchPending(cfg)
		now := time.Now()
		for _, a := range alerts {
			key := fmt.Sprintf("%d:%s", a.ID, a.Title)
			if t, ok := sent[key]; ok && now.Sub(t) < time.Duration(cfg.DedupeSec)*time.Second {
				continue
			}
			for _, r := range rcpts {
				if !levelAllowed(a.Level, r.MinLevel) {
					continue
				}
				if r.EscalateMin > 0 && a.CreatedAt != "" {
					// escalation: only notify after N minutes since alert creation
					if t, err := time.Parse("2006-01-02 15:04:05", a.CreatedAt); err == nil {
						if now.Sub(t) < time.Duration(r.EscalateMin)*time.Minute {
							continue
						}
					}
				}
				if hourCount >= cfg.RatePerHour {
					break
				}
				params := []string{a.Title, a.DeviceName, a.Level}
				phone := r.Phone
				if phone == "" {
					phone = r.SmsPhone
				}
				if phone == "" {
					continue
				}
				runDispatch(cfg, &a, phone, params)
				if r.SmsPhone != "" && r.SmsPhone != phone {
					runDispatch(cfg, nil, r.SmsPhone, params)
				}
				hourCount++
			}
			sent[key] = now
		}
		time.Sleep(time.Duration(cfg.Interval) * time.Second)
	}
}

func fetchPending(cfg Config) ([]alertRow, []recipient) {
	return fetchAlerts(cfg), fetchRecipients(cfg)
}
