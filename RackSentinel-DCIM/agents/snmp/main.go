package main

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/gosnmp/gosnmp"
)

type Config struct {
	ServerURL string `json:"server_url"`
	Token     string `json:"token"`
	Interval  int    `json:"interval_sec"`
	Profiles  string `json:"profiles_dir"`
}

type Profile struct {
	ID          string            `json:"id"`
	Name        string            `json:"name"`
	Vendor      string            `json:"vendor"`
	SysObjectID []string          `json:"sys_object_id_prefixes"`
	Metrics     []ProfileMetric   `json:"metrics"`
	Alerts      []ProfileAlert    `json:"alerts"`
	NeedsVerify bool              `json:"needs_verification"`
}

type ProfileMetric struct {
	Key   string `json:"key"`
	OID   string `json:"oid"`
	Scale float64 `json:"scale"`
}

type ProfileAlert struct {
	Key      string  `json:"key"`
	OID      string  `json:"oid"`
	Hi       float64 `json:"hi"`
	Level    string  `json:"level"`
	Title    string  `json:"title"`
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
	if c.Interval <= 0 {
		c.Interval = 120
	}
	if c.Profiles == "" {
		c.Profiles = "profiles"
	}
	return c, nil
}

func apiGet(url string, token string) ([]byte, error) {
	req, _ := http.NewRequest(http.MethodGet, url, nil)
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	return io.ReadAll(resp.Body)
}

func apiPost(url string, token string, body map[string]interface{}) error {
	b, _ := json.Marshal(body)
	req, _ := http.NewRequest(http.MethodPost, url, bytes.NewReader(b))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 300 {
		slurp, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("%s", slurp)
	}
	return nil
}

func loadProfiles(dir string) ([]Profile, error) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil, err
	}
	var out []Profile
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		raw, _ := os.ReadFile(filepath.Join(dir, e.Name()))
		var p Profile
		if json.Unmarshal(raw, &p) == nil && p.ID != "" {
			out = append(out, p)
		}
	}
	return out, nil
}

func matchProfile(profiles []Profile, sysObjectID string) *Profile {
	for _, p := range profiles {
		for _, pref := range p.SysObjectID {
			if strings.HasPrefix(sysObjectID, pref) {
				return &p
			}
		}
	}
	return nil
}

func snmpClient(t map[string]interface{}) *gosnmp.GoSNMP {
	ver := fmt.Sprintf("%v", t["snmp_version"])
	host := fmt.Sprintf("%v", t["snmp_host"])
	port := uint16(161)
	if p, ok := t["snmp_port"].(float64); ok {
		port = uint16(p)
	}
	g := &gosnmp.GoSNMP{
		Target:    host,
		Port:      port,
		Timeout:   time.Duration(4) * time.Second,
		Retries:   1,
		MaxOids:   10,
	}
	switch ver {
	case "3":
		g.Version = gosnmp.Version3
		g.SecurityModel = gosnmp.UserSecurityModel
		g.MsgFlags = gosnmp.AuthPriv
		g.SecurityParameters = &gosnmp.UsmSecurityParameters{
			UserName:                 fmt.Sprintf("%v", t["snmp_v3_user"]),
			AuthenticationProtocol:   gosnmp.SHA,
			PrivacyProtocol:          gosnmp.AES,
			AuthenticationPassphrase: fmt.Sprintf("%v", t["snmp_v3_auth_pass"]),
			PrivacyPassphrase:        fmt.Sprintf("%v", t["snmp_v3_priv_pass"]),
		}
	default:
		g.Version = gosnmp.Version2c
		g.Community = fmt.Sprintf("%v", t["snmp_community"])
	}
	return g
}

