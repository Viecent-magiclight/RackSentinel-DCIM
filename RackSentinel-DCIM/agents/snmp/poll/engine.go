package poll

import (
	"fmt"
	"strings"

	"github.com/gosnmp/gosnmp"
)

type Result struct {
	Metrics   map[string]interface{}
	Alerts    []map[string]interface{}
	Sys       map[string]string
	Interfaces []Iface
	Sensors   []Sensor
	ProfileID string
}

func PollTarget(t Target, profiles []Profile, st *StateStore) Result {
	res := Result{
		Metrics: map[string]interface{}{},
		Alerts:  []map[string]interface{}{},
		Sys:     map[string]string{},
	}
	g := NewClient(t)
	if err := g.Connect(); err != nil {
		res.Alerts = []map[string]interface{}{{
			"level": "warning", "title": "SNMP 连接失败", "detail": err.Error(),
		}}
		return res
	}
	defer g.Conn.Close()

	stdOIDs := []string{
		"1.3.6.1.2.1.1.1.0",
		"1.3.6.1.2.1.1.2.0",
		"1.3.6.1.2.1.1.5.0",
	}
	if pkt, err := g.Get(stdOIDs); err == nil {
		for _, v := range pkt.Variables {
			oid := strings.TrimPrefix(v.Name, ".")
			switch {
			case strings.HasSuffix(oid, "1.1.1.0"):
				res.Sys["sysDescr"] = fmt.Sprintf("%v", v.Value)
			case strings.HasSuffix(oid, "1.1.2.0"):
				res.Sys["sysObjectID"] = fmt.Sprintf("%v", v.Value)
			case strings.HasSuffix(oid, "1.1.5.0"):
				res.Sys["sysName"] = fmt.Sprintf("%v", v.Value)
			}
		}
	}

	var prof *Profile
	if t.ProfileID != "" && t.ProfileID != "<nil>" {
		for _, p := range profiles {
			if p.ID == t.ProfileID {
				prof = &p
				break
			}
		}
	}
	if prof == nil && res.Sys["sysObjectID"] != "" {
		prof = MatchProfile(profiles, res.Sys["sysObjectID"])
	}
	if prof != nil {
		res.ProfileID = prof.ID
	}

	collectProfileMetrics(g, prof, res.Metrics, &res.Alerts)

	if prof == nil || HasFeature(prof, "host_resources") || HasFeature(prof, "multi_cpu") {
		avg, _ := PollMultiCPU(g)
		if avg > 0 {
			ApplyCPUMetrics(res.Metrics, avg, nil)
		}
	}
	if prof == nil || HasFeature(prof, "if_mib") {
		ifaces, err := WalkIFTable(g, t.ID, st)
		if err == nil {
			res.Interfaces = ifaces
		}
	}
	if prof != nil && HasFeature(prof, "entity_sensor") {
		res.Sensors = append(res.Sensors, WalkEntitySensors(g)...)
	}
	if prof != nil && HasFeature(prof, "ups_mib") {
		res.Sensors = append(res.Sensors, PollUPS(g)...)
	}
	return res
}

func collectProfileMetrics(g *gosnmp.GoSNMP, prof *Profile, metrics map[string]interface{}, alerts *[]map[string]interface{}) {
	if prof == nil {
		return
	}
	for _, m := range prof.Metrics {
		if m.Walk {
			vals := walkColumnUint(g, m.OID)
			var sum float64
			for _, v := range vals {
				sum += float64(v) * m.Scale
			}
			if len(vals) > 0 {
				metrics[m.Key] = sum / float64(len(vals))
			}
			continue
		}
		r, err := g.Get([]string{m.OID})
		if err != nil || len(r.Variables) == 0 {
			continue
		}
		metrics[m.Key] = toFloat(r.Variables[0].Value) * m.Scale
	}
	for _, a := range prof.Alerts {
		r, err := g.Get([]string{a.OID})
		if err != nil || len(r.Variables) == 0 {
			continue
		}
		val := toFloat(r.Variables[0].Value)
		if val >= a.Hi {
			*alerts = append(*alerts, map[string]interface{}{
				"level": a.Level, "title": a.Title, "detail": fmt.Sprintf("%s=%.2f", a.Key, val),
			})
		}
	}
}

// Probe performs a quick connectivity check.
func Probe(t Target, profiles []Profile) (map[string]interface{}, error) {
	r := PollTarget(t, profiles, NewStateStore(""))
	out := map[string]interface{}{
		"ok":         len(r.Alerts) == 0 || r.Sys["sysName"] != "",
		"sys":        r.Sys,
		"profile_id": r.ProfileID,
		"metrics":    r.Metrics,
	}
	if len(r.Alerts) > 0 && r.Sys["sysName"] == "" {
		out["ok"] = false
		out["error"] = r.Alerts[0]["detail"]
	}
	return out, nil
}
