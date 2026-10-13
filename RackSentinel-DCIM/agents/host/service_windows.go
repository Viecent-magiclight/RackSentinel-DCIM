//go:build windows

package main

import (
	"fmt"
	"os"
	"time"

	"github.com/racksentinel/dcim/agents/host/internal/agent"
	"github.com/racksentinel/dcim/agents/host/internal/buffer"
	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/debug"
)

var elog debug.Log = debug.New("RackSentinelHost")

type rackService struct {
	cfgPath string
}

func (m *rackService) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (ssec bool, errno uint32) {
	changes <- svc.Status{State: svc.StartPending}
	cfg, err := loadConfig(m.cfgPath)
	if err != nil {
		elog.Error(1, err.Error())
		return true, 1
	}
	acfg := agent.Config{ServerURL: cfg.ServerURL, Token: cfg.Token, Interval: cfg.Interval, BufferFile: cfg.BufferFile}
	buf := buffer.New(cfg.BufferFile)
	stop := make(chan struct{})
	go agent.Loop(acfg, collectMetrics, buf, stop)
	changes <- svc.Status{State: svc.Running, Accepts: svc.AcceptStop | svc.AcceptShutdown}
	for {
		select {
		case c := <-r:
			switch c.Cmd {
			case svc.Interrogate:
				changes <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				close(stop)
				changes <- svc.Status{State: svc.StopPending}
				time.Sleep(500 * time.Millisecond)
				return false, 0
			default:
				elog.Error(1, fmt.Sprintf("unexpected control %d", c))
			}
		}
	}
}

func runService(cfgPath string) {
	isIntSess, err := svc.IsAnInteractiveSession()
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	if isIntSess {
		cfg, err := loadConfig(cfgPath)
		if err != nil {
			os.Exit(1)
		}
		acfg := agent.Config{ServerURL: cfg.ServerURL, Token: cfg.Token, Interval: cfg.Interval, BufferFile: cfg.BufferFile}
		buf := buffer.New(cfg.BufferFile)
		stop := make(chan struct{})
		agent.Loop(acfg, collectMetrics, buf, stop)
		return
	}
	_ = svc.Run("RackSentinelHost", &rackService{cfgPath: cfgPath})
}
