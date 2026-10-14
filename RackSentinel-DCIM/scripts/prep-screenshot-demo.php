<?php
/**
 * Prepare DB for README screenshots: real metrics, SNMP detail, alerts.
 *   RACK_DB_DRIVER=sqlite php scripts/prep-screenshot-demo.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
putenv('RACK_DB_DRIVER=sqlite');
require $root . '/lib/db.php';
require $root . '/lib/helpers.php';
require $root . '/lib/settings.php';
require $root . '/lib/migrate.php';
require $root . '/lib/ingest.php';
require $root . '/lib/ingest_snmp.php';

run_migrations();

q('DELETE FROM monitor_agents WHERE name IN (?, ?)', ['preview-host', 'preview-snmp']);
settings_save(['metrics_simulation' => '0']);

$hostDev = fetch_one(
    "SELECT id, name FROM devices WHERE type_key = 'server' ORDER BY id LIMIT 1"
);
$snmpDev = fetch_one(
    "SELECT id, name FROM devices WHERE type_key = 'switch' ORDER BY id LIMIT 1"
);
if (!$hostDev || !$snmpDev) {
    fwrite(STDERR, "Need seeded devices. Run install/cli.php first.\n");
    exit(1);
}
$hostId = (int) $hostDev['id'];
$snmpId = (int) $snmpDev['id'];

update_row('devices', $hostId, [
    'monitor_mode' => 'agent',
    'status'       => 'online',
]);
update_row('devices', $snmpId, [
    'monitor_mode'  => 'snmp',
    'snmp_enabled'  => 1,
    'snmp_version'  => '2c',
    'snmp_community'=> 'demo',
    'snmp_port'     => 161,
    'status'        => 'online',
]);

// Host agent ingest (inline)
$hostPlain = ingest_new_agent_token();
insert_row('monitor_agents', [
    'name'         => 'preview-host',
    'kind'         => 'host',
    'device_id'    => $hostId,
    'token_hash'   => ingest_hash_token($hostPlain),
    'token_prefix' => ingest_token_prefix($hostPlain),
    'scopes_json'  => json_encode(['ingest:device']),
    'enabled'      => 1,
]);
ingest_record_metrics($hostId, [
    'cpu' => 38.2, 'mem' => 61.5, 'disk' => 54.0, 'temp' => 42.0,
    'net_in' => 1200000, 'net_out' => 890000, 'power' => 320,
    'hostname' => 'app01.a01.dc-a.local', 'ip' => '10.20.1.20',
], true);
q('UPDATE monitor_agents SET last_seen_at = ? WHERE name = ?', [now_str(), 'preview-host']);

// SNMP report (inline)
$sys = [
    'sysName' => 'SW-A01-01',
    'sysDescr' => 'Huawei Versatile Routing Platform Software',
    'sysObjectID' => '1.3.6.1.4.1.2011.2.23.1',
];
ingest_save_snmp_meta($snmpId, $sys, 'huawei-switch');
ingest_save_interfaces($snmpId, [
    [
        'if_index' => 1, 'name' => 'GigabitEthernet0/0/1', 'alias' => 'uplink-core',
        'admin_status' => 'up', 'oper_status' => 'up', 'speed_mbps' => 10000,
        'in_octets' => '18446744073709551615', 'out_octets' => '9000000000',
        'in_rate_mbps' => 124.5, 'out_rate_mbps' => 88.2, 'in_errors' => 0, 'out_errors' => 0,
    ],
    [
        'if_index' => 2, 'name' => 'GigabitEthernet0/0/2', 'alias' => 'srv-a01',
        'admin_status' => 'up', 'oper_status' => 'up', 'speed_mbps' => 1000,
        'in_rate_mbps' => 12.4, 'out_rate_mbps' => 45.1,
    ],
    [
        'if_index' => 3, 'name' => 'GigabitEthernet0/0/3', 'alias' => 'mgmt',
        'admin_status' => 'up', 'oper_status' => 'down', 'speed_mbps' => 1000,
        'in_rate_mbps' => 0, 'out_rate_mbps' => 0,
    ],
]);
ingest_save_sensors($snmpId, [
    ['sensor_key' => 'temp-inlet', 'label' => '进风口温度', 'kind' => 'temp', 'value' => 28.5, 'unit' => '℃', 'status' => 'normal'],
    ['sensor_key' => 'temp-exhaust', 'label' => '出风口温度', 'kind' => 'temp', 'value' => 41.2, 'unit' => '℃', 'status' => 'warning'],
    ['sensor_key' => 'fan-1', 'label' => '风扇 1', 'kind' => 'fan', 'value' => 4200, 'unit' => 'RPM', 'status' => 'normal'],
]);
for ($i = 0; $i < 12; $i++) {
    $cpu = 18 + $i * 0.6 + ($i % 3);
    $mem = 44 + ($i % 4) * 1.2;
    q(
        'INSERT INTO device_metrics (device_id, cpu, mem, disk, temp, net_in, net_out, power, recorded_at)
         VALUES (?, ?, ?, 52, 40, 0, 0, 180, datetime("now", ?))',
        [$snmpId, $cpu, $mem, '-' . ($i * 5) . ' minutes']
    );
}
ingest_record_metrics($snmpId, ['cpu' => 22.0, 'mem' => 48.0, 'temp' => 41.2], true);

// Extra alerts for alerts.php
foreach ([
    ['warning', 'CPU 使用率偏高', "设备 {$hostDev['name']} CPU 超过预警阈值"],
    ['critical', '磁盘空间不足', '根分区可用空间 < 10%'],
    ['info', 'SNMP 轮询恢复', "交换机 {$snmpDev['name']} 恢复在线"],
] as [$lvl, $title, $detail]) {
    ingest_create_alert($hostId, $lvl, $title, $detail);
}

// Notifier demo row on monitor page
if (!fetch_one('SELECT id FROM notification_recipients LIMIT 1')) {
    insert_row('notification_recipients', [
        'name' => '值班工程师', 'phone' => '+8613800138000', 'sms_phone' => '+8613800138000',
        'min_level' => 'warning', 'enabled' => 1, 'escalate_min' => 5,
    ]);
}

file_put_contents($root . '/storage/.screenshot-device-id', (string) $snmpId);

echo "Host device id: $hostId ({$hostDev['name']})\n";
echo "SNMP / device-monitor id: $snmpId ({$snmpDev['name']})\n";
echo "metrics_simulation=0\n";
