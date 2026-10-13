package poll

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
)

type Profile struct {
	ID                 string          `json:"id"`
	Name               string          `json:"name"`
	Vendor             string          `json:"vendor"`
	Description        string          `json:"description"`
	SysObjectID        []string        `json:"sys_object_id_prefixes"`
	Metrics            []ProfileMetric `json:"metrics"`
	Alerts             []ProfileAlert  `json:"alerts"`
	Features           []string        `json:"features"`
	NeedsVerification  bool            `json:"needs_verification"`
}

type ProfileMetric struct {
	Key   string  `json:"key"`
	OID   string  `json:"oid"`
	Scale float64 `json:"scale"`
	Walk  bool    `json:"walk"`
}

type ProfileAlert struct {
	Key   string  `json:"key"`
	OID   string  `json:"oid"`
	Hi    float64 `json:"hi"`
	Level string  `json:"level"`
	Title string  `json:"title"`
}

func LoadProfiles(dir string) ([]Profile, error) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil, err
	}
	var out []Profile
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		raw, err := os.ReadFile(filepath.Join(dir, e.Name()))
		if err != nil {
			continue
		}
		var p Profile
		if json.Unmarshal(raw, &p) == nil && p.ID != "" {
			out = append(out, p)
		}
	}
	return out, nil
}

func MatchProfile(profiles []Profile, sysObjectID string) *Profile {
	for _, p := range profiles {
		for _, pref := range p.SysObjectID {
			if pref != "" && strings.HasPrefix(sysObjectID, pref) {
				return &p
			}
		}
	}
	return nil
}

func HasFeature(p *Profile, feat string) bool {
	if p == nil {
		return feat == "if_mib" || feat == "host_resources"
	}
	for _, f := range p.Features {
		if f == feat || f == "*" {
			return true
		}
	}
	return false
}
