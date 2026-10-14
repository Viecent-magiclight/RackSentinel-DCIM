package buffer

import (
	"encoding/json"
	"os"
	"sync"
)

type Item struct {
	Route string                 `json:"route"`
	Body  map[string]interface{} `json:"body"`
}

type FileBuffer struct {
	path string
	mu   sync.Mutex
}

func New(path string) *FileBuffer {
	return &FileBuffer{path: path}
}

func (b *FileBuffer) Load() []Item {
	b.mu.Lock()
	defer b.mu.Unlock()
	raw, err := os.ReadFile(b.path)
	if err != nil {
		return nil
	}
	var items []Item
	if json.Unmarshal(raw, &items) != nil {
		return nil
	}
	return items
}

func (b *FileBuffer) Append(items []Item) {
	b.mu.Lock()
	defer b.mu.Unlock()
	cur := []Item{}
	if raw, err := os.ReadFile(b.path); err == nil {
		json.Unmarshal(raw, &cur)
	}
	cur = append(cur, items...)
	raw, _ := json.Marshal(cur)
	_ = os.WriteFile(b.path, raw, 0600)
}

func (b *FileBuffer) Save(remaining []Item) {
	b.mu.Lock()
	defer b.mu.Unlock()
	if len(remaining) == 0 {
		_ = os.Remove(b.path)
		return
	}
	raw, _ := json.Marshal(remaining)
	_ = os.WriteFile(b.path, raw, 0600)
}
