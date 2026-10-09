<?php
/**
 * REST 单入口：api/index.php?r=<resource>.<action>
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/auth.php';

if (!db_installed()) {
    json_out(['error' => '系统尚未安装，请先访问 install.php'], 503);
}
require_login_api();
require_same_origin();

$route = (string) param('r', '');
$in    = json_in();

try {
    switch ($route) {

        /* ---------------- 目录 ---------------- */
        case 'meta':
            json_out([
                'types'    => device_types(),
                'os'       => os_types(),
                'statuses' => device_statuses(),
                'walls'    => wall_kinds(),
                'sensors'  => sensor_kinds(),
            ]);

        /* ---------------- 机房 ---------------- */
        case 'room.list':
            json_out(fetch_all('SELECT * FROM rooms ORDER BY id'));

        case 'room.save': {
            $data = pick($in, ['code', 'name', 'width', 'depth', 'grid', 'wall_height', 'note']);
            $id   = (int) arr_get($in, 'id', 0);
            if ($id) {
                update_row('rooms', $id, $data);
            } else {
                $data['code'] = $data['code'] ?: ('ROOM-' . date('ymdHis'));
                $data['name'] = $data['name'] ?: '新建机房';
                $id = insert_row('rooms', $data);
            }
            json_out(['ok' => true, 'id' => $id]);
        }

        /* ---------------- 平面布局 ---------------- */
        case 'layout.get': {
            $roomId = int_param('room_id');
            $room   = $roomId
                ? fetch_one('SELECT * FROM rooms WHERE id = ?', [$roomId])
                : fetch_one('SELECT * FROM rooms ORDER BY id LIMIT 1');
            if (!$room) {
                json_out(['error' => '机房不存在'], 404);
            }
            $rid   = (int) $room['id'];
            $racks = fetch_all('SELECT * FROM racks WHERE room_id = ? ORDER BY name', [$rid]);
            foreach ($racks as &$r) {
                $r['stats'] = rack_stats((int) $r['id']);
            }
            unset($r);
            json_out([
                'room'    => $room,
                'walls'   => fetch_all('SELECT * FROM walls WHERE room_id = ? ORDER BY sort_order, id', [$rid]),
                'racks'   => $racks,
                'sensors' => fetch_all('SELECT * FROM sensors WHERE room_id = ? ORDER BY id', [$rid]),
            ]);
        }

        case 'layout.save': {
            $roomId = (int) arr_get($in, 'room_id', 0);
            if (!$roomId) {
                json_out(['error' => '缺少 room_id'], 400);
            }
            $pdo = db();
            $pdo->beginTransaction();

            if (!empty($in['room']) && is_array($in['room'])) {
                $rd = pick($in['room'], ['name', 'width', 'depth', 'grid', 'wall_height', 'note']);
                if ($rd) {
                    update_row('rooms', $roomId, $rd);
                }
            }

            // 墙体：全量替换
            if (isset($in['walls']) && is_array($in['walls'])) {
                q('DELETE FROM walls WHERE room_id = ?', [$roomId]);
                foreach ($in['walls'] as $i => $w) {
                    insert_row('walls', [
                        'room_id'    => $roomId,
                        'kind'       => (string) arr_get($w, 'kind', 'wall'),
                        'x1'         => (int) arr_get($w, 'x1', 0),
                        'y1'         => (int) arr_get($w, 'y1', 0),
                        'x2'         => (int) arr_get($w, 'x2', 0),
                        'y2'         => (int) arr_get($w, 'y2', 0),
                        'thickness'  => (int) arr_get($w, 'thickness', 24),
                        'curve'      => (int) arr_get($w, 'curve', 0),
                        'sort_order' => $i,
                    ]);
                }
            }

            // 传感器：全量替换
            if (isset($in['sensors']) && is_array($in['sensors'])) {
                q('DELETE FROM sensors WHERE room_id = ?', [$roomId]);
                foreach ($in['sensors'] as $s) {
                    insert_row('sensors', [
                        'room_id' => $roomId,
                        'name'    => (string) arr_get($s, 'name', '传感器'),
                        'kind'    => (string) arr_get($s, 'kind', 'temp'),
                        'x'       => (int) arr_get($s, 'x', 0),
                        'y'       => (int) arr_get($s, 'y', 0),
                        'value'   => (float) arr_get($s, 'value', 0),
                        'unit'    => (string) arr_get($s, 'unit', ''),
                        'status'  => (string) arr_get($s, 'status', 'normal'),
                    ]);
                }
            }

            // 机柜：增量同步（删除机柜会连带删除其中设备）
            $idMap = [];
            if (isset($in['racks']) && is_array($in['racks'])) {
                $keep = [];
                foreach ($in['racks'] as $r) {
                    $data = [
                        'name'           => (string) arr_get($r, 'name', 'RACK'),
                        'label'          => arr_get($r, 'label'),
                        'row_label'      => arr_get($r, 'row_label'),
                        'x'              => (int) arr_get($r, 'x', 0),
                        'y'              => (int) arr_get($r, 'y', 0),
                        'width'          => (int) arr_get($r, 'width', 60),
                        'depth'          => (int) arr_get($r, 'depth', 110),
                        'rotation'       => (int) arr_get($r, 'rotation', 0),
                        'u_total'        => (int) arr_get($r, 'u_total', 42),
                        'power_capacity' => (int) arr_get($r, 'power_capacity', 6000),
                        'note'           => arr_get($r, 'note'),
                    ];
                    $rid = (int) arr_get($r, 'id', 0);
                    if ($rid > 0) {
                        update_row('racks', $rid, $data);
                        $keep[] = $rid;
                    } else {
                        $data['room_id'] = $roomId;
                        $newId = insert_row('racks', $data);
                        $keep[] = $newId;
                        $idMap[(string) arr_get($r, 'tmp_id', $data['name'])] = $newId;
                    }
                }
                $existing = array_column(fetch_all('SELECT id FROM racks WHERE room_id = ?', [$roomId]), 'id');
                foreach ($existing as $eid) {
                    if (!in_array((int) $eid, $keep, true)) {
                        delete_row('racks', (int) $eid);
                    }
                }
            }

            $pdo->commit();
            json_out(['ok' => true, 'id_map' => $idMap]);
        }

        /* ---------------- 机柜 ---------------- */
        case 'rack.list': {
            $roomId = int_param('room_id');
            $sql    = 'SELECT * FROM racks' . ($roomId ? ' WHERE room_id = ?' : '') . ' ORDER BY name';
            $racks  = fetch_all($sql, $roomId ? [$roomId] : []);
            foreach ($racks as &$r) {
                $r['stats'] = rack_stats((int) $r['id']);
            }
            json_out($racks);
        }

        case 'rack.get': {
            $id   = int_param('id');
            $rack = fetch_one('SELECT * FROM racks WHERE id = ?', [$id]);
            if (!$rack) {
                json_out(['error' => '机柜不存在'], 404);
            }
            $rack['stats']   = rack_stats($id);
            $rack['devices'] = fetch_all(
                'SELECT d.*, (SELECT cpu  FROM device_metrics m WHERE m.device_id = d.id ORDER BY m.id DESC LIMIT 1) AS cpu,
                             (SELECT mem  FROM device_metrics m WHERE m.device_id = d.id ORDER BY m.id DESC LIMIT 1) AS mem,
                             (SELECT temp FROM device_metrics m WHERE m.device_id = d.id ORDER BY m.id DESC LIMIT 1) AS temp
                 FROM devices d WHERE d.rack_id = ? ORDER BY d.u_start DESC',
                [$id]
            );
            json_out($rack);
        }

        case 'rack.save': {
            $data = pick($in, ['room_id', 'name', 'label', 'row_label', 'x', 'y', 'width', 'depth',
                               'rotation', 'u_total', 'power_capacity', 'note']);
            $id = (int) arr_get($in, 'id', 0);
            if ($id) {
                unset($data['room_id']);
                update_row('racks', $id, $data);
            } else {
                $id = insert_row('racks', $data);
            }
            json_out(['ok' => true, 'id' => $id]);
        }

        case 'rack.delete':
            delete_row('racks', int_param('id'));
            json_out(['ok' => true]);

        /* ---------------- 设备 ---------------- */
        case 'device.list': {
            $where = [];
            $args  = [];
            if ($v = param('rack_id')) { $where[] = 'd.rack_id = ?';  $args[] = (int) $v; }
            if ($v = param('type'))    { $where[] = 'd.type_key = ?'; $args[] = $v; }
            if ($v = param('os'))      { $where[] = 'd.os_key = ?';   $args[] = $v; }
            if ($v = param('status'))  { $where[] = 'd.status = ?';   $args[] = $v; }
            if ($kw = trim((string) param('q', ''))) {
                $where[] = '(d.name LIKE ? OR d.hostname LIKE ? OR d.ip LIKE ? OR d.mgmt_ip LIKE ? OR d.model LIKE ? OR d.asset_tag LIKE ?)';
                array_push($args, ...array_fill(0, 6, "%$kw%"));
            }
            $sql = 'SELECT d.*, r.name AS rack_name, r.row_label
                    FROM devices d JOIN racks r ON r.id = d.rack_id';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY r.name, d.u_start DESC';
            $limit = max(1, min(2000, int_param('limit', 500)));
            $sql  .= ' LIMIT ' . $limit;
            json_out(fetch_all($sql, $args));
        }

        case 'device.get': {
            $id = int_param('id');
            $d  = fetch_one(
                'SELECT d.*, r.name AS rack_name, r.room_id FROM devices d
                 JOIN racks r ON r.id = d.rack_id WHERE d.id = ?',
                [$id]
            );
            if (!$d) {
                json_out(['error' => '设备不存在'], 404);
            }
            $d['metrics'] = array_reverse(fetch_all(
                'SELECT cpu, mem, disk, temp, net_in, net_out, power, recorded_at
                 FROM device_metrics WHERE device_id = ? ORDER BY id DESC LIMIT 24',
                [$id]
            ));
            json_out($d);
        }

        case 'device.save': {
            $id      = (int) arr_get($in, 'id', 0);
            $rackId  = (int) arr_get($in, 'rack_id', 0);
            $uStart  = max(1, (int) arr_get($in, 'u_start', 1));
            $uSize   = max(1, (int) arr_get($in, 'u_size', 1));

            if (!$rackId && $id) {
                $rackId = (int) fetch_val('SELECT rack_id FROM devices WHERE id = ?', [$id], 0);
            }
            $uTotal = (int) fetch_val('SELECT u_total FROM racks WHERE id = ?', [$rackId], 42);
            if ($uStart + $uSize - 1 > $uTotal) {
                json_out(['error' => "超出机柜高度：该机柜共 {$uTotal}U"], 422);
            }
            if ($conflict = u_conflict($rackId, $uStart, $uSize, $id)) {
                json_out(['error' => "U 位冲突，已被 {$conflict} 占用"], 422);
            }

            $data = pick($in, ['name', 'hostname', 'ip', 'mgmt_ip', 'type_key', 'os_key',
                               'vendor', 'model', 'serial', 'asset_tag', 'owner', 'status', 'power_w', 'note']);
            $data['u_start'] = $uStart;
            $data['u_size']  = $uSize;
            $data['name']    = $data['name'] ?? '新设备';

            if ($id) {
                update_row('devices', $id, $data);
            } else {
                $data['rack_id'] = $rackId;
                $id = insert_row('devices', $data);
            }
            json_out(['ok' => true, 'id' => $id]);
        }

        case 'device.move': {
            $id     = (int) arr_get($in, 'id', 0);
            $rackId = (int) arr_get($in, 'rack_id', 0)
                ?: (int) fetch_val('SELECT rack_id FROM devices WHERE id = ?', [$id], 0);
            $uStart = max(1, (int) arr_get($in, 'u_start', 1));
            $uSize  = (int) fetch_val('SELECT u_size FROM devices WHERE id = ?', [$id], 1);
            $uTotal = (int) fetch_val('SELECT u_total FROM racks WHERE id = ?', [$rackId], 42);

            if ($uStart + $uSize - 1 > $uTotal) {
                json_out(['error' => '超出机柜高度'], 422);
            }
            if ($conflict = u_conflict($rackId, $uStart, $uSize, $id)) {
                json_out(['error' => "U 位冲突，已被 {$conflict} 占用"], 422);
            }
            update_row('devices', $id, ['rack_id' => $rackId, 'u_start' => $uStart]);
            json_out(['ok' => true]);
        }

        case 'device.delete':
            delete_row('devices', int_param('id'));
            json_out(['ok' => true]);

        /* ---------------- 大屏汇总 ---------------- */
        case 'dashboard.summary': {
            $roomId = int_param('room_id') ?: (int) fetch_val('SELECT id FROM rooms ORDER BY id LIMIT 1', [], 0);

            $racks = fetch_all('SELECT * FROM racks WHERE room_id = ? ORDER BY name', [$roomId]);
            foreach ($racks as &$r) {
                $r['stats'] = rack_stats((int) $r['id']);
            }
            unset($r);

            $byStatus = [];
            foreach (fetch_all(
                "SELECT d.status, COUNT(*) c FROM devices d JOIN racks r ON r.id = d.rack_id
                 WHERE r.room_id = ? AND d.type_key <> 'blank' GROUP BY d.status",
                [$roomId]
            ) as $row) {
                $byStatus[$row['status']] = (int) $row['c'];
            }

            $byType = [];
            foreach (fetch_all(
                "SELECT d.type_key, COUNT(*) c FROM devices d JOIN racks r ON r.id = d.rack_id
                 WHERE r.room_id = ? AND d.type_key <> 'blank' GROUP BY d.type_key ORDER BY c DESC",
                [$roomId]
            ) as $row) {
                $byType[$row['type_key']] = (int) $row['c'];
            }

            $byOs = [];
            foreach (fetch_all(
                "SELECT d.os_key, COUNT(*) c FROM devices d JOIN racks r ON r.id = d.rack_id
                 WHERE r.room_id = ? AND d.type_key IN ('server','blade') GROUP BY d.os_key ORDER BY c DESC",
                [$roomId]
            ) as $row) {
                $byOs[$row['os_key'] ?: 'none'] = (int) $row['c'];
            }

            $totUsedU = (int) fetch_val(
                "SELECT COALESCE(SUM(d.u_size),0) FROM devices d JOIN racks r ON r.id = d.rack_id WHERE r.room_id = ?",
                [$roomId], 0
            );
            $totU = (int) fetch_val('SELECT COALESCE(SUM(u_total),0) FROM racks WHERE room_id = ?', [$roomId], 0);

            $power = (float) fetch_val(
                "SELECT COALESCE(SUM(m.power),0) FROM devices d
                 JOIN racks r ON r.id = d.rack_id
                 JOIN device_metrics m ON m.id = (SELECT id FROM device_metrics WHERE device_id = d.id ORDER BY id DESC LIMIT 1)
                 WHERE r.room_id = ?",
                [$roomId], 0
            );

            // 只统计真正有 CPU 的设备，避免 PDU/KVM 出现在高负载榜单里
            $cpuTypes = cpu_type_keys();
            $top = fetch_all(
                "SELECT d.id, d.name, d.hostname, d.type_key, d.os_key, d.status, r.name AS rack_name,
                        m.cpu, m.mem, m.temp
                 FROM devices d
                 JOIN racks r ON r.id = d.rack_id
                 JOIN device_metrics m ON m.id = (SELECT id FROM device_metrics WHERE device_id = d.id ORDER BY id DESC LIMIT 1)
                 WHERE r.room_id = ? AND d.type_key IN (" . placeholders($cpuTypes) . ")
                 ORDER BY m.cpu DESC LIMIT 8",
                array_merge([$roomId], $cpuTypes)
            );

            json_out([
                'room'      => fetch_one('SELECT * FROM rooms WHERE id = ?', [$roomId]),
                'racks'     => $racks,
                'walls'     => fetch_all('SELECT * FROM walls WHERE room_id = ? ORDER BY sort_order, id', [$roomId]),
                'sensors'   => fetch_all('SELECT * FROM sensors WHERE room_id = ? ORDER BY id', [$roomId]),
                'by_status' => $byStatus,
                'by_type'   => $byType,
                'by_os'     => $byOs,
                'capacity'  => ['used_u' => $totUsedU, 'total_u' => $totU],
                'power_w'   => round($power),
                'top_load'  => $top,
                'alerts'    => fetch_all(
                    'SELECT a.*, d.name AS device_name, r.name AS rack_name
                     FROM alerts a
                     LEFT JOIN devices d ON d.id = a.device_id
                     LEFT JOIN racks r ON r.id = a.rack_id
                     ORDER BY a.acked, a.created_at DESC LIMIT 12'
                ),
            ]);
        }

        /* ---------------- 实时指标推进 ---------------- */
        case 'metrics.tick': {
            $roomId = int_param('room_id') ?: (int) fetch_val('SELECT id FROM rooms ORDER BY id LIMIT 1', [], 0);
            $passive = passive_type_keys();
            $rows = fetch_all(
                "SELECT d.id, d.status, d.power_w, d.type_key,
                        m.cpu, m.mem, m.disk, m.temp, m.net_in, m.net_out, m.power, m.recorded_at
                 FROM devices d
                 JOIN racks r ON r.id = d.rack_id
                 LEFT JOIN device_metrics m ON m.id = (SELECT id FROM device_metrics WHERE device_id = d.id ORDER BY id DESC LIMIT 1)
                 WHERE r.room_id = ? AND d.type_key NOT IN (" . placeholders($passive) . ')',
                array_merge([$roomId], $passive)
            );
            $cpuTypes = cpu_type_keys();

            // 距离上次落库超过 60 秒才写入历史，避免高频轮询撑大表
            $last    = (string) fetch_val('SELECT MAX(recorded_at) FROM device_metrics', [], '');
            $persist = !$last || (time() - strtotime($last)) >= 60;

            $out = [];
            $st  = $persist ? db()->prepare(
                'INSERT INTO device_metrics (device_id, cpu, mem, disk, temp, net_in, net_out, power, recorded_at)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            ) : null;
            $now = now_str();

            foreach ($rows as $r) {
                $off = $r['status'] === 'offline';
                // 配电、KVM 这类设备没有 CPU/内存，只随功耗与温度波动
                $idle = $off || !in_array($r['type_key'], $cpuTypes, true);
                $d    = fn($v, $amp, $lo, $hi) => $idle ? 0
                    : max($lo, min($hi, (float) $v + mt_rand(-$amp * 10, $amp * 10) / 10));

                $cpu  = $d($r['cpu'] ?: 20, 6, 1, 99);
                $mem  = $d($r['mem'] ?: 40, 2.5, 8, 97);
                $disk = $d($r['disk'] ?: 50, 0.3, 10, 96);
                $temp = $off ? 0 : max(22, min(62, (float) ($r['temp'] ?: 32) + mt_rand(-12, 12) / 10));
                $nIn  = $idle ? 0 : max(0, (float) ($r['net_in'] ?? 20) + mt_rand(-90, 90) / 10);
                $nOut = $idle ? 0 : max(0, (float) ($r['net_out'] ?? 15) + mt_rand(-80, 80) / 10);
                $pw   = $off ? 0 : (float) $r['power_w'] * mt_rand(72, 110) / 100;

                $out[] = [
                    'id' => (int) $r['id'],
                    'cpu' => round($cpu, 1), 'mem' => round($mem, 1), 'disk' => round($disk, 1),
                    'temp' => round($temp, 1), 'net_in' => round($nIn, 1), 'net_out' => round($nOut, 1),
                    'power' => round($pw),
                ];
                if ($st) {
                    $st->execute([$r['id'], $cpu, $mem, $disk, $temp, $nIn, $nOut, $pw, $now]);
                }
            }

            if ($persist) {   // 只保留最近 4 小时
                q('DELETE FROM device_metrics WHERE recorded_at < ?', [date('Y-m-d H:i:s', time() - 14400)]);
            }

            json_out(['ts' => $now, 'persisted' => $persist, 'metrics' => $out]);
        }

        /* ---------------- 告警 ---------------- */
        case 'alert.list':
            json_out(fetch_all(
                'SELECT a.*, d.name AS device_name, r.name AS rack_name
                 FROM alerts a
                 LEFT JOIN devices d ON d.id = a.device_id
                 LEFT JOIN racks r ON r.id = a.rack_id
                 ORDER BY a.acked, a.created_at DESC LIMIT 60'
            ));

        case 'alert.ack':
            q('UPDATE alerts SET acked = 1 WHERE id = ?', [int_param('id')]);
            json_out(['ok' => true]);

        default:
            json_out(['error' => '未知接口: ' . $route], 404);
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_out(['error' => $e->getMessage()], 500);
}
