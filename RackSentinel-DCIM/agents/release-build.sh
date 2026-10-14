#!/usr/bin/env bash
# Build release binaries for host, snmp, notifier (linux/darwin/windows × amd64/arm64)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="${1:-$ROOT/../../release-assets/v0.2.0}"
mkdir -p "$OUT"
export CGO_ENABLED=0
VER="${2:-v0.2.0}"

build_one() {
  local dir="$1" name="$2"
  cd "$ROOT/$dir"
  go mod tidy
  for pair in "linux:amd64" "linux:arm64" "darwin:amd64" "darwin:arm64" "windows:amd64" "windows:arm64"; do
    IFS=: read -r goos goarch <<< "$pair"
    ext=""
    [[ "$goos" == windows ]] && ext=".exe"
    echo ">> $name $goos/$goarch"
    GOOS=$goos GOARCH=$goarch go build -ldflags="-s -w -X main.version=$VER" -o "$OUT/${name}-${goos}-${goarch}${ext}" .
  done
}

build_one host racksentinel-host
build_one snmp racksentinel-snmp
build_one notifier racksentinel-notifier

# SNMP MIB profiles bundle
tar -czf "$OUT/racksentinel-snmp-profiles-${VER}.tar.gz" -C "$ROOT/snmp" profiles

echo "Assets in $OUT"
ls -la "$OUT"
