package main

func levelAllowed(level, min string) bool {
	if min == "" {
		min = "warning"
	}
	order := map[string]int{"info": 1, "warning": 2, "critical": 3}
	if order[level] == 0 {
		level = "warning"
	}
	return order[level] >= order[min]
}
