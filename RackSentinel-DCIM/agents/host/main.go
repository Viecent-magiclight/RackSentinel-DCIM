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
	"runtime"
	"time"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/host"
	"github.com/shirou/gopsutil/v3/load"
	"github.com/shirou/gopsutil/v3/mem"
	"github.com/shirou/gopsutil/v3/net"
)

type Config struct {
	ServerURL string `json:"server_url"`
	Token     string `json:"token"`
	Interval  int    `json:"interval_sec"`
}

type bufferItem struct {
	Route string
	Body  map[string]interface{}
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
	return c, nil
}

func postJSON(url string, token string, body map[string]interface{}) error {
	b, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(b))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 300 {
		slurp, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("HTTP %d: %s", resp.StatusCode, string(slurp))
	}
	return nil
}

func collect() (map[string]interface{}, map[string]interface{}) {
	cpuPct, _ := cpu.Percent(0, false)
	memStat, _ := mem.VirtualMemory()
	diskStat, _ := disk.Usage("/")
	hostInfo, _ := host.Info()
	loadStat, _ := load.Avg()
	netIO, _ := net.IOCounters(false)

	cpuVal := 0.0
	if len(cpuPct) > 0 {
		cpuVal = cpuPct[0]
	}
	memVal := 0.0
	if memStat != nil {
		memVal = memStat.UsedPercent
	}
	diskVal := 0.0
	if diskStat != nil {
		diskVal = diskStat.UsedPercent
	}
	var netIn, netOut float64
	if len(netIO) > 0 {
		netIn = float64(netIO[0].BytesRecv) / 1024 / 1024
		netOut = float64(netIO[0].BytesSent) / 1024 / 1024
	}

	metrics := map[string]interface{}{
		"cpu":     cpuVal,
		"mem":     memVal,
		"disk":    diskVal,
		"temp":    0,
		"net_in":  netIn,
		"net_out": netOut,
		"power":   0,
	}
	if hostInfo != nil {
		metrics["hostname"] = hostInfo.Hostname
	}
	meta := map[string]interface{}{
		"os":         runtime.GOOS,
		"arch":       runtime.GOARCH,
		"uptime_sec": 0,
	}
	if hostInfo != nil {
		meta["platform"] = hostInfo.Platform
		meta["uptime_sec"] = hostInfo.Uptime
	}
	if loadStat != nil {
		meta["load1"] = loadStat.Load1
	}
	return metrics, meta
}

func flushBuffer(cfg Config, buf []bufferItem) []bufferItem {
	var left []bufferItem
	for _, it := range buf {
		url := fmt.Sprintf("%s?r=%s", cfg.ServerURL, it.Route)
		if err := postJSON(url, cfg.Token, it.Body); err != nil {
			left = append(left, it)
		}
	}
	return left
}

func main() {
	cfgPath := flag.String("config", "config.json", "config file path")
	once := flag.Bool("once", false, "run one cycle and exit")
	flag.Parse()

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

	var pending []bufferItem
	tick := func() {
		metrics, meta := collect()
		pending = append(pending, bufferItem{
			Route: "heartbeat",
			Body:  map[string]interface{}{"status": "online", "meta": meta},
		})
		pending = append(pending, bufferItem{
			Route: "metrics",
			Body:  map[string]interface{}{"metrics": metrics},
		})
		pending = flushBuffer(cfg, pending)
	}

	tick()
	if *once {
		return
	}
	for {
		time.Sleep(time.Duration(cfg.Interval) * time.Second)
		tick()
	}
}
