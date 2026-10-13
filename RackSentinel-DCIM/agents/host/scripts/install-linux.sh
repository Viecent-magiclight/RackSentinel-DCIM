#!/usr/bin/env bash
# 安装 systemd 服务（需 root）
set -euo pipefail
BIN="${1:-/usr/local/bin/racksentinel-host}"
CFG="${2:-/etc/racksentinel/host-config.json}"
cat >/etc/systemd/system/racksentinel-host.service <<EOF
[Unit]
Description=RackSentinel Host Monitoring Agent
After=network-online.target

[Service]
Type=simple
ExecStart=$BIN -config $CFG
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable racksentinel-host.service
echo "Installed. Edit $CFG then: systemctl start racksentinel-host"
