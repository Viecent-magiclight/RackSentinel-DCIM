package main

import (
	"runtime"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/host"
	"github.com/shirou/gopsutil/v3/load"
	"github.com/shirou/gopsutil/v3/mem"
	"github.com/shirou/gopsutil/v3/net"
)

func collectMetrics() (map[string]interface{}, map[string]interface{}) {
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
		"cpu": cpuVal, "mem": memVal, "disk": diskVal, "temp": 0,
		"net_in": netIn, "net_out": netOut, "power": 0,
	}
	if hostInfo != nil {
		metrics["hostname"] = hostInfo.Hostname
	}
	meta := map[string]interface{}{"os": runtime.GOOS, "arch": runtime.GOARCH}
	if hostInfo != nil {
		meta["platform"] = hostInfo.Platform
		meta["uptime_sec"] = hostInfo.Uptime
	}
	if loadStat != nil {
		meta["load1"] = loadStat.Load1
	}
	return metrics, meta
}
