package poll

import (
	"fmt"

	"github.com/gosnmp/gosnmp"
)

// ENTITY-SENSOR-MIB entPhySensorValue (1.3.6.1.2.1.99.1.1.1.4)
const oidEntPhySensorValue = "1.3.6.1.2.1.99.1.1.1.4"
const oidEntPhySensorType = "1.3.6.1.2.1.99.1.1.1.1"

// UPS-MIB scalars (RFC 1628)
var upsOIDs = map[string]string{
	"ups_battery_status":    "1.3.6.1.2.1.33.1.2.1.0",
	"ups_seconds_on_battery": "1.3.6.1.2.1.33.1.2.2.0",
	"ups_est_min_remaining": "1.3.6.1.2.1.33.1.2.3.0",
	"ups_out_voltage":       "1.3.6.1.2.1.33.1.4.1.0",
}

type Sensor struct {
	SensorKey string  `json:"sensor_key"`
	Label     string  `json:"label"`
	Kind      string  `json:"kind"`
	Value     float64 `json:"value"`
	Unit      string  `json:"unit"`
	Status    string  `json:"status"`
}

func WalkEntitySensors(g *gosnmp.GoSNMP) []Sensor {
	types := walkColumnUint(g, oidEntPhySensorType)
	vals := walkColumnUint(g, oidEntPhySensorValue)
	var out []Sensor
	for idx, val := range vals {
		kind := sensorKind(types[idx])
		out = append(out, Sensor{
			SensorKey: fmt.Sprintf("ent-%d", idx),
			Label:     fmt.Sprintf("ENTITY sensor %d", idx),
			Kind:      kind,
			Value:     float64(val),
			Unit:      sensorUnit(kind),
			Status:    "normal",
		})
	}
	return out
}

func PollUPS(g *gosnmp.GoSNMP) []Sensor {
	var out []Sensor
	for key, oid := range upsOIDs {
		r, err := g.Get([]string{oid})
		if err != nil || len(r.Variables) == 0 {
			continue
		}
		out = append(out, Sensor{
			SensorKey: "ups-" + key,
			Label:     key,
			Kind:      "ups",
			Value:     toFloat(r.Variables[0].Value),
			Unit:      upsUnit(key),
			Status:    "normal",
		})
	}
	return out
}

func sensorKind(t uint64) string {
	switch t {
	case 8:
		return "temp"
	case 13:
		return "power"
	case 10:
		return "fan"
	default:
		return "other"
	}
}

func sensorUnit(kind string) string {
	switch kind {
	case "temp":
		return "C"
	case "power":
		return "W"
	default:
		return ""
	}
}

func upsUnit(key string) string {
	if key == "ups_out_voltage" {
		return "V"
	}
	if key == "ups_est_min_remaining" || key == "ups_seconds_on_battery" {
		return "s"
	}
	return ""
}
