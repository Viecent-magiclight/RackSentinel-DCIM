<?php
/**
 * 设备类型 / 操作系统 目录
 * icon 字段对应 partials/icons.php 中的 <symbol id="ico-xxx">
 */

function device_types(): array
{
    static $t = [
        'server'       => ['name' => '服务器',     'icon' => 'server',   'color' => '#2ef2a3', 'u' => 2, 'os' => true],
        'blade'        => ['name' => '刀片机箱',   'icon' => 'blade',    'color' => '#4ade80', 'u' => 7, 'os' => true],
        'switch'       => ['name' => '交换机',     'icon' => 'switch',   'color' => '#35c6ff', 'u' => 1, 'os' => true],
        'router'       => ['name' => '路由器',     'icon' => 'router',   'color' => '#7c8cff', 'u' => 1, 'os' => true],
        'firewall'     => ['name' => '防火墙',     'icon' => 'firewall', 'color' => '#ff6b57', 'u' => 1, 'os' => true],
        'gateway'      => ['name' => '网关',       'icon' => 'gateway',  'color' => '#ffb020', 'u' => 1, 'os' => true],
        'loadbalancer' => ['name' => '负载均衡',   'icon' => 'lb',       'color' => '#00e0c0', 'u' => 1, 'os' => true],
        'storage'      => ['name' => '存储阵列',   'icon' => 'storage',  'color' => '#a06bff', 'u' => 4, 'os' => false],
        'tape'         => ['name' => '磁带库',     'icon' => 'tape',     'color' => '#b08968', 'u' => 4, 'os' => false],
        'ups'          => ['name' => 'UPS 电源',   'icon' => 'ups',      'color' => '#ffd166', 'u' => 3, 'os' => false],
        'pdu'          => ['name' => 'PDU 配电',   'icon' => 'pdu',      'color' => '#f4a261', 'u' => 1, 'os' => false],
        'kvm'          => ['name' => 'KVM 切换器', 'icon' => 'kvm',      'color' => '#8fa6b8', 'u' => 1, 'os' => false],
        'console'      => ['name' => '控制台',     'icon' => 'console',  'color' => '#9fb8c8', 'u' => 1, 'os' => false],
        'patch'        => ['name' => '配线架',     'icon' => 'patch',    'color' => '#5d7f92', 'u' => 1, 'os' => false],
        'blank'        => ['name' => '盲板',       'icon' => 'blank',    'color' => '#3b5468', 'u' => 1, 'os' => false],
    ];
    return $t;
}

function os_types(): array
{
    static $o = [
        'winserver' => ['name' => 'Windows Server', 'icon' => 'os-windows',  'color' => '#00a4ef'],
        'windows'   => ['name' => 'Windows 10/11',  'icon' => 'os-windows',  'color' => '#0078d4'],
        'ubuntu'    => ['name' => 'Ubuntu Server',  'icon' => 'os-ubuntu',   'color' => '#e95420'],
        'centos'    => ['name' => 'CentOS',         'icon' => 'os-centos',   'color' => '#932279'],
        'rhel'      => ['name' => 'Red Hat RHEL',   'icon' => 'os-redhat',   'color' => '#ee0000'],
        'debian'    => ['name' => 'Debian',         'icon' => 'os-debian',   'color' => '#d70a53'],
        'suse'      => ['name' => 'openSUSE',       'icon' => 'os-linux',    'color' => '#73ba25'],
        'kylin'     => ['name' => '银河麒麟',       'icon' => 'os-kylin',    'color' => '#c7161e'],
        'uos'       => ['name' => '统信 UOS',       'icon' => 'os-kylin',    'color' => '#e8511d'],
        'esxi'      => ['name' => 'VMware ESXi',    'icon' => 'os-vmware',   'color' => '#78be20'],
        'hyperv'    => ['name' => 'Hyper-V',        'icon' => 'os-windows',  'color' => '#0078d4'],
        'proxmox'   => ['name' => 'Proxmox VE',     'icon' => 'os-linux',    'color' => '#e57000'],
        'freebsd'   => ['name' => 'FreeBSD',        'icon' => 'os-freebsd',  'color' => '#ab2b28'],
        'ciscoios'  => ['name' => 'Cisco IOS',      'icon' => 'os-network',  'color' => '#1ba0d7'],
        'vrp'       => ['name' => '华为 VRP',       'icon' => 'os-network',  'color' => '#e40613'],
        'comware'   => ['name' => 'H3C Comware',    'icon' => 'os-network',  'color' => '#0068b7'],
        'rgos'      => ['name' => '锐捷 RGOS',      'icon' => 'os-network',  'color' => '#0b4ea2'],
        'eos'       => ['name' => 'Arista EOS',     'icon' => 'os-network',  'color' => '#2b9ad6'],
        'junos'     => ['name' => 'Juniper Junos',  'icon' => 'os-network',  'color' => '#0f6fb8'],
        'openwrt'   => ['name' => 'OpenWrt',        'icon' => 'os-linux',    'color' => '#00b5e2'],
        'appliance' => ['name' => '专用固件',       'icon' => 'os-chip',     'color' => '#8fa6b8'],
        'none'      => ['name' => '无 / 不适用',    'icon' => 'os-chip',     'color' => '#5d7f92'],
    ];
    return $o;
}

