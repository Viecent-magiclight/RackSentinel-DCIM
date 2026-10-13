package agent

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/racksentinel/dcim/agents/host/internal/buffer"
)

func TestBufferPersistsOnFailure(t *testing.T) {
	dir := t.TempDir()
	buf := buffer.New(filepath.Join(dir, "q.json"))
	buf.Append([]buffer.Item{{Route: "metrics", Body: map[string]interface{}{"x": 1}}})
	if len(buf.Load()) != 1 {
		t.Fatal("expected buffered item")
	}
	buf.Save(nil)
	if _, err := os.Stat(filepath.Join(dir, "q.json")); err == nil {
		t.Fatal("expected file removed")
	}
}
