# RackSentinel 采集 API（Ingest）

入口：`/api/ingest.php?r=<action>`

鉴权：HTTP 头 `Authorization: Bearer <token>` 或 `X-Agent-Token: <token>`

令牌在 Web「监控采集」页创建；**完整令牌仅创建时返回一次**，服务端只保存 SHA-256 哈希。

## 公共

| Action | 方法 | 说明 |
|--------|------|------|
| `ping` | GET | 健康检查，无需令牌 |

## 主机 Agent（scope: `ingest:device`，且绑定 `device_id`）

| Action | 方法 | Body |
|--------|------|------|
| `heartbeat` | POST | `{ "status": "online\|warning\|maintenance", "meta": { ... } }` |
| `metrics` | POST | `{ "metrics": { "cpu", "mem", "disk", "temp", "net_in", "net_out", "power", "hostname", "ip" } }` |
| `health` | POST | `{ "checks": [ { "name", "ok", "level", "detail" } ] }` |
| `alert` | POST | `{ "level", "title", "detail" }` |

限流：每令牌每分钟请求数由设置 `ingest_rate_per_min` 控制（默认 120）。

## SNMP 轮询器（scope: `snmp:poll`）

| Action | 方法 | 说明 |
|--------|------|------|
| `snmp.targets` | GET | 返回 `monitor_mode=snmp` 且启用的设备列表与 MIB 配置目录 |
| `snmp.report` | POST | `{ "device_id", "metrics": {}, "sys": {}, "alerts": [] }` |

设备 SNMP 参数在机柜 U 位编辑页的「监控采集」区配置。

## 告警通知器（scope: `alert:read`, `notify:ack`）

| Action | 方法 | 说明 |
|--------|------|------|
| `alerts.pending` | POST | `{ "since": "YYYY-mm-dd HH:ii:ss" }` |
| `notify.recipients` | GET | 启用的收件人列表 |
| `alerts.ack_remote` | POST | `{ "id": <alert_id> }` |

## 大屏数据模式

- 设置 `metrics_simulation=0`（默认）：`metrics.tick` 不再随机改写指标，仅展示 Agent/SNMP 上报数据。
- `metrics_simulation=1`：保留 v0.1 演示用随机游走。

主机 Agent 超过 `heartbeat_stale_sec` 未上报心跳时，绑定设备会被标为 `offline`。

## 示例

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"metrics":{"cpu":12.5,"mem":44,"disk":60,"temp":38}}' \
  "http://localhost:8080/RackSentinel-DCIM/api/ingest.php?r=metrics"
```
