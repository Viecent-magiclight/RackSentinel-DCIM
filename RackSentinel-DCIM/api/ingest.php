<?php
/**
 * 采集专用 REST 入口（无需浏览器会话）：
 *   api/ingest.php?r=<action>
 *
 * 鉴权：Authorization: Bearer <token> 或 X-Agent-Token
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/settings.php';
require __DIR__ . '/../lib/ingest.php';
require __DIR__ . '/../lib/ingest_snmp.php';

if (!db_installed()) {
    json_out(['error' => '系统尚未安装'], 503);
}

$route = (string) param('r', '');
$in    = json_in();

try {
    switch ($route) {

        case 'ping':
            json_out(['ok' => true, 'ts' => now_str(), 'service' => 'racksentinel-ingest']);

        case 'heartbeat': {
            $agent = ingest_require_device_agent();
            $deviceId = (int) $agent['device_id'];
            ingest_touch_agent((int) $agent['id']);
            $status = (string) arr_get($in, 'status', 'online');
            if (!in_array($status, ['online', 'warning', 'maintenance'], true)) {
                $status = 'online';
            }
            update_row('devices', $deviceId, [
                'last_heartbeat_at' => now_str(),
                'status'            => $status,
            ]);
            if ($meta = arr_get($in, 'meta')) {
                update_row('monitor_agents', (int) $agent['id'], [
                    'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]);
            }
            json_out(['ok' => true, 'device_id' => $deviceId]);
        }

        case 'metrics': {
            $agent = ingest_require_device_agent();
            $deviceId = (int) $agent['device_id'];
            ingest_touch_agent((int) $agent['id']);
            $metrics = arr_get($in, 'metrics', $in);
            if (!is_array($metrics)) {
                json_out(['error' => '缺少 metrics 对象'], 400);
            }
            ingest_record_metrics($deviceId, $metrics, true);
            json_out(['ok' => true, 'device_id' => $deviceId]);
        }

        case 'health': {
            $agent = ingest_require_device_agent();
            $deviceId = (int) $agent['device_id'];
            ingest_touch_agent((int) $agent['id']);
            $checks = arr_get($in, 'checks', []);
            if (is_array($checks)) {
                foreach ($checks as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $ok    = !empty($c['ok']);
                    $name  = (string) arr_get($c, 'name', 'health');
                    $detail = (string) arr_get($c, 'detail', '');
                    if (!$ok) {
                        ingest_create_alert(
                            $deviceId,
                            (string) arr_get($c, 'level', 'warning'),
                            '健康检查失败: ' . $name,
                            $detail
                        );
                    }
                }
            }
            json_out(['ok' => true]);
        }

        case 'alert': {
            $agent = ingest_require_device_agent();
            $deviceId = (int) $agent['device_id'];
            ingest_touch_agent((int) $agent['id']);
            $title = trim((string) arr_get($in, 'title', ''));
            if ($title === '') {
                json_out(['error' => '缺少 title'], 400);
            }
            $id = ingest_create_alert(
                $deviceId,
                (string) arr_get($in, 'level', 'warning'),
                $title,
                (string) arr_get($in, 'detail', '')
            );
            json_out(['ok' => true, 'id' => $id]);
        }

        /* ---------- SNMP 轮询器 ---------- */
        case 'snmp.targets': {
            ingest_require_agent(['snmp:poll']);
            $rows = fetch_all(
                "SELECT d.id, d.name, d.hostname, d.ip, d.mgmt_ip, d.vendor, d.model, d.status,
                        d.snmp_version, d.snmp_host, d.snmp_port, d.snmp_community,
                        d.snmp_v3_user, d.snmp_v3_auth_proto, d.snmp_v3_auth_pass,
                        d.snmp_v3_priv_proto, d.snmp_v3_priv_pass, d.snmp_profile
                 FROM devices d
                 WHERE d.monitor_mode = 'snmp' AND d.snmp_enabled = 1
                 ORDER BY d.id"
            );
            foreach ($rows as &$r) {
                $r['snmp_host'] = $r['snmp_host'] ?: $r['mgmt_ip'] ?: $r['ip'];
                $r['snmp_port'] = (int) ($r['snmp_port'] ?: 161);
            }
            unset($r);
            json_out(['targets' => $rows, 'profiles' => snmp_profile_catalog()]);
        }

        case 'snmp.report': {
            ingest_require_agent(['snmp:poll']);
            $deviceId = (int) arr_get($in, 'device_id', 0);
            if (!$deviceId) {
                json_out(['error' => '缺少 device_id'], 400);
            }
            $dev = fetch_one('SELECT id FROM devices WHERE id = ? AND monitor_mode = ?', [$deviceId, 'snmp']);
            if (!$dev) {
                json_out(['error' => '设备不存在或未启用 SNMP 监控'], 404);
            }
            $metrics = arr_get($in, 'metrics', []);
            if (is_array($metrics) && $metrics) {
                ingest_record_metrics($deviceId, $metrics, true);
            }
            $profileDetected = (string) arr_get($in, 'profile_id', '');
            if ($sys = arr_get($in, 'sys')) {
                $up = [];
                if ($v = arr_get($sys, 'sysName')) {
                    $up['hostname'] = (string) $v;
                }
                if ($v = arr_get($sys, 'sysDescr')) {
                    $up['note'] = substr((string) $v, 0, 500);
                }
                if ($up) {
                    update_row('devices', $deviceId, $up);
                }
                ingest_save_snmp_meta($deviceId, $sys, $profileDetected ?: null);
            }
            ingest_save_interfaces($deviceId, (array) arr_get($in, 'interfaces', []));
            ingest_save_sensors($deviceId, (array) arr_get($in, 'sensors', []));
            update_row('devices', $deviceId, ['last_heartbeat_at' => now_str()]);
            foreach ((array) arr_get($in, 'alerts', []) as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $t = trim((string) arr_get($a, 'title', ''));
                if ($t === '') {
                    continue;
                }
                ingest_create_alert($deviceId, (string) arr_get($a, 'level', 'warning'), $t, (string) arr_get($a, 'detail', ''));
            }
            json_out(['ok' => true]);
        }

        /* ---------- 告警通知器 ---------- */
        case 'alerts.pending': {
            ingest_require_agent(['alert:read']);
            $since = (string) arr_get($in, 'since', date('Y-m-d H:i:s', time() - 86400));
            $rows  = fetch_all(
                'SELECT a.*, d.name AS device_name, r.name AS rack_name
                 FROM alerts a
                 LEFT JOIN devices d ON d.id = a.device_id
                 LEFT JOIN racks r ON r.id = COALESCE(a.rack_id, d.rack_id)
                 WHERE a.acked = 0 AND a.created_at >= ?
                 ORDER BY a.created_at ASC LIMIT 200',
                [$since]
            );
            json_out(['alerts' => $rows]);
        }

        case 'alerts.ack_remote': {
            ingest_require_agent(['notify:ack']);
            $id = (int) arr_get($in, 'id', 0);
            if ($id) {
                q('UPDATE alerts SET acked = 1 WHERE id = ?', [$id]);
            }
            json_out(['ok' => true]);
        }

        case 'notify.recipients': {
            ingest_require_agent(['alert:read']);
            json_out(['recipients' => fetch_all(
                'SELECT id, name, phone, sms_phone, min_level, enabled, escalate_min FROM notification_recipients WHERE enabled = 1'
            )]);
        }

        default:
            json_out(['error' => '未知采集接口: ' . $route], 404);
    }
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 500);
}
