#!/usr/bin/env bash
set -euo pipefail
PORT="${SNMPSIM_PORT:-1161}"
DIR="$(cd "$(dirname "$0")/../testdata/snmpsim" && pwd)"
if command -v snmpsim-command-responder >/dev/null; then
  exec snmpsim-command-responder --agent-udpv4-endpoint=127.0.0.1:"$PORT" --data-dir="$DIR"
fi
if [ -x "$HOME/.local/bin/snmpsim-command-responder" ]; then
  exec "$HOME/.local/bin/snmpsim-command-responder" --agent-udpv4-endpoint=127.0.0.1:"$PORT" --data-dir="$DIR"
fi
echo "Install snmpsim: pip install snmpsim" >&2
exit 1
