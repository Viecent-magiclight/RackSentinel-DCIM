# RackSentinel 监控组件部署

## Web 平台

1. 安装 PHP 8 + PDO（MySQL 或 `RACK_DB_DRIVER=sqlite`）。
2. 访问 `install.php` 或运行迁移（页面加载时自动 `run_migrations()`）。
3. **监控采集** (`monitor.php`)：关闭指标模拟、创建 Agent 令牌。

## 主机 Agent (`agents/host`)

```bash
cd agents/host && ./scripts/build.sh
sudo cp dist/racksentinel-host-linux-amd64 /usr/local/bin/racksentinel-host
sudo cp config.example.json /etc/racksentinel/host-config.json
```

- Linux: `./scripts/install-linux.sh`
- macOS: `./scripts/install-macos.sh`
- Windows: `.\scripts\install-windows.ps1`（需先放置二进制与配置）

离线时指标写入 `buffer.json`，恢复后自动重试。

## SNMP 轮询器 (`agents/snmp`)

```bash
cd agents/snmp && ./scripts/build.sh
# config.json: server_url, token (snmp_poller), profiles_dir, state_file
./dist/racksentinel-snmp-linux-amd64 -config config.json
```

- 设备：`monitor_mode=snmp`，填写 Community / v3 / **MIB 配置**。
- 命令行探测：`racksentinel-snmp -probe -host 10.0.0.1 -community public`
- 测试：`scripts/run-snmpsim.sh` + `go test -tags=integration ./poll`

## 告警通知器 (`agents/notifier`)

```bash
cd agents/notifier && go build -o dist/racksentinel-notifier .
export TENCENT_SECRET_ID=... TENCENT_SECRET_KEY=...
# config.json: server_url, notifier token, template ids
./dist/racksentinel-notifier -config config.json
```

Web **测试发送**：监控页「测试发送」（使用 TC3 签名，密钥优先环境变量）。

## 验证

```bash
./scripts/run-all-tests.sh
```
