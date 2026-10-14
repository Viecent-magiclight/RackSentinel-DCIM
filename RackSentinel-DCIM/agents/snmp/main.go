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
	"time"

	"github.com/racksentinel/dcim/agents/snmp/poll"
)

type Config struct {
	ServerURL string `json:"server_url"`
	Token     string `json:"token"`
	Interval  int    `json:"interval_sec"`
	Profiles  string `json:"profiles_dir"`
	StateFile string `json:"state_file"`
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
	if c.StateFile == "" {
		c.StateFile = "snmp-state.json"
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

func main() {
	cfgPath := flag.String("config", "config.json", "config path")
	once := flag.Bool("once", false, "single poll")
	probe := flag.Bool("probe", false, "SNMP probe (JSON stdout)")
	host := flag.String("host", "", "probe host")
	community := flag.String("community", "public", "probe community")
	version := flag.String("version", "2c", "probe version")
	port := flag.Int("port", 161, "probe port")
	flag.Parse()

	if *probe {
		profDir := "profiles"
		if exe, err := os.Executable(); err == nil {
			cand := filepath.Join(filepath.Dir(exe), "profiles")
			if _, err := os.Stat(cand); err == nil {
				profDir = cand
			}
		}
		profiles, _ := poll.LoadProfiles(profDir)
		t := poll.Target{Host: *host, Port: uint16(*port), SNMPVersion: *version, Community: *community, ID: "probe"}
		out, err := poll.Probe(t, profiles)
		if err != nil {
			fmt.Fprintf(os.Stderr, "%v\n", err)
			os.Exit(1)
		}
		json.NewEncoder(os.Stdout).Encode(out)
		return
	}

	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	profDir := cfg.Profiles
	if !filepath.IsAbs(profDir) {
		profDir = filepath.Join(filepath.Dir(*cfgPath), profDir)
	}
	profiles, _ := poll.LoadProfiles(profDir)
	st := poll.NewStateStore(cfg.StateFile)

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
		for _, tm := range payload.Targets {
			t := poll.TargetFromMap(tm)
			r := poll.PollTarget(t, profiles, st)
			body := map[string]interface{}{
				"device_id":  tm["id"],
				"metrics":    r.Metrics,
				"alerts":     r.Alerts,
				"sys":        r.Sys,
				"interfaces": r.Interfaces,
				"sensors":    r.Sensors,
				"profile_id": r.ProfileID,
			}
			if err := apiPost(cfg.ServerURL+"?r=snmp.report", cfg.Token, body); err != nil {
				fmt.Fprintln(os.Stderr, "report", tm["id"], err)
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