func pollTarget(t map[string]interface{}, profiles []Profile) (map[string]interface{}, []map[string]interface{}, map[string]string) {
	metrics := map[string]interface{}{}
	alerts := []map[string]interface{}{}
	sys := map[string]string{}

	g := snmpClient(t)
	if err := g.Connect(); err != nil {
		return metrics, []map[string]interface{}{{
			"level": "warning", "title": "SNMP 连接失败", "detail": err.Error(),
		}}, sys
	}
	defer g.Conn.Close()

	stdOIDs := []string{
		"1.3.6.1.2.1.1.1.0", // sysDescr
		"1.3.6.1.2.1.1.2.0", // sysObjectID
		"1.3.6.1.2.1.1.5.0", // sysName
	}
	res, err := g.Get(stdOIDs)
	if err == nil {
		for _, v := range res.Variables {
			switch v.Name {
			case "1.3.6.1.2.1.1.1.0":
				sys["sysDescr"] = fmt.Sprintf("%v", v.Value)
			case "1.3.6.1.2.1.1.2.0":
				sys["sysObjectID"] = fmt.Sprintf("%v", v.Value)
			case "1.3.6.1.2.1.1.5.0":
				sys["sysName"] = fmt.Sprintf("%v", v.Value)
			}
		}
	}

	profileID := fmt.Sprintf("%v", t["snmp_profile"])
	var prof *Profile
	if profileID != "" {
		for _, p := range profiles {
			if p.ID == profileID {
				prof = &p
				break
			}
		}
	}
	if prof == nil && sys["sysObjectID"] != "" {
		prof = matchProfile(profiles, sys["sysObjectID"])
	}
	if prof == nil {
		prof = &Profile{
			Metrics: []ProfileMetric{
				{Key: "cpu", OID: "1.3.6.1.2.1.25.3.3.1.2.1", Scale: 1}, // hrProcessorLoad index 1 — needs_verification on multi-CPU
			},
		}
	}

	for _, m := range prof.Metrics {
		r, err := g.Get([]string{m.OID})
		if err != nil || len(r.Variables) == 0 {
			continue
		}
		val := toFloat(r.Variables[0].Value) * m.Scale
		metrics[m.Key] = val
	}
	for _, a := range prof.Alerts {
		r, err := g.Get([]string{a.OID})
		if err != nil || len(r.Variables) == 0 {
			continue
		}
		val := toFloat(r.Variables[0].Value)
		if val >= a.Hi {
			alerts = append(alerts, map[string]interface{}{
				"level": a.Level, "title": a.Title, "detail": fmt.Sprintf("%s=%.2f", a.Key, val),
			})
		}
	}
	return metrics, alerts, sys
}

func toFloat(v interface{}) float64 {
	switch x := v.(type) {
	case int:
		return float64(x)
	case uint:
		return float64(x)
	case int64:
		return float64(x)
	case uint64:
		return float64(x)
	case float64:
		return x
	case []byte:
		return 0
	default:
		return 0
	}
}

func main() {
	cfgPath := flag.String("config", "config.json", "config path")
	once := flag.Bool("once", false, "single poll")
	flag.Parse()
	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	profiles, _ := loadProfiles(cfg.Profiles)

	run := func() {
		raw, err := apiGet(cfg.ServerURL+"?r=snmp.targets", cfg.Token)
		if err != nil {
			fmt.Fprintln(os.Stderr, "targets:", err)
			return
		}
		var payload struct {
			Targets []map[string]interface{} `json:"targets"`
		}
		if json.Unmarshal(raw, &payload) != nil {
			return
		}
		for _, t := range payload.Targets {
			metrics, alerts, sys := pollTarget(t, profiles)
			body := map[string]interface{}{
				"device_id": t["id"],
				"metrics":   metrics,
				"alerts":    alerts,
				"sys":       sys,
			}
			if err := apiPost(cfg.ServerURL+"?r=snmp.report", cfg.Token, body); err != nil {
				fmt.Fprintln(os.Stderr, "report", t["id"], err)
			}
		}
	}
	run()
	if *once {
		return
	}
	for {
		time.Sleep(time.Duration(cfg.Interval) * time.Second)
		run()
	}
}
