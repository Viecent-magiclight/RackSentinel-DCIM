#!/usr/bin/env bash
set -euo pipefail
BIN="${1:-/usr/local/bin/racksentinel-host}"
CFG="${2:-/usr/local/etc/racksentinel/host-config.json}"
PLIST=/Library/LaunchDaemons/com.racksentinel.host.plist
sudo tee "$PLIST" >/dev/null <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key><string>com.racksentinel.host</string>
  <key>ProgramArguments</key>
  <array>
    <string>$BIN</string>
    <string>-config</string>
    <string>$CFG</string>
  </array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><true/>
</dict>
</plist>
EOF
sudo launchctl load -w "$PLIST"
echo "Installed launchd agent. Edit $CFG then: sudo launchctl kickstart -k system/com.racksentinel.host"
