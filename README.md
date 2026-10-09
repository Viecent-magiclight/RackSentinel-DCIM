# 🛡️ RackSentinel・机房机柜资产与运维管理平台

**RackSentinel — Data Center Infrastructure & Rack Asset Management (DCIM)**

*可自绘机房平面图・按 U 位管理机柜设备・大屏实时展示运维状态*

*Draw your own data-center floor plan · Manage rack units (U) · Real-time ops dashboard*

纯 PHP + PDO + 原生 JS，无需 Composer、无需前端构建，解压即可部署。

Pure PHP + PDO + vanilla JS. **Zero dependencies, zero build step** — unzip and run.



![PHP](https://img.shields.io/badge/PHP-8.0+-%23777BB4?logo=php\&logoColor=white)



![MySQL](https://img.shields.io/badge/MySQL-5.7%2F8.0-%234479A1?logo=mysql\&logoColor=white)



![SQLite](https://img.shields.io/badge/SQLite-ready-%23003B57?logo=sqlite\&logoColor=white)



![REST API](https://img.shields.io/badge/API-REST-%23FF6B57)



![Three.js](https://img.shields.io/badge/3D-Three.js-%23FF6B57)



![Status](https://img.shields.io/badge/Version-0.1%20\(UI%20only\)-%234ade80)

> **当前状态 / Project status**
>
> ：
>
> `v0.1`
>
>  已完成 
>
> **后台界面与数据模型**
>
> （运行数据当前为模拟值）。
> **数据探针（Zabbix / Prometheus / SNMP / 传感器）将在&#x20;**
>
> `v0.2`
>
> **&#x20;中发布。**
> `v0.1`
>
>  delivers the 
>
> **backend UI & data model**
>
>  (runtime data is currently simulated).
> **Data probes (Zabbix / Prometheus / SNMP / sensors) ship in&#x20;**
>
> `v0.2`
>
> **.**



***

## 📦 Features・功能特性



| Feature                           | 说明                                                   |
| --------------------------------- | ---------------------------------------------------- |
| 🖥️ **运维大屏** Operations Dashboard | 3D 机房 / 平面图双视图、设备状态统计、U 位容量与功率、实时告警、负载趋势；默认每 5 秒轮询   |
| ✏️ **平面图画板** Floor Plan Designer  | 自由绘制墙体、玻璃隔断、门、窗、柱；放置机柜与环境传感器；网格吸附、撤销重做               |
| 📐 **机柜 U 位** Rack U-View         | 整列 42U 立面图，拖动设备改 U 位或跨柜搬迁，维护名称 / 主机名 / IP / 型号 / 资产号 |
| 🔍 **设备清单** Device Inventory      | 按名称、主机名、IP、型号、资产号搜索；按类型 / 操作系统 / 状态 / 机柜筛选           |
| ⚙️ **系统设置** Settings              | 站点名称、大屏标题、刷新间隔、账号与密码管理                               |
| 📤 **Excel 导入导出** Import / Export | 导出设备清单与逐柜 U 位图（多 Sheet、边框、合并）；批量新增 / 更新 / 删除，导入前整批校验 |
| 🚨 **告警** Alerts                  | 告警列表与确认（ack）                                         |

**设备类型 / Device types**：服务器、刀片机箱、交换机、路由器、防火墙、网关、负载均衡、存储阵列、磁带库、UPS、PDU、KVM、控制台、配线架、盲板

**操作系统 / Operating systems**：Windows Server、Windows 10/11、Ubuntu、CentOS、RHEL、Debian、openSUSE、银河麒麟、统信 UOS、VMware ESXi、Hyper-V、Proxmox VE、FreeBSD、Cisco IOS、华为 VRP、H3C Comware、锐捷 RGOS、Arista EOS、Juniper Junos、OpenWrt、专用固件

**环境传感器 / Environment sensors**：温度、湿度、烟感、漏水、配电



***

## 🛠️ Tech Stack・技术栈



| Layer    | Stack                                                                |
| -------- | -------------------------------------------------------------------- |
| Backend  | PHP 8.0+・PDO 预处理・REST 单入口                                            |
| Database | **MySQL 5.7+/8.0**（默认）或 **SQLite**（双驱动，`RACK_DB_DRIVER=sqlite` 一键切换） |
| Frontend | 原生 JS，无框架、无构建工具                                                      |
| 3D Scene | Three.js（CDN 加载，失败自动回退平面图）                                           |
| Icons    | SVG 图标雪碧图（`partials/icons.php`）                                      |
| Excel    | 纯 PHP 自研 xlsx 读写库（`lib/xlsx.php`），零依赖                                |



***

## 🏗️ Architecture・架构一览



```
┌─────────────────────────────────────────────────────────────┐
│                     REST API (单入口)                        │
│              api/index.php?r=<resource>.<action>            │
│                GET 读取 · POST + JSON 写入                    │
├─────────────────────────────────────────────────────────────┤
│  lib/db.php       PDO 封装（MySQL / SQLite 双驱动）            │
│  lib/auth.php     会话登录 · bcrypt · 同源校验 · IP 限速锁       │
│  lib/catalog.php  设备类型 / 系统 / 状态 / 墙体 / 传感器目录      │
│  lib/helpers.php  U 位冲突检测 · 机柜统计 · 导航渲染             │
│  lib/xlsx.php     自研 Excel 读写                             │
├─────────────────────────────────────────────────────────────┤
│  rooms → walls / racks / sensors                              │
│  racks  → devices (按 U 位安装)                                │
│  devices→ device_metrics（实时/历史指标） + alerts              │
└─────────────────────────────────────────────────────────────┘
```

### 数据模型・Data Model

`rooms`（机房）→ `walls`（墙体）、`racks`（机柜）、`sensors`（传感器）

`racks` → `devices`（设备，`u_start`/`u_size` 定位 U 位）

`devices` → `device_metrics`（CPU / 内存 / 磁盘 / 温度 / 网络 / 功率历史）、`alerts`（告警）

辅助表：`users`（账号）、`settings`（站点配置）、`login_attempts`（登录限速）



***

## 🚀 Quick Start・快速开始

### 1. 环境要求 / Requirements



* **PHP 8.0+**，启用 `pdo_mysql`（本地试用也可用 `pdo_sqlite`）

* **MySQL 5.7+ / 8.0**（或直接使用 SQLite，免装数据库）

* 任意 Web 服务器，站点根目录指向本文件夹

### 2. 配置数据库 / Configure database

编辑 `config/config.php`（也可用环境变量覆盖）：



```
'mysql' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'server_rack',
    'username' => 'root',
    'password' => 'your_password',
    'charset'  => 'utf8mb4',
],
```

账号需 `CREATE DATABASE` 权限；若数据库已建好，则只需 `CREATE TABLE` 权限。

### 3. 执行安装 / Install

浏览器打开 `install.php`，点击「开始安装并写入样本数据」。

安装会建库建表，并灌入 1 个机房、14 处平面图元、18 个机柜、约 260 台设备、10 个传感器、指标历史与告警样本。

命令行安装（适合远程服务器）：



```
php install/cli.php
```

> ⚠️ 安装脚本会先 
>
> `DROP TABLE`
>
>  再重建，
>
> **重装会清空现有数据**
>
> 。

### 4. 访问 / Access

打开 `index.php` 即为运维大屏。默认导航：**运维大屏 / 平面图画板 / 机柜 U 位 / 设备清单 / 系统设置**。



***

## 🧪 免装 MySQL 直接试用 / Try without MySQL

设置环境变量即可切换到 SQLite 文件库（数据写入 `storage/server_rack.sqlite`）：



```
$env:RACK_DB_DRIVER = "sqlite"
php install/cli.php
php -S 127.0.0.1:8123 -t .
```



```
RACK_DB_DRIVER=sqlite php install/cli.php
RACK_DB_DRIVER=sqlite php -S 127.0.0.1:8123 -t .
```

两种驱动表结构字段完全一致，验证完可直接切回 MySQL。



***

## 🔌 REST API・接口

统一入口 `api/index.php?r=<resource>.<action>`，读取用 GET、写入用 POST + JSON body。



| Route                                                                          | 说明                         |
| ------------------------------------------------------------------------------ | -------------------------- |
| `meta`                                                                         | 设备类型 / 操作系统 / 状态等目录        |
| `room.list` / `room.save`                                                      | 机房增查（**多机房入口，为 0.2 扩展预留**） |
| `layout.get` / `layout.save`                                                   | 读取、保存整张平面图（墙体、机柜、传感器）      |
| `rack.list` / `rack.get` / `rack.save` / `rack.delete`                         | 机柜增删改查                     |
| `device.list` / `device.get` / `device.save` / `device.move` / `device.delete` | 设备增删改查与 U 位搬迁              |
| `dashboard.summary`                                                            | 大屏所需汇总数据                   |
| `metrics.tick`                                                                 | 推进一轮实时指标（**当前为模拟随机游走**）    |
| `alert.list` / `alert.ack`                                                     | 告警列表与确认                    |

`device.save` / `device.move` / Excel 导入均校验 U 位越界与重叠，冲突时返回 `HTTP 422` 并指出被哪台设备占用。



***

## 🛡️ Security・安全设计



* **bcrypt** 密码哈希，登录失败按来源 IP 限速锁定（防爆破）

* 会话 Cookie `HttpOnly + SameSite=Lax`，写入接口做**同源校验**（防 CSRF）

* 登录成功 `session_regenerate_id` 防会话固定

* 全量使用 **PDO 预处理**，杜绝 SQL 注入

* 字段白名单 `pick()` 写入，前端无法注入未知列

* `install/verify.php` 样本数据自检（重名、IP 冲突、U 位重叠等）



***

## 📌 Roadmap・路线图

### v0.2 — 数据采集 / Data Acquisition（进行中）

`v0.1` 的 `metrics.tick` 目前以随机游走生成**模拟数据**用于演示。接入真实监控时，只需将该路由改为从真实数据源读取，**前端无需任何改动**。以下能力规划在 `v0.2` 落地：



* **\[多机房支持]** 完善多机房 / 多数据中心能力（当前为单机房）：机房间切换、跨机房聚合视图、全局资产统计

* **\[传感器实时 API]** 完善温度等环境传感器的实时获取 API：真实数据上报、阈值告警、历史曲线

* **\[交换机 SNMP]** 完善针对交换机的 SNMP 开发：端口状态、流量、链路、发现与拓扑

* **\[跨系统探针]** 完善针对跨系统的探针开发：Zabbix / Prometheus 采集适配、标准探针协议、统一上报



```
┌───────────────────────────────────────────────────────────────┐
│                      v0.2 数据采集架构（规划）                    │
│                                                                │
│   ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌───────────┐   │
│   │ 传感器探针  │  │ SNMP 探针  │  │ 服务器探针  │  │ 跨系统适配  │   │
│   │ 温度/湿度   │  │ 交换机/网络 │  │ CPU/内存/IO│  │ Zabbix/PRM│   │
│   └─────┬─────┘  └─────┬─────┘  └─────┬─────┘  └─────┬─────┘   │
│         └──────────────┴──────────────┴──────────────┘         │
│                          │  统一上报协议                          │
│                    ┌─────▼────────────────────┐                 │
│                    │  data-ingest（接收+入库）   │                 │
│                    └─────┬────────────────────┘                 │
│                          ▼                                     │
│                    metrics / alerts（现成大屏直接消费）             │
└───────────────────────────────────────────────────────────────┘
```

### 后续迭代 / Later



* 跨机房统一拓扑与网络链路可视化

* 设备生命周期 / 维护计划 / 工单

* 权限分级（RBAC，多角色）

* 告警通知通道（邮件 / 企业微信 / Webhook）

* 3D 场景本地化（去除 CDN 依赖，离线部署）



***

## 📁 Directory Structure・目录结构



```
config/config.php          数据库与应用配置
lib/db.php                 PDO 封装（MySQL / SQLite 双驱动）
lib/auth.php               会话登录 · CSRF · IP 限速
lib/catalog.php            设备类型、操作系统、传感器等目录
lib/helpers.php            U 位冲突检测、机柜统计、导航渲染
lib/xlsx.php               自研 Excel 读写
api/index.php              REST 单入口（?r=resource.action）
partials/head.php          页面公共头部
partials/icons.php         SVG 图标雪碧图
partials/dashboard.php     大屏画面
assets/css/app.css         全局样式
assets/js/common.js        API 封装、图标、模态框、图表
assets/js/planner.js       平面图画板
assets/js/rackview.js      42U 机柜立面编辑器
assets/js/dashboard.js     运维大屏面板
assets/js/scene3d.js       三维机房场景
install/schema.*.sql       建表脚本（MySQL / SQLite）
install/seed.php           样本数据生成
install/cli.php            命令行安装
install/verify.php         样本数据自检
```



***

## 💡 Notes・几点说明



* **三维场景依赖 CDN**：`scene3d.js` 从 jsDelivr /unpkg 多源依次加载 Three.js；内网可下载到本地并修改 `CDN_LIST` 首项；加载失败自动回退平面图。

* **指标落库节流**：大屏每 5 秒拉取 `metrics.tick`，但仅当距上次落库超过 60 秒才写入历史，且只保留最近 4 小时，避免高频轮询撑大表。

* **坐标单位**：平面图一律使用厘米，机柜 `x` / `y` 为柜体中心，`rotation` 取 0/90/180/270。

* **U 位编号**：U1 在机柜底部，向上递增。



***

## 📄 License・开源许可

*待补充（Add your license here）*



***

**RackSentinel・让每一 U 空间一目了然 / Every rack unit, crystal clear.**

Made with ❤️ · PHP · SQLite/MySQL · Vanilla JS · Three.js