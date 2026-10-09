# 机房机柜资产与运维管理平台（PHP + MySQL）

一套可自绘机房平面图、按 U 位管理机柜设备、并以大屏展示实时运维状态的 DCIM 系统。
纯 PHP + PDO + 原生 JS，无需 Composer 与前端构建，解压即可部署。

## 功能

| 页面 | 说明 |
| --- | --- |
| `index.php` 运维大屏 | 3D 机房 / 平面图双视图、设备状态统计、容量与功率、实时告警、负载趋势，默认每 5 秒轮询一次 |
| `planner.php` 平面图画板 | 自由绘制墙体、玻璃隔断、门、窗、柱子；放置机柜与环境传感器；网格吸附、撤销重做 |
| `racks.php` 机柜 U 位 | 整列机柜并排的 42U 立面图，拖动设备改 U 位或跨柜搬迁，维护设备名称 / 主机名 / IP |
| `devices.php` 设备清单 | 按名称、主机名、IP、型号、资产号搜索，按类型 / 操作系统 / 状态 / 机柜筛选 |

设备图标按类型区分（服务器、刀片、交换机、路由器、防火墙、网关、负载均衡、存储、磁带库、
UPS、PDU、KVM、控制台、配线架、盲板），服务器另叠加操作系统图标
（Windows / Ubuntu / CentOS / RHEL / Debian / 麒麟 / ESXi / Cisco IOS / 华为 VRP 等）。

## 安装

### 1. 环境

- PHP 8.0+，启用 `pdo_mysql`（本地试用也可用 `pdo_sqlite`）
- MySQL 5.7+ / 8.0
- 任意 Web 服务器，站点根目录指向本文件夹

### 2. 配置数据库

编辑 `config/config.php`：

```php
'mysql' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'server_rack',
    'username' => 'root',
    'password' => '你的密码',
    'charset'  => 'utf8mb4',
],
```

账号需要有 `CREATE DATABASE` 权限；若数据库已由管理员建好，则只需 `CREATE TABLE` 权限。

### 3. 执行安装

浏览器打开 `install.php`，点「开始安装并写入样本数据」。
安装会建库建表，并灌入 1 个机房、14 处平面图元、18 个机柜、约 260 台设备、10 个传感器、
指标历史与告警样本。

命令行安装（适合远程服务器）：

```bash
php install/cli.php
```

> 安装脚本会先 `DROP TABLE` 再重建，**重装会清空现有数据**。

### 4. 访问

打开 `index.php` 即为运维大屏。

## 不装 MySQL 先试用

设置环境变量即可切换到 SQLite 文件库，数据写在 `storage/server_rack.sqlite`：

```powershell
$env:RACK_DB_DRIVER = "sqlite"
php install/cli.php
php -S 127.0.0.1:8123 -t .
```

```bash
RACK_DB_DRIVER=sqlite php install/cli.php
RACK_DB_DRIVER=sqlite php -S 127.0.0.1:8123 -t .
```

两种驱动表结构字段完全一致，验证完可直接切回 MySQL。

## 目录结构

```
config/config.php          数据库与应用配置
lib/db.php                 PDO 封装（MySQL / SQLite 双驱动）
lib/catalog.php            设备类型、操作系统、传感器等目录定义
lib/helpers.php            U 位冲突检测、机柜统计、导航渲染
api/index.php              REST 单入口（?r=resource.action）
partials/head.php          页面公共头部
partials/icons.php         SVG 图标雪碧图
assets/css/app.css         全局样式
assets/js/common.js        API 封装、图标、模态框、图表
assets/js/planner.js       平面图画板
assets/js/rackview.js      42U 机柜立面编辑器
assets/js/dashboard.js     运维大屏面板
assets/js/scene3d.js       三维机房场景
install/schema.*.sql       建表脚本
install/seed.php           样本数据生成
install/cli.php            命令行安装
install/stats.php          查看各表记录数与机柜概况
install/verify.php         样本数据自检（重名、IP 冲突、U 位重叠等）
```

## 接口

统一入口 `api/index.php?r=<resource>.<action>`，读取用 GET、写入用 POST + JSON body。

| 路由 | 说明 |
| --- | --- |
| `meta` | 设备类型 / 操作系统 / 状态等目录 |
| `layout.get` / `layout.save` | 读取、保存整张平面图（墙体、机柜、传感器） |
| `rack.list` / `rack.get` / `rack.save` / `rack.delete` | 机柜增删改查 |
| `device.list` / `device.get` / `device.save` / `device.move` / `device.delete` | 设备增删改查与 U 位搬迁 |
| `dashboard.summary` | 大屏所需的汇总数据 |
| `metrics.tick` | 推进一轮实时指标 |
| `alert.list` / `alert.ack` | 告警列表与确认 |

`device.save` 与 `device.move` 会校验 U 位是否越界或与其它设备重叠，冲突时返回 HTTP 422
并说明被哪台设备占用。

## 几点说明

- **三维场景依赖 CDN**：`scene3d.js` 从 jsDelivr / unpkg 等多个源依次尝试加载 Three.js。
  内网环境可把 `three.module.js` 放到本地，并将 `CDN_LIST` 第一项改成本地路径；
  加载失败时大屏会自动回退到平面图模式，不影响其它功能。
- **指标落库节流**：大屏每 5 秒拉一次 `metrics.tick`，但只有距上次落库超过 60 秒才写
  `device_metrics`，并且只保留最近 4 小时，避免高频轮询撑大表。
- **模拟数据**：`metrics.tick` 在当前值基础上做随机游走，用于演示。接入真实监控时，
  把该路由改成从 Zabbix / Prometheus / SNMP 读取即可，前端无需改动。
- **坐标单位**：平面图一律使用厘米，机柜 `x` / `y` 为柜体中心点，`rotation` 取 0/90/180/270。
- **U 位编号**：U1 在机柜底部，向上递增。
