package poll

import (
	"testing"
	"time"
)

func TestCounterRates(t *testing.T) {
	st := NewStateStore("")
	now := time.Now()
	st.Rates("dev1", 1, 1000, 2000, now)
	in, out := st.Rates("dev1", 1, 1000+1_000_000, 2000+2_000_000, now.Add(10*time.Second))
	if in <= 0 || out <= 0 {
		t.Fatalf("expected positive rates, got in=%v out=%v", in, out)
	}
}
