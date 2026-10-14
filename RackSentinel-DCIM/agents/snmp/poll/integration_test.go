//go:build integration

package poll_test

import (
	"os"
	"testing"
	"time"

	"github.com/racksentinel/dcim/agents/snmp/poll"
)

func TestPollAgainstSnmpSim(t *testing.T) {
	host := os.Getenv("SNMPSIM_HOST")
	if host == "" {
		host = "127.0.0.1"
	}
	port := uint16(1161)
	tgt := poll.Target{
		ID: "1", Host: host, Port: port, SNMPVersion: "2c", Community: "demo",
	}
	st := poll.NewStateStore("")
	r := poll.PollTarget(tgt, nil, st)
	if r.Sys["sysName"] == "" {
		t.Fatalf("expected sysName from sim, got %+v", r.Sys)
	}
	if len(r.Interfaces) == 0 {
		t.Fatal("expected IF-MIB data")
	}
	time.Sleep(2 * time.Second)
	r2 := poll.PollTarget(tgt, nil, st)
	if len(r2.Interfaces) > 0 && r2.Interfaces[0].InRateMbps < 0 {
		t.Fatal("bad rate")
	}
}
