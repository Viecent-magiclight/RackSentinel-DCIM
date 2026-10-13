package poll

import (
	"fmt"
	"time"

	"github.com/gosnmp/gosnmp"
)

type Target struct {
	ID            string
	SNMPVersion   string
	Host          string
	Port          uint16
	Community     string
	V3User        string
	V3AuthPass    string
	V3PrivPass    string
	ProfileID     string
}

func TargetFromMap(t map[string]interface{}) Target {
	port := uint16(161)
	if p, ok := t["snmp_port"].(float64); ok {
		port = uint16(p)
	}
	return Target{
		ID:          fmt.Sprintf("%v", t["id"]),
		SNMPVersion: fmt.Sprintf("%v", t["snmp_version"]),
		Host:        fmt.Sprintf("%v", t["snmp_host"]),
		Port:        port,
		Community:   fmt.Sprintf("%v", t["snmp_community"]),
		V3User:      fmt.Sprintf("%v", t["snmp_v3_user"]),
		V3AuthPass:  fmt.Sprintf("%v", t["snmp_v3_auth_pass"]),
		V3PrivPass:  fmt.Sprintf("%v", t["snmp_v3_priv_pass"]),
		ProfileID:   fmt.Sprintf("%v", t["snmp_profile"]),
	}
}

func NewClient(t Target) *gosnmp.GoSNMP {
	g := &gosnmp.GoSNMP{
		Target:  t.Host,
		Port:    t.Port,
		Timeout: 4 * time.Second,
		Retries: 1,
		MaxOids: 60,
	}
	switch t.SNMPVersion {
	case "3":
		g.Version = gosnmp.Version3
		g.SecurityModel = gosnmp.UserSecurityModel
		g.MsgFlags = gosnmp.AuthPriv
		g.SecurityParameters = &gosnmp.UsmSecurityParameters{
			UserName:                 t.V3User,
			AuthenticationProtocol:   gosnmp.SHA,
			PrivacyProtocol:          gosnmp.AES,
			AuthenticationPassphrase: t.V3AuthPass,
			PrivacyPassphrase:        t.V3PrivPass,
		}
	default:
		g.Version = gosnmp.Version2c
		g.Community = t.Community
	}
	return g
}
