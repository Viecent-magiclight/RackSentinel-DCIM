<?php
/**
 * SNMP 轮询结果落库：接口、传感器、设备 SNMP 元数据。
 */

function ingest_save_snmp_meta(int $deviceId, array $sys, ?string $profileId = null): void
{
    if (!table_exists('device_snmp_meta')) {
        return;
    }
    $oid = (string) arr_get($sys, 'sysObjectID', '');
    $row = fetch_one('SELECT device_id FROM device_snmp_meta WHERE device_id = ?', [$deviceId]);
    $data = [
        'sys_object_id' => $oid ?: null,
        'sys_name'      => (string) arr_get($sys, 'sysName', ''),
        'sys_descr'     => substr((string) arr_get($sys, 'sysDescr', ''), 0, 500),
        'profile_id'    => $profileId,
        'last_poll_at'  => now_str(),
    ];
    if ($row) {
        update_row('device_snmp_meta', $deviceId, $data);
    } else {
        $data['device_id'] = $deviceId;
        insert_row('device_snmp_meta', $data);
    }
    if ($profileId && $oid) {
        update_row('devices', $deviceId, ['snmp_profile' => $profileId]);
    }
}

function ingest_save_interfaces(int $deviceId, array $ifaces): void
{
    if (!table_exists('device_interfaces') || !$ifaces) {
        return;
    }
    $now = now_str();
    foreach ($ifaces as $if) {
        if (!is_array($if)) {
            continue;
        }
        $idx = (int) arr_get($if, 'if_index', 0);
        if ($idx <= 0) {
            continue;
        }
        $existing = fetch_one(
            'SELECT id FROM device_interfaces WHERE device_id = ? AND if_index = ?',
            [$deviceId, $idx]
        );
        $data = [
            'name'          => (string) arr_get($if, 'name', ''),
            'alias'         => (string) arr_get($if, 'alias', ''),
            'admin_status'  => (string) arr_get($if, 'admin_status', ''),
            'oper_status'   => (string) arr_get($if, 'oper_status', ''),
            'speed_mbps'    => (int) arr_get($if, 'speed_mbps', 0),
            'in_octets'     => (string) arr_get($if, 'in_octets', '0'),
            'out_octets'    => (string) arr_get($if, 'out_octets', '0'),
            'in_errors'     => (int) arr_get($if, 'in_errors', 0),
            'out_errors'    => (int) arr_get($if, 'out_errors', 0),
            'in_rate_mbps'  => (float) arr_get($if, 'in_rate_mbps', 0),
            'out_rate_mbps' => (float) arr_get($if, 'out_rate_mbps', 0),
            'recorded_at'   => $now,
        ];
        if ($existing) {
            update_row('device_interfaces', (int) $existing['id'], $data);
        } else {
            $data['device_id'] = $deviceId;
            $data['if_index']  = $idx;
            insert_row('device_interfaces', $data);
        }
    }
}

function ingest_save_sensors(int $deviceId, array $sensors): void
{
    if (!table_exists('device_sensors') || !$sensors) {
        return;
    }
    $now = now_str();
    foreach ($sensors as $s) {
        if (!is_array($s)) {
            continue;
        }
        $key = (string) arr_get($s, 'sensor_key', '');
        if ($key === '') {
            $key = (string) arr_get($s, 'label', 'sensor');
        }
        $existing = fetch_one(
            'SELECT id FROM device_sensors WHERE device_id = ? AND sensor_key = ?',
            [$deviceId, $key]
        );
        $data = [
            'label'       => (string) arr_get($s, 'label', $key),
            'kind'        => (string) arr_get($s, 'kind', 'other'),
            'value'       => (float) arr_get($s, 'value', 0),
            'unit'        => (string) arr_get($s, 'unit', ''),
            'status'      => (string) arr_get($s, 'status', 'normal'),
            'recorded_at' => $now,
        ];
        if ($existing) {
            update_row('device_sensors', (int) $existing['id'], $data);
        } else {
            $data['device_id']   = $deviceId;
            $data['sensor_key']  = $key;
            insert_row('device_sensors', $data);
        }
    }
}

function monitor_device_detail(int $deviceId): ?array
{
    $d = fetch_one(
        'SELECT d.*, r.name AS rack_name, r.room_id FROM devices d JOIN racks r ON r.id = d.rack_id WHERE d.id = ?',
        [$deviceId]
    );
    if (!$d) {
        return null;
    }
    $d['metrics'] = array_reverse(fetch_all(
        'SELECT cpu, mem, disk, temp, net_in, net_out, power, recorded_at FROM device_metrics WHERE device_id = ? ORDER BY id DESC LIMIT 48',
        [$deviceId]
    ));
    $d['interfaces'] = table_exists('device_interfaces')
        ? fetch_all('SELECT * FROM device_interfaces WHERE device_id = ? ORDER BY if_index', [$deviceId])
        : [];
    $d['hw_sensors'] = table_exists('device_sensors')
        ? fetch_all('SELECT * FROM device_sensors WHERE device_id = ? ORDER BY sensor_key', [$deviceId])
        : [];
    $d['snmp_meta'] = table_exists('device_snmp_meta')
        ? fetch_one('SELECT * FROM device_snmp_meta WHERE device_id = ?', [$deviceId])
        : null;
    $d['alerts'] = fetch_all(
        'SELECT * FROM alerts WHERE device_id = ? ORDER BY created_at DESC LIMIT 20',
        [$deviceId]
    );
    return $d;
}
