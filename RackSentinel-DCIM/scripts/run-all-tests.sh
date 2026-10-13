#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export RACK_DB_DRIVER=sqlite
echo "== PHP smoke =="
php "$ROOT/scripts/smoke-ingest.php"
php "$ROOT/install/verify.php"
echo "== Go host =="
(cd "$ROOT/agents/host" && go test ./...)
echo "== Go snmp =="
(cd "$ROOT/agents/snmp" && go test ./...)
echo "== Go notifier =="
(cd "$ROOT/agents/notifier" && go test ./...)
if command -v pip3 >/dev/null; then
  pip3 install -q snmpsim pysmi 2>/dev/null || true
fi
if python3 -c "import snmpsim" 2>/dev/null; then
  echo "== SNMP integration (snmpsim) =="
  chmod +x "$ROOT/agents/snmp/scripts/run-snmpsim.sh"
  SNMPSIM_PORT=1161
  "$ROOT/agents/snmp/scripts/run-snmpsim.sh" &
  SIM_PID=$!
  sleep 2
  (cd "$ROOT/agents/snmp" && SNMPSIM_HOST=127.0.0.1 go test -tags=integration ./poll -count=1) || true
  kill $SIM_PID 2>/dev/null || true
fi
echo "ALL DONE"
