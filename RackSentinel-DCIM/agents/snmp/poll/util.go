package poll

import "fmt"

func toFloat(v interface{}) float64 {
	switch x := v.(type) {
	case int:
		return float64(x)
	case uint:
		return float64(x)
	case int64:
		return float64(x)
	case uint64:
		return float64(x)
	case float64:
		return x
	case []byte:
		var f float64
		fmt.Sscanf(string(x), "%f", &f)
		return f
	default:
		return 0
	}
}

func toUint64(v interface{}) uint64 {
	switch x := v.(type) {
	case uint64:
		return x
	case uint:
		return uint64(x)
	case int:
		if x < 0 {
			return 0
		}
		return uint64(x)
	case int64:
		if x < 0 {
			return 0
		}
		return uint64(x)
	case []byte:
		var n uint64
		fmt.Sscanf(string(x), "%d", &n)
		return n
	default:
		return uint64(toFloat(v))
	}
}
