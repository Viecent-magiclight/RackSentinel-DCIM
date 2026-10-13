package agent

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"

	"github.com/racksentinel/dcim/agents/host/internal/buffer"
)

type Config struct {
	ServerURL  string
	Token      string
	Interval   int
	BufferFile string
}

type Collector func() (map[string]interface{}, map[string]interface{})

func RunOnce(cfg Config, collect Collector, buf *buffer.FileBuffer) {
	metrics, meta := collect()
	items := []buffer.Item{
		{Route: "heartbeat", Body: map[string]interface{}{"status": "online", "meta": meta}},
		{Route: "metrics", Body: map[string]interface{}{"metrics": metrics}},
	}
	pending := buf.Load()
	pending = append(pending, items...)
	var left []buffer.Item
	for _, it := range pending {
		url := fmt.Sprintf("%s?r=%s", cfg.ServerURL, it.Route)
		if err := postJSON(url, cfg.Token, it.Body); err != nil {
			left = append(left, it)
		}
	}
	buf.Save(left)
}

func Loop(cfg Config, collect Collector, buf *buffer.FileBuffer, stop <-chan struct{}) {
	RunOnce(cfg, collect, buf)
	tick := time.NewTicker(time.Duration(cfg.Interval) * time.Second)
	defer tick.Stop()
	for {
		select {
		case <-stop:
			return
		case <-tick.C:
			RunOnce(cfg, collect, buf)
		}
	}
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