/**
 * 会上报 CPU / 内存 / 磁盘的设备类型。
 * PDU、UPS、KVM、配线架这类设备只有功耗和温度，给它们编造 CPU 利用率会让大屏失真。
 */
function cpu_type_keys(): array
{
    return ['server', 'blade', 'switch', 'router', 'firewall', 'gateway', 'loadbalancer', 'storage'];
}

/** 完全不接入监控、无任何指标的类型 */
function passive_type_keys(): array
{
    return ['blank', 'patch'];
}

function device_statuses(): array
{
    return [
        'online'      => ['name' => '在线运行', 'color' => '#2ef2a3'],
        'warning'     => ['name' => '告警',     'color' => '#ffb020'],
        'offline'     => ['name' => '离线',     'color' => '#ff4d6a'],
        'maintenance' => ['name' => '维护中',   'color' => '#7c8cff'],
    ];
}

function wall_kinds(): array
{
    return [
        'wall'   => ['name' => '墙体',   'color' => '#4a7f9e', 'thickness' => 24],
        'glass'  => ['name' => '玻璃隔断', 'color' => '#49c7ea', 'thickness' => 12],
        'door'   => ['name' => '门',     'color' => '#ffb020', 'thickness' => 12],
        'window' => ['name' => '窗',     'color' => '#7dd3fc', 'thickness' => 12],
        'pillar' => ['name' => '柱子',   'color' => '#64748b', 'thickness' => 50],
    ];
}

function sensor_kinds(): array
{
    return [
        'temp'     => ['name' => '温度',   'unit' => '℃',   'icon' => 'sn-temp'],
        'humidity' => ['name' => '湿度',   'unit' => '%',    'icon' => 'sn-humi'],
        'smoke'    => ['name' => '烟感',   'unit' => '',     'icon' => 'sn-smoke'],
        'water'    => ['name' => '漏水',   'unit' => '',     'icon' => 'sn-water'],
        'power'    => ['name' => '配电',   'unit' => 'kW',   'icon' => 'sn-power'],
    ];
}

function type_meta(string $key): array
{
    return device_types()[$key] ?? ['name' => $key, 'icon' => 'server', 'color' => '#8fa6b8', 'u' => 1, 'os' => false];
}

function os_meta(?string $key): array
{
    $key = $key ?: 'none';
    return os_types()[$key] ?? os_types()['none'];
}

function status_meta(string $key): array
{
    return device_statuses()[$key] ?? ['name' => $key, 'color' => '#8fa6b8'];
}

/** 输出给前端 JS 使用的目录 JSON */
function catalog_json(): string
{
    return json_encode([
        'types'    => device_types(),
        'os'       => os_types(),
        'statuses' => device_statuses(),
        'walls'    => wall_kinds(),
        'sensors'  => sensor_kinds(),
    ], JSON_UNESCAPED_UNICODE);
}
