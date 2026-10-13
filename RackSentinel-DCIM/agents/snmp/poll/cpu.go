package poll

import (
	"fmt"

	"github.com/gosnmp/gosnmp"
)

const oidHrProcessorLoad = "1.3.6.1.2.1.25.3.3.1.2"

func PollMultiCPU(g *gosnmp.GoSNMP) (avg float64, perCPU map[int]float64) {
	perCPU = map[int]float64{}
	_ = g.Walk(oidHrProcessorLoad, func(pdu gosnmp.SnmpPDU) error {
		idx := lastIndex(pdu.Name)
		v := toFloat(pdu.Value)
		perCPU[idx] = v
		return nil
	})
	if len(perCPU) == 0 {
		return 0, perCPU
	}
	var sum float64
	for _, v := range perCPU {
		sum += v
	}
	return sum / float64(len(perCPU)), perCPU
}

func ApplyCPUMetrics(metrics map[string]interface{}, avg float64, per map[int]float64) {
	metrics["cpu"] = avg
	for idx, v := range per {
		metrics[fmt.Sprintf("cpu_%d", idx)] = v
	}
}
