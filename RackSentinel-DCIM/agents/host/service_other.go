//go:build !windows

package main

import (
	"os"

	"github.com/racksentinel/dcim/agents/host/internal/agent"
	"github.com/racksentinel/dcim/agents/host/internal/buffer"
)

func runService(cfgPath string) {
	cfg, err := loadConfig(cfgPath)
	if err != nil {
		os.Exit(1)
	}
	acfg := agent.Config{ServerURL: cfg.ServerURL, Token: cfg.Token, Interval: cfg.Interval, BufferFile: cfg.BufferFile}
	buf := buffer.New(cfg.BufferFile)
	stop := make(chan struct{})
	agent.Loop(acfg, collectMetrics, buf, stop)
}
