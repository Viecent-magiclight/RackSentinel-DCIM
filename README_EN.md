<div align="center">

# 🛡️ RackSentinel

### Data Center Infrastructure & Rack Asset Management

**A data center asset and operations management platform**

Design your own floor plans · Manage rack U-space assets · Visualize operational status

<br />

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![SQLite](https://img.shields.io/badge/SQLite-supported-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![REST API](https://img.shields.io/badge/API-REST-FF6B57)](#-rest-api)
[![Three.js](https://img.shields.io/badge/3D-Three.js-black?logo=threedotjs&logoColor=white)](https://threejs.org/)

[简体中文](README.md) | **English**

[Features](#-features) · [Screenshots](#-screenshots) · [Quick Start](#-quick-start) · [Architecture](#-architecture) · [Roadmap](#-roadmap)

</div>

---

## 📖 Introduction

**RackSentinel** is an asset and operations management platform for data center environments. It provides floor plan design, rack U-space management, device inventory, an operations dashboard, and alert management.

Built with **plain PHP + PDO + vanilla JavaScript**, RackSentinel requires neither Composer nor a frontend build tool. It supports MySQL and SQLite, making it suitable for local evaluation, self-hosted deployment, and further development.

> **Current version: `v0.1`**
>
> The main administrative UI and data model are implemented. Some runtime metrics are simulated. Integration with real data sources such as Zabbix, Prometheus, SNMP, and environmental sensors is planned for future releases.

## 🖼️ Screenshots

### Operations Dashboard

<p align="center">
  <img src="Preview/Dashboard1.png" alt="RackSentinel operations dashboard preview 1" width="49%" />
  <img src="Preview/Dashboard2.png" alt="RackSentinel operations dashboard preview 2" width="49%" />
</p>

### Data Center Floor Plan Designer

<p align="center">
  <img src="Preview/Floor%20Plan%20Design.png" alt="RackSentinel floor plan designer" width="85%" />
</p>

### Rack U-Space Layout Editor

<p align="center">
  <img src="Preview/Rack%20Layout%20Editor.png" alt="RackSentinel rack U-space layout editor" width="85%" />
</p>

### Asset Management

<p align="center">
  <img src="Preview/Asset%20Management.png" alt="RackSentinel asset management interface" width="85%" />
</p>

> Screenshots are stored in [`Preview/`](./Preview). If an image does not render, check that the filename capitalization and path match the files in the repository.

## ✨ Features

| Module | Description |
| --- | --- |
| 🖥️ **Operations Dashboard** | 3D data center and floor plan views, device status statistics, U-space capacity and power, alert overview, and load trends; polls every 5 seconds by default |
| ✏️ **Floor Plan Designer** | Draw walls, glass partitions, doors, windows, and pillars; place racks and environmental sensors; grid snapping, undo, and redo |
| 📐 **Rack U-Space Management** | 42U rack front view; drag devices to change U positions or move them between racks; maintain device names, hostnames, IPs, models, and asset IDs |
| 🔍 **Device Inventory** | Search by name, hostname, IP, model, or asset ID; filter by device type, operating system, status, and rack |
| 📤 **Excel Import / Export** | Export device inventory and per-rack U-space layouts; batch create, update, and delete with validation before import |
| 🚨 **Alert Management** | View and acknowledge alerts |
| ⚙️ **System Settings** | Configure site name, dashboard title, refresh interval, and account passwords |
| 🧭 **3D Data Center Scene** | Render a 3D scene with Three.js; automatically fall back to the floor plan if loading fails |

**Supported device types**

Servers, blade chassis, switches, routers, firewalls, gateways, load balancers, storage arrays, tape libraries, UPS, PDU, KVM, consoles, patch panels, and blanking panels.

**Environmental sensor types**

Temperature, humidity, smoke, water leakage, and power distribution.

## 🧰 Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.0+, PDO prepared statements, single-entry REST API |
| Database | MySQL 5.7+/8.0 or SQLite, switchable drivers |
| Frontend | Vanilla JavaScript, no frontend framework or build step |
| 3D scene | Three.js loaded via CDN |
| Icons | SVG icon sprite |
| Excel | Dependency-free XLSX reader/writer implemented in PHP |

## 🚀 Quick Start

### Requirements

- PHP **8.0+**
- MySQL **5.7+/8.0**, or `pdo_sqlite` enabled to use SQLite
- Any web server capable of running PHP
- Enable `pdo_mysql` when using MySQL

### 1. Clone the repository

```bash
git clone https://github.com/Viecent-magiclight/RackSentinel-DCIM.git
cd RackSentinel-DCIM
```

### 2. Configure the database

Edit the database connection settings in `config/config.php`. For example:

```php
'mysql' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'server_rack',
    'username' => 'root',
    'password' => 'your_password',
    'charset'  => 'utf8mb4',
],
```

The database account needs permission to create a database. If the database already exists, table-creation privileges are sufficient.

### 3. Install and initialize sample data

Open `install.php` in your browser and click **Start installation and import sample data**.

You can also install from the command line:

```bash
php install/cli.php
```

The installer creates the schema and inserts demo data, including a room, floor plan elements, racks, devices, sensors, metric history, and alert samples.

> ⚠️ **Warning:** The installer drops and recreates the relevant tables. Reinstalling may erase existing data. Do not run it against an environment containing important data without taking appropriate precautions.

### 4. Run and access the application

Point your web server's document root to the project directory and open `index.php` to access the operations dashboard.

For local testing, you can use PHP's built-in web server:

```bash
php -S 127.0.0.1:8123 -t .
```

Then visit `http://127.0.0.1:8123`.

## 🪶 Try It with SQLite

You can skip MySQL setup and switch to SQLite using an environment variable. The database file is written to `storage/server_rack.sqlite`.

**Linux / macOS**

```bash
RACK_DB_DRIVER=sqlite php install/cli.php
RACK_DB_DRIVER=sqlite php -S 127.0.0.1:8123 -t .
```

**Windows PowerShell**

```powershell
$env:RACK_DB_DRIVER = "sqlite"
php install/cli.php
php -S 127.0.0.1:8123 -t .
```

MySQL and SQLite use the same table structure, so you can switch database drivers as needed.

## 🏗️ Architecture

```text
┌──────────────────────────────────────────────────────────────┐
│                         Web UI                               │
│ Dashboard · Floor Plan · Rack U-Space · Inventory · Settings │
└──────────────────────────────┬───────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────┐
│                       REST API                               │
│             api/index.php?r=<resource>.<action>              │
│                   GET reads · POST + JSON writes             │
└──────────────────────────────┬───────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────┐
│                        PHP Layer                             │
│  db.php · auth.php · catalog.php · helpers.php · xlsx.php    │
└──────────────────────────────┬───────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────┐
│                    MySQL / SQLite                            │
│  rooms · racks · devices · device_metrics · alerts · users   │
└──────────────────────────────────────────────────────────────┘
```

### Core Data Relationships

- `rooms`: rooms, associated with `walls`, `racks`, and `sensors`
- `racks`: racks, containing `devices` mounted at specific U positions
- `devices`: devices, associated with metric history in `device_metrics` and alerts in `alerts`
- `users`, `settings`, `login_attempts`: accounts, site settings, and login rate limiting

## 🔌 REST API

All API requests use the following entry point:

```text
api/index.php?r=<resource>.<action>
```

Use `GET` for reads and `POST` with a JSON body for writes.

| Route | Description |
| --- | --- |
| `meta` | Retrieve device types, operating systems, statuses, and other catalogs |
| `room.list` / `room.save` | Room APIs (prepared for future multi-room support) |
| `layout.get` / `layout.save` | Read and save floor plan layouts |
| `rack.list` / `rack.get` / `rack.save` / `rack.delete` | Rack queries and management |
| `device.list` / `device.get` / `device.save` / `device.move` / `device.delete` | Device management and U-position moves |
| `dashboard.summary` | Retrieve dashboard summary data |
| `metrics.tick` | Advance one metrics update (currently simulated data) |
| `alert.list` / `alert.ack` | List and acknowledge alerts |

Device saves, device moves, and Excel imports validate U-space boundaries and overlaps. Conflicts return `HTTP 422`.

## 🔐 Security

- Passwords are hashed with **bcrypt**
- Failed logins are rate-limited by source IP to reduce brute-force attempts
- Session cookies use `HttpOnly` and `SameSite=Lax`
- Write endpoints perform same-origin checks to help mitigate CSRF
- `session_regenerate_id` is called after successful login to help prevent session fixation
- Database access uses PDO prepared statements
- Write fields are filtered through an allowlist
- `install/verify.php` checks sample data consistency

## 🗺️ Roadmap

### v0.2 · Data Acquisition

The current `metrics.tick` endpoint generates simulated metrics for demonstration. Planned work includes:

- [ ] **Multi-room support**: room switching, cross-room aggregation, and global asset statistics
- [ ] **Environmental sensor integration**: real data ingestion, threshold alerts, and historical charts
- [ ] **SNMP for switches**: port status, traffic, link information, and device discovery
- [ ] **Monitoring integrations**: adapters for data sources such as Zabbix and Prometheus
- [ ] **Unified ingestion**: a consistent reporting interface for different probes

### Future Plans

- Unified cross-room topology and network link visualization
- Device lifecycle management, maintenance plans, and work orders
- RBAC-based multi-role permissions
- Alert notifications through email, WeCom, and Webhooks
- Local Three.js assets for offline deployment

## 📁 Directory Structure

```text
config/config.php       Database and application configuration
lib/db.php              PDO wrapper (MySQL / SQLite)
lib/auth.php            Sessions, same-origin checks, and IP rate limiting
lib/catalog.php         Device types, OS, and sensor catalogs
lib/helpers.php         U-space conflict checks, rack statistics, navigation
lib/xlsx.php            XLSX reader/writer
api/index.php           Single REST API entry point
partials/               Shared page components
assets/css/app.css      Global styles
assets/js/common.js     API wrapper, icons, dialogs, and charts
assets/js/planner.js    Floor plan designer
assets/js/rackview.js   Rack U-space editor
assets/js/dashboard.js  Operations dashboard
assets/js/scene3d.js    3D data center scene
install/                Schema scripts, sample data, installer, and checks
Preview/                README screenshots
```

## 📝 Notes

- **The 3D scene depends on a CDN:** Three.js is loaded from a CDN. For intranet deployments, it can be hosted locally. The application falls back to the floor plan if loading fails.
- **Metric history is throttled:** The dashboard polls every 5 seconds by default, while historical metrics are persisted according to a throttling strategy to limit database growth.
- **Floor plan coordinates:** Coordinates use centimeters. Rack `x` / `y` values represent the rack center, and `rotation` supports `0/90/180/270`.
- **U numbering:** U1 is at the bottom of a rack, with numbers increasing upward.
- **Data collection status:** Runtime metrics are simulated in the current version. Integration with real monitoring systems is planned for future releases.

## 📄 License

RackSentinel is licensed under the [MIT License](./LICENSE).

---

<div align="center">

**RackSentinel · Every rack unit, crystal clear.**

Made with ❤️ · PHP · SQLite / MySQL · Vanilla JavaScript · Three.js

[简体中文](README.md) | **English**

</div>
