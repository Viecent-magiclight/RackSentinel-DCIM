#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
export CGO_ENABLED=0
GOOS=linux GOARCH=amd64 go build -ldflags="-s -w" -o dist/racksentinel-snmp-linux-amd64 .
cp -r profiles dist/profiles 2>/dev/null || true
echo "Built dist/racksentinel-snmp-linux-amd64"
