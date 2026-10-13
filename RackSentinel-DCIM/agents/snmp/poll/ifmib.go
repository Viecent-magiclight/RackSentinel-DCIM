package poll

import (
	"fmt"
	"time"

	"github.com/gosnmp/gosnmp"
)

const (
	oidIfDescr     = "1.3.6.1.2.1.2.2.1.2"
	oidIfAdmin     = "1.3.6.1.2.1.2.2.1.7"
	oidIfOper      = "1.3.6.1.2.1.2.2.1.8"
	oidIfInErrors  = "1.3.6.1.2.1.2.2.1.14"
	oidIfOutErrors = "1.3.6.1.2.1.2.2.1.20"
	oidIfHCIn      = "1.3.6.1.2.1.31.1.1.1.6"
	oidIfHCOut     = "1.3.6.1.2.1.31.1.1.1.10"
	oidIfHighSpeed = "1.3.6.1.2.1.31.1.1.1.15"
	oidIfAlias     = "1.3.6.1.2.1.31.1.1.1.18"
)

type Iface struct {
	IfIndex      int     `json:"if_index"`
	Name         string  `json:"name"`
	Alias        string  `json:"alias"`
	AdminStatus  string  `json:"admin_status"`
	OperStatus   string  `json:"oper_status"`
	SpeedMbps    int     `json:"speed_mbps"`
	InOctets     string  `json:"in_octets"`
	OutOctets    string  `json:"out_octets"`
	InErrors     int     `json:"in_errors"`
	OutErrors    int     `json:"out_errors"`
	InRateMbps   float64 `json:"in_rate_mbps"`
	OutRateMbps  float64 `json:"out_rate_mbps"`
}

func WalkIFTable(g *gosnmp.GoSNMP, deviceKey string, st *StateStore) ([]Iface, error) {
	now := time.Now()
	descr := walkColumn(g, oidIfDescr)
	admin := walkColumn(g, oidIfAdmin)
	oper := walkColumn(g, oidIfOper)
	inErr := walkColumnUint(g, oidIfInErrors)
	outErr := walkColumnUint(g, oidIfOutErrors)
	hcIn := walkColumn(g, oidIfHCIn)
	hcOut := walkColumn(g, oidIfHCOut)
	speed := walkColumn(g, oidIfHighSpeed)
	alias := walkColumn(g, oidIfAlias)

	var out []Iface
	for idx, name := range descr {
		inOct := toUint64(hcIn[idx])
		outOct := toUint64(hcOut[idx])
		inR, outR := st.Rates(deviceKey, idx, inOct, outOct, now)
		out = append(out, Iface{
			IfIndex:     idx,
			Name:        name,
			Alias:       alias[idx],
			AdminStatus: ifStatusLabel(admin[idx]),
			OperStatus:  ifStatusLabel(oper[idx]),
			SpeedMbps:   int(toUint64(speed[idx])),
			InOctets:    fmt.Sprintf("%d", inOct),
			OutOctets:   fmt.Sprintf("%d", outOct),
			InErrors:    int(inErr[idx]),
			OutErrors:   int(outErr[idx]),
			InRateMbps:  inR,
			OutRateMbps: outR,
		})
	}
	st.Save()
	return out, nil
}

func walkColumn(g *gosnmp.GoSNMP, baseOID string) map[int]string {
	out := map[int]string{}
	_ = g.Walk(baseOID, func(pdu gosnmp.SnmpPDU) error {
		idx := lastIndex(pdu.Name)
		out[idx] = fmt.Sprintf("%v", pdu.Value)
		return nil
	})
	return out
}

func walkColumnUint(g *gosnmp.GoSNMP, baseOID string) map[int]uint64 {
	out := map[int]uint64{}
	_ = g.Walk(baseOID, func(pdu gosnmp.SnmpPDU) error {
		idx := lastIndex(pdu.Name)
		out[idx] = toUint64(pdu.Value)
		return nil
	})
	return out
}

func lastIndex(oid string) int {
	parts := splitOID(oid)
	if len(parts) == 0 {
		return 0
	}
	var n int
	fmt.Sscanf(parts[len(parts)-1], "%d", &n)
	return n
}

func splitOID(oid string) []string {
	if len(oid) > 0 && oid[0] == '.' {
		oid = oid[1:]
	}
	var parts []string
	cur := ""
	for _, c := range oid {
		if c == '.' {
			if cur != "" {
				parts = append(parts, cur)
				cur = ""
			}
			continue
		}
		cur += string(c)
	}
	if cur != "" {
		parts = append(parts, cur)
	}
	return parts
}

func ifStatusLabel(v string) string {
	switch v {
	case "1":
		return "up"
	case "2":
		return "down"
	case "3":
		return "testing"
	default:
		return v
	}
}
