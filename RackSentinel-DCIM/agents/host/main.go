package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"os"
	"path/filepath"

	"github.com/racksentinel/dcim/agents/host/internal/agent"
	"github.com/racksentinel/dcim/agents/host/internal/buffer"
)

type Config struct {
	ServerURL  string `json:"server_url"`
	Token      string `json:"token"`
	Interval   int    `json:"interval_sec"`
	BufferFile string `json:"buffer_file"`
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
	if c.ServerURL == "" {
		c.ServerURL = os.Getenv("RACK_INGEST_URL")
	}
	if c.Token == "" {
		c.Token = os.Getenv("RACK_AGENT_TOKEN")
	}
	if c.Interval <= 0 {
		c.Interval = 60
	}
	if c.BufferFile == "" {
		c.BufferFile = "buffer.json"
	}
	return c, nil
}

func main() {
	cfgPath := flag.String("config", "config.json", "config file path")
	once := flag.Bool("once", false, "run one cycle and exit")
	serviceMode := flag.Bool("service", false, "run as OS service (Windows)")
	flag.Parse()

	if *serviceMode {
		runService(*cfgPath)
		return
	}

	exe, _ := os.Executable()
	def := filepath.Join(filepath.Dir(exe), "config.json")
	if *cfgPath == "config.json" && os.Getenv("RACK_AGENT_CONFIG") == "" {
		if _, err := os.Stat(def); err == nil {
			*cfgPath = def
		}
	}
	if env := os.Getenv("RACK_AGENT_CONFIG"); env != "" {
		*cfgPath = env
	}

	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		fmt.Fprintf(os.Stderr, "config: %v\n", err)
		os.Exit(1)
	}
	if cfg.ServerURL == "" || cfg.Token == "" {
		fmt.Fprintln(os.Stderr, "server_url and token are required")
		os.Exit(1)
	}

	acfg := agent.Config{
		ServerURL:  cfg.ServerURL,
		Token:      cfg.Token,
		Interval:   cfg.Interval,
		BufferFile: cfg.BufferFile,
	}
	buf := buffer.New(cfg.BufferFile)
	if *once {
		agent.RunOnce(acfg, collectMetrics, buf)
		return
	}
	stop := make(chan struct{})
	agent.Loop(acfg, collectMetrics, buf, stop)
}
