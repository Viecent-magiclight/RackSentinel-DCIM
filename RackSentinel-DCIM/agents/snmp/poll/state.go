package poll

import (
	"encoding/json"
	"os"
	"sync"
	"time"
)

type CounterSnap struct {
	InOctets  uint64
	OutOctets uint64
	At        time.Time
}

type StateStore struct {
	path string
	mu   sync.Mutex
	data map[string]map[int]CounterSnap // deviceKey -> ifIndex
}

func NewStateStore(path string) *StateStore {
	s := &StateStore{path: path, data: map[string]map[int]CounterSnap{}}
	if path != "" {
		raw, err := os.ReadFile(path)
		if err == nil {
			json.Unmarshal(raw, &s.data)
		}
	}
	return s
}

func (s *StateStore) Rates(deviceKey string, ifIndex int, inOct, outOct uint64, now time.Time) (float64, float64) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.data[deviceKey] == nil {
		s.data[deviceKey] = map[int]CounterSnap{}
	}
	prev := s.data[deviceKey][ifIndex]
	s.data[deviceKey][ifIndex] = CounterSnap{InOctets: inOct, OutOctets: outOct, At: now}
	if prev.At.IsZero() || now.Sub(prev.At) <= 0 {
		return 0, 0
	}
	sec := now.Sub(prev.At).Seconds()
	if sec < 1 {
		return 0, 0
	}
	inRate := float64(inOct-prev.InOctets) * 8 / sec / 1e6
	outRate := float64(outOct-prev.OutOctets) * 8 / sec / 1e6
	if inRate < 0 {
		inRate = 0
	}
	if outRate < 0 {
		outRate = 0
	}
	return inRate, outRate
}

func (s *StateStore) Save() {
	if s.path == "" {
		return
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	raw, _ := json.Marshal(s.data)
	_ = os.WriteFile(s.path, raw, 0600)
}
