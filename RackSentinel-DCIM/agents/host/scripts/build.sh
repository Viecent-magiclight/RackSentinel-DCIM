#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
mkdir -p dist
export CGO_ENABLED=0
for pair in "linux:amd64" "linux:arm64" "darwin:amd64" "darwin:arm64" "windows:amd64"; do
  IFS=: read -r goos goarch <<< "$pair"
  ext=""
  [[ "$goos" == windows ]] && ext=".exe"
  echo "Building host agent $goos/$goarch"
  GOOS=$goos GOARCH=$goarch go build -ldflags="-s -w" -o "dist/racksentinel-host-${goos}-${goarch}${ext}" .
done
echo "Done -> dist/"
