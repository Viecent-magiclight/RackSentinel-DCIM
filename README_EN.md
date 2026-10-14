<div align="center">

# 🛡️ RackSentinel

### Data Center Infrastructure & Rack Asset Management

**机房资产与运维管理平台 · Data Center Infrastructure Management**

Custom floor plans · Rack U-position assets · Operations visualization · Live monitoring ingest

<br />

[![Release](https://img.shields.io/github/v/release/Viecent-magiclight/RackSentinel-DCIM?label=v0.2.0&color=3d9a8b)](https://github.com/Viecent-magiclight/RackSentinel-DCIM/releases/tag/v0.2.0)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Go](https://img.shields.io/badge/Go-1.22%2B-00ADD8?logo=go&logoColor=white)](https://go.dev/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![SQLite](https://img.shields.io/badge/SQLite-supported-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![REST API](https://img.shields.io/badge/API-REST-FF6B57)](#-rest-api)
[![Three.js](https://img.shields.io/badge/3D-Three.js-black?logo=threedotjs&logoColor=white)](https://threejs.org/)

[简体中文](README.md) | [English](README_EN.md)

[Features](#-features) · [Quick start](#-quick-start) · [Monitoring & edge agents](#-monitoring--edge-agents) · [Architecture](#-architecture) · [Roadmap](#-roadmap)

</div>

---

## 📖 Overview

**RackSentinel** is a data-center rack asset and operations platform: floor plans, rack DCIM, operations wall, and—since **v0.2**—a real monitoring pipeline (ingest API, host agent, SNMP poller, Tencent Cloud SMS/voice alerts).

The web app is **plain PHP + PDO + vanilla JavaScript** (no Composer, no frontend build). Edge components are **single-file Go binaries**. Database: MySQL or SQLite.

> **Current release: [v0.2.0](https://github.com/Viecent-magiclight/RackSentinel-DCIM/releases/tag/v0.2.0)**
>
> - Turn off **Dashboard metric simulation** on the **Monitoring ingest** page (`metrics_simulation=0`) to show **real** Agent/SNMP metrics on the wall.
> - You can keep simulation on (`metrics_simulation=1`) for demos.
> - SNMP vendor profiles marked **`needs_verification`** should be validated on site.

Application code lives under **`RackSentinel-DCIM/`** (paths below are relative to that directory unless noted).

## 🖼️ Screenshots

### Operations dashboard

<p align="center">
  <img src="Preview/Dashboard1.png" alt="RackSentinel operations dashboard 1" width="49%" />
  <img src="Preview/Dashboard2.png" alt="RackSentinel operations dashboard 2" width="49%" />
</p>

### Floor plan designer

<p align="center">
  <img src="Preview/Floor%20Plan%20Design.png" alt="RackSentinel floor plan designer" width="85%" />
</p>

### Rack U-position editor

<p align="center">
  <img src="Preview/Rack%20Layout%20Editor.png" alt="RackSentinel rack layout editor" width="85%" />
</p>

### Asset management

<p align="center">
  <img src="Preview/Asset%20Management.png" alt="RackSentinel asset management" width="85%" />
</p>

### Settings

<p align="center">
  <img src="Preview/Settings.png" alt="RackSentinel settings" width="85%" />
</p>

### Monitoring ingest (v0.2)

<p align="center">
  <img src="Preview/Monitor%20Ingest.png" alt="RackSentinel monitoring ingest" width="85%" />
</p>

### Alerts center

<p align="center">
  <img src="Preview/Alerts%20Center.png" alt="RackSentinel alerts center" width="85%" />
</p>

### Device monitor detail

<p align="center">
  <img src="Preview/Device%20Monitor.png" alt="RackSentinel device SNMP monitor" width="85%" />
</p>

> All screenshots reflect the **v0.2** UI at 1920×1080 in repo-root [`Preview/`](./Preview). Regenerate after `install/cli.php` with `scripts/prep-screenshot-demo.php` and `node scripts/capture-previews.mjs`.

## ✨ Features

| Module | Description |
| --- | --- |
| 🖥️ **Operations wall** | 3D / floor plan, device status, U and power, alerts and load trends; real or simulated metrics |
| ✏️ **Floor plan designer** | Walls, doors, columns, racks, sensors; grid snap, undo/redo |
| 📐 **Rack U management** | 42U elevation, drag U moves; SNMP and monitoring fields |
| 🔍 **Device inventory** | Search, filter, Excel import/export |
| 📡 **Ingest API** | `api/ingest.php`: token auth, heartbeat/metrics/health/alerts, SNMP reports — see [INGEST_API.md](RackSentinel-DCIM/docs/INGEST_API.md) |
| 🖧 **Host agent** | Linux / macOS / Windows: CPU, memory, disk, network; offline buffer |
| 🔀 **SNMP poller** | IF-MIB, multi-CPU, ENTITY-SENSOR, UPS-MIB; `sysObjectID` vendor JSON profiles |
| 📞 **Alert notifier** | Pending alerts, Tencent **SMS** and **voice** (TC3 signing) |
| 🛎️ **Monitoring / alerts UI** | `monitor.php` tokens and Tencent settings; `alerts.php`, `device-monitor.php` |
| ⚙️ **Settings** | Site name, wall title, refresh interval, accounts |

**Device types**: servers, switches, routers, firewalls, storage, UPS, PDU, KVM, etc. (`lib/catalog.php`).

## 🧰 Stack

| Layer | Technology |
| --- | --- |
| Web backend | PHP 8.0+, PDO, REST single entry |
| Database | MySQL 5.7+/8.0 or SQLite |
| Web frontend | Vanilla JS, Three.js (CDN) |
| Edge | Go 1.22+ (host / snmp / notifier) |
| Excel | Pure PHP XLSX |

## 🚀 Quick start

### Requirements

- PHP **8.0+** (`pdo_mysql` or `pdo_sqlite`)
- Optional: Go 1.22+ (to build agents yourself)
- Web server or `php -S`

### 1. Clone

```bash
git clone https://github.com/Viecent-magiclight/RackSentinel-DCIM.git
cd RackSentinel-DCIM/RackSentinel-DCIM
```

### 2. Database

Edit `config/config.php`, or use `RACK_DB_DRIVER=sqlite` for a quick trial (below).

### 3. Install

Open `install.php` in a browser, or:

```bash
php install/cli.php
```

### 4. Run

```bash
php -S 127.0.0.1:8123 -t .
```

Default admin: `admin` / `admin123` (change after install).

### SQLite trial

```bash
RACK_DB_DRIVER=sqlite php install/cli.php
RACK_DB_DRIVER=sqlite php -S 127.0.0.1:8123 -t .
```

Data file: `storage/server_rack.sqlite`.

---

## 📡 Monitoring & edge agents

Deployment: **[docs/DEPLOYMENT.md](RackSentinel-DCIM/docs/DEPLOYMENT.md)**. Ingest HTTP contract: **[docs/INGEST_API.md](RackSentinel-DCIM/docs/INGEST_API.md)**.

Prebuilt binaries: [GitHub Release v0.2.0](https://github.com/Viecent-magiclight/RackSentinel-DCIM/releases/tag/v0.2.0) (`racksentinel-host-*`, `racksentinel-snmp-*`, `racksentinel-notifier-*`, linux/darwin/windows × amd64/arm64; SNMP bundle `racksentinel-snmp-profiles-v0.2.0.tar.gz`).

Build releases yourself:

```bash
cd agents && ./release-build.sh /path/to/output v0.2.0
```

### Web: create ingest tokens

1. Sign in and open **Monitoring ingest** (`monitor.php`).
2. Disable dashboard metric simulation and save (`metrics_simulation=0`).
3. Create tokens (**full token shown only once**):
   - **+ Host agent**: must bind a device; used for `ingest:device`.
   - **+ SNMP poller token**: `snmp_poller`, fetch targets and report metrics.
   - **+ Notifier token**: `notifier`, fetch pending alerts and recipients.

Set `server_url` to: `https://<your-host>/<path>/api/ingest.php` (no trailing slash).

---

### 1️⃣ Host agent (`racksentinel-host`)

#### Download & config

1. Download `racksentinel-host-<os>-<arch>` from [v0.2.0](https://github.com/Viecent-magiclight/RackSentinel-DCIM/releases/tag/v0.2.0) (`.exe` on Windows).
2. Copy `agents/host/config.example.json` to `config.json` (beside the binary).

| Field | Description |
| --- | --- |
| `server_url` | Ingest base URL, e.g. `https://dcim.example.com/api/ingest.php` |
| `token` | Host agent token (one-time display at creation) |
| `interval_sec` | Report interval (seconds), default `60` |
| `buffer_file` | Offline queue file, default `buffer.json` |

Environment overrides: `RACK_INGEST_URL`, `RACK_AGENT_TOKEN`, `RACK_AGENT_CONFIG` (config file path).

#### Linux (systemd)

```bash
sudo cp racksentinel-host-linux-amd64 /usr/local/bin/racksentinel-host
sudo mkdir -p /etc/racksentinel
sudo cp config.json /etc/racksentinel/host-config.json
sudo bash agents/host/scripts/install-linux.sh
sudo systemctl start racksentinel-host
sudo systemctl status racksentinel-host
```

**Uninstall**: `sudo systemctl disable --now racksentinel-host`; remove `/etc/systemd/system/racksentinel-host.service` and `systemctl daemon-reload`.

#### macOS (launchd)

```bash
sudo cp racksentinel-host-darwin-arm64 /usr/local/bin/racksentinel-host
sudo cp config.json /usr/local/etc/racksentinel/host-config.json
sudo bash agents/host/scripts/install-macos.sh /usr/local/bin/racksentinel-host /usr/local/etc/racksentinel/host-config.json
```

**Uninstall**: `sudo launchctl unload /Library/LaunchDaemons/com.racksentinel.host.plist` and remove the plist.

#### Windows (service)

```powershell
# Place racksentinel-host-windows-amd64.exe and config.json under e.g. C:\Program Files\RackSentinel\
.\agents\host\scripts\install-windows.ps1 -Binary "C:\Program Files\RackSentinel\racksentinel-host.exe" -Config "C:\ProgramData\RackSentinel\host-config.json"
sc.exe start RackSentinelHost
```

Service runs with `-service -config <path>` (`agents/host/service_windows.go`).

**Uninstall**: `sc.exe stop RackSentinelHost`; `sc.exe delete RackSentinelHost`.

#### Offline buffer & troubleshooting

- On ingest failure, heartbeat and metrics append to `buffer_file`, then retry when connectivity returns.
- One-shot test: `racksentinel-host -config config.json -once`.
- Device stays offline: check **heartbeat stale** (`heartbeat_stale_sec`) and `monitor_mode=agent` on the device.
- HTTP 401: bad/disabled token; 403: token not bound to a device.

---

### 2️⃣ SNMP poller (`racksentinel-snmp`)

#### Token & config

1. Create an **SNMP poller token** on **Monitoring ingest**.
2. Configure from `agents/snmp/config.example.json`:

| Field | Description |
| --- | --- |
| `server_url` | Same ingest URL (`api/ingest.php`) |
| `token` | SNMP poller token |
| `interval_sec` | Poll interval, default `120` |
| `profiles_dir` | MIB profile directory, default `profiles` (extract `racksentinel-snmp-profiles-*.tar.gz` from the release) |
| `state_file` | Interface rate state, default `snmp-state.json` |

Run: `racksentinel-snmp -config config.json`; one cycle: `-once`.

CLI probe (no web session): `racksentinel-snmp -probe -host 10.0.0.1 -community public -version 2c`

#### Web: add SNMP devices

In the **rack U-position** device editor:

| Field | Description |
| --- | --- |
| `monitor_mode` | Set to **`snmp`** |
| `snmp_enabled` | **Yes** |
| `snmp_version` | `1` / `2c` / `3` |
| `snmp_host` | Empty = management / business IP |
| `snmp_port` | Default `161` |
| `snmp_community` | v1/v2c community |
| `snmp_v3_user` / `snmp_v3_auth_proto` / `snmp_v3_auth_pass` / `snmp_v3_priv_proto` / `snmp_v3_priv_pass` | SNMPv3 |
| `snmp_profile` | Optional profile ID; empty = **sysObjectID prefix** auto-match |

**Test SNMP** runs `device.snmp_test` (server must have `racksentinel-snmp` available for `-probe`).

Details: `device-monitor.php?id=<device_id>` (interfaces, sensors, trends).

#### Vendor profiles (`agents/snmp/profiles/*.json`)

| Profile ID | Vendor / use | Site check |
| --- | --- | --- |
| `generic-host-resources` | HOST-RESOURCES / IF-MIB | partial |
| `generic-ups` | RFC1628 UPS-MIB | no |
| `cisco-ios` | Cisco CPU / memory / temp | **needs_verification** |
| `huawei-switch` | Huawei switch | **needs_verification** |
| `h3c-switch` | H3C HH3C-ENTITY-EXT | **needs_verification** |
| `brocade-fc` | Brocade / Broadcom SAN | **needs_verification** |
| `dell-emc-unity` | Dell EMC Unity | **needs_verification** |
| `hpe-3par` | HPE 3PAR / Primera | **needs_verification** |
| `huawei-oceanstor` | Huawei OceanStor | **needs_verification** |
| `netapp-ontap` | NetApp ONTAP | **needs_verification** |
| `synology-dsm` | Synology | **needs_verification** |
| `qnap-qts` | QNAP | **needs_verification** |
| `ibm-storwize` | IBM Storwize / FlashSystem | **needs_verification** |
| `hitachi-storage` | Hitachi | **needs_verification** |

#### Run as a service (example: Linux systemd)

Similar to the host agent: `ExecStart=/usr/local/bin/racksentinel-snmp -config /etc/racksentinel/snmp-config.json`, `Restart=always`.

---

### 3️⃣ Alert notifier (`racksentinel-notifier`)

#### Platform (web)

On **Monitoring ingest** → **Tencent alerts**:

| Setting key | Description |
| --- | --- |
| `notify_enabled` | `1` to enable external notify |
| `tencent_secret_id` / `tencent_secret_key` | May be empty; use env vars instead |
| `tencent_sms_app_id` / `tencent_sms_sign` / `tencent_sms_template_id` | SMS |
| `tencent_vms_app_id` / `tencent_vms_template_id` | Voice |
| `notify_dedupe_sec` | Dedupe window (seconds) |
| `notify_rate_per_hour` | Max outbound calls per hour |
| `notify_voice_mode` | `tts` (SendTtsVoice) or `code` (SendCodeVoice) |

**Recipients** on the same page: `phone` (voice), `sms_phone`, `min_level` (`info`/`warning`/`critical`), `escalate_min` (minutes after alert creation before notify).

**Test send**: **Test send** button (`notify.test_send` API; needs valid Tencent templates).

Recommended env vars (do not commit): `TENCENT_SECRET_ID`, `TENCENT_SECRET_KEY`.

#### Notifier process config

Copy `agents/notifier/config.example.json`:

| Field | Description |
| --- | --- |
| `server_url` / `token` | `api/ingest.php` + **notifier token** |
| `interval_sec` | Poll interval |
| `secret_id` / `secret_key` | Optional; env vars override when empty |
| `sms_sdk_app_id` / `sms_sign` / `sms_template_id` | SMS (align with web settings) |
| `vms_sdk_app_id` / `vms_template_id` | Voice |
| `dedupe_sec` / `rate_per_hour` / `voice_mode` | Dedupe, rate limit, voice mode |

CLI test (needs `TEST_PHONE`): `TEST_PHONE=+86... racksentinel-notifier -config config.json -test`

Behavior: fetches `alerts.pending` and `notify.recipients`; filters by level and `escalate_min`; dedupe and hourly cap; may call `alerts.ack_remote` after send (`agents/notifier`).

---

## 🏗️ Architecture

```text
┌─────────────────────────────────────────────────────────────────┐
│  Web: wall · floor plan · racks · inventory · monitor · alerts   │
└────────────────────────────┬────────────────────────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
 api/index.php        api/ingest.php         install/
 (session REST)       (Bearer tokens)         migrate
        │                    │
        └──────────┬─────────┘
                   ▼
            PHP + PDO (lib/*)
                   │
                   ▼
     MySQL / SQLite: devices · device_metrics · device_interfaces
                      · device_sensors · monitor_agents · alerts · …
                   ▲
     ┌─────────────┴─────────────┬──────────────────┐
     │                           │                  │
 racksentinel-host      racksentinel-snmp    racksentinel-notifier
 (host metrics)         (SNMP poll)          (Tencent SMS/voice)
```

### Data model (core)

- `devices`: assets, `monitor_mode` (`none` / `agent` / `snmp`), SNMP fields, `last_heartbeat_at`
- `device_metrics` / `device_interfaces` / `device_sensors`: reported metrics and SNMP detail
- `monitor_agents`: ingest tokens (hashed)
- `alerts` / `notification_recipients`: alerts and outbound recipients

## 🔌 REST API

**Admin** (browser session):

```text
RackSentinel-DCIM/api/index.php?r=<resource>.<action>
```

**Ingest** (Bearer token, no cookie):

```text
RackSentinel-DCIM/api/ingest.php?r=<action>
```

Examples: `dashboard.summary`, `metrics.tick` (real-only when `metrics_simulation=0`), `device.monitor`, `device.snmp_test`, `agent.*`, `notify.*`.

Full ingest contract: [docs/INGEST_API.md](RackSentinel-DCIM/docs/INGEST_API.md).

## 🔐 Security

- bcrypt passwords, login rate limit, HttpOnly/SameSite sessions, write CSRF, PDO prepared statements, field allowlists.
- Do **not** commit Tencent SecretKey or ingest tokens; prefer environment variables.

## 🗺️ Roadmap

### v0.2 (released)

- [x] Ingest API, host/SNMP/notifier agents, monitoring UI, IF-MIB and vendor profile framework

### Later

- [ ] Multi-site aggregation, Zabbix/Prometheus adapters, richer FC port metrics
- [ ] More vendor MIB validation and discovery
- [ ] RBAC, email/WeCom/webhook, offline 3D assets

## 📁 Layout

```text
RackSentinel-DCIM/
  api/index.php · api/ingest.php
  monitor.php · alerts.php · device-monitor.php
  docs/INGEST_API.md · docs/DEPLOYMENT.md
  agents/host · agents/snmp · agents/notifier · agents/release-build.sh
  lib/ingest.php · lib/ingest_snmp.php
  install/ · assets/ · config/
Preview/                 # screenshots at repo root
```

## 📝 Notes

- Wall polls at `poll_ms`; metric history writes are throttled (`metrics.tick`).
- 3D uses Three.js CDN; falls back to floor plan on failure.
- U positions: U1 at bottom of rack, increasing upward.

## 📄 License

[MIT License](./LICENSE)

---

<div align="center">

**RackSentinel · Every rack unit, crystal clear**

*让每一 U 空间一目了然*

</div>
