# RackSentinel 边缘采集组件

| 目录 | 二进制 | 说明 |
|------|--------|------|
| `host/` | `racksentinel-host` | 主机 CPU/内存/磁盘/网络采集 |
| `snmp/` | `racksentinel-snmp` | SNMP 轮询，配置文件驱动 MIB |
| `notifier/` | `racksentinel-notifier` | 拉取告警并调用腾讯云语音/短信 |

## 构建

```bash
cd agents/host && chmod +x scripts/build.sh && ./scripts/build.sh
cd agents/snmp && chmod +x scripts/build.sh && ./scripts/build.sh
cd agents/notifier && go build -o dist/racksentinel-notifier .
```

## 配置

复制各目录下的 `config.example.json`，填入 `server_url`（指向 `api/ingest.php` 基址）与在 Web 创建的令牌。

腾讯云密钥请使用环境变量 `TENCENT_SECRET_ID` / `TENCENT_SECRET_KEY`，勿提交到 Git。

## 安装为服务

- Linux：`host/scripts/install-linux.sh`
- macOS / Windows：可使用 launchd / Windows Service 包装相同二进制与 `-config` 参数

离线时主机 Agent 会在内存中缓冲一批上报并在恢复后重试（见 `host/main.go` 中的 `pending` 队列）。
