<?php
/**
 * 采集端鉴权、限流与指标落库（主机 Agent / SNMP 轮询器共用）。
 */

const INGEST_TOKEN_BYTES = 32;

function ingest_bearer_token(): string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) {
        return $m[1];
    }
    if ($h !== '' && !str_contains($h, ' ')) {
        return trim($h);
    }
    return trim((string) param('token', ''));
}

function ingest_hash_token(string $plain): string
{
    return hash('sha256', $plain);
}

function ingest_token_prefix(string $plain): string
{
    return substr($plain, 0, 8);
}

/** @return array|null 代理行（含 scopes 解析后的数组） */
function ingest_agent_by_token(string $plain): ?array
{
    if ($plain === '') {
        return null;
    }
    $hash = ingest_hash_token($plain);
    $row  = fetch_one(
        'SELECT * FROM monitor_agents WHERE token_hash = ? AND enabled = 1 LIMIT 1',
        [$hash]
    );
    if (!$row) {
        return null;
    }
    $row['scopes'] = ingest_parse_scopes((string) ($row['scopes_json'] ?? ''));
    return $row;
}

function ingest_parse_scopes(string $json): array
{
    if ($json === '') {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

function ingest_require_agent(?array $scopesNeeded = null): array
{
    $plain = ingest_bearer_token();
    $agent = ingest_agent_by_token($plain);
    if (!$agent) {
        json_out(['error' => '无效或已禁用的采集令牌', 'code' => 'auth'], 401);
    }
    ingest_rate_check((int) $agent['id']);
    if ($scopesNeeded) {
        $have = $agent['scopes'];
        foreach ($scopesNeeded as $s) {
            if (!in_array($s, $have, true) && !in_array('*', $have, true)) {
                json_out(['error' => '令牌权限不足', 'code' => 'forbidden', 'need' => $s], 403);
            }
        }
    }
    return $agent;
}

/** 主机 Agent：必须绑定 device_id */
function ingest_require_device_agent(): array
{
    $agent = ingest_require_agent(['ingest:device']);
    if (!(int) $agent['device_id']) {
        json_out(['error' => '该令牌未绑定设备', 'code' => 'no_device'], 403);
    }
    return $agent;
}

function ingest_rate_limit_per_min(): int
{
    return max(10, (int) setting('ingest_rate_per_min', '120'));
}

function ingest_rate_check(int $agentId): void
{
    $bucket = gmdate('Y-m-d H:i:00');
    $lim    = ingest_rate_limit_per_min();
    $row    = fetch_one(
        'SELECT id, count FROM ingest_rate WHERE agent_id = ? AND bucket = ?',
        [$agentId, $bucket]
    );
    if ($row && (int) $row['count'] >= $lim) {
        json_out(['error' => '请求过于频繁，请稍后重试', 'code' => 'rate_limit'], 429);
    }
    if ($row) {
        q('UPDATE ingest_rate SET count = count + 1 WHERE id = ?', [(int) $row['id']]);
    } else {
        insert_row('ingest_rate', ['agent_id' => $agentId, 'bucket' => $bucket, 'count' => 1]);
    }
    // 清理 2 小时前的桶
    q('DELETE FROM ingest_rate WHERE bucket < ?', [gmdate('Y-m-d H:i:00', time() - 7200)]);
}

function ingest_touch_agent(int $agentId, ?string $ip = null): void
{
    update_row('monitor_agents', $agentId, [
        'last_seen_at' => now_str(),
        'last_ip'      => $ip ?: client_ip(),
    ]);
}

function heartbeat_stale_seconds(): int
{
    return max(30, (int) setting('heartbeat_stale_sec', '180'));
}

function metrics_simulation_enabled(): bool
{
    return setting('metrics_simulation', '0') === '1';
}

/** 写入一条指标并可选更新设备状态 */
function ingest_record_metrics(int $deviceId, array $m, bool $setOnline = true): void
{
    $cpu   = isset($m['cpu']) ? (float) $m['cpu'] : null;
    $mem   = isset($m['mem']) ? (float) $m['mem'] : null;
    $disk  = isset($m['disk']) ? (float) $m['disk'] : null;
    $temp  = isset($m['temp']) ? (float) $m['temp'] : null;
    $nIn   = isset($m['net_in']) ? (float) $m['net_in'] : null;
    $nOut  = isset($m['net_out']) ? (float) $m['net_out'] : null;
    $power = isset($m['power']) ? (float) $m['power'] : null;
    $now   = now_str();

    insert_row('device_metrics', [
        'device_id'   => $deviceId,
        'cpu'         => $cpu ?? 0,
        'mem'         => $mem ?? 0,
        'disk'        => $disk ?? 0,
        'temp'        => $temp ?? 0,
        'net_in'      => $nIn ?? 0,
        'net_out'     => $nOut ?? 0,
        'power'       => $power ?? 0,
        'recorded_at' => $now,
    ]);

    $devUp = ['last_heartbeat_at' => $now];
    if ($setOnline) {
        $devUp['status'] = 'online';
    }
    if (isset($m['hostname']) && $m['hostname'] !== '') {
        $devUp['hostname'] = (string) $m['hostname'];
    }
    if (isset($m['ip']) && $m['ip'] !== '') {
        $devUp['ip'] = (string) $m['ip'];
    }
    update_row('devices', $deviceId, $devUp);

    ingest_eval_thresholds($deviceId, $m);
}

function ingest_eval_thresholds(int $deviceId, array $m): void
{
    $rules = [
        ['key' => 'cpu', 'level' => 'warning', 'title' => 'CPU 使用率偏高', 'hi' => (float) setting('thresh_cpu_warn', '85')],
        ['key' => 'cpu', 'level' => 'critical', 'title' => 'CPU 使用率过高', 'hi' => (float) setting('thresh_cpu_crit', '95')],
        ['key' => 'mem', 'level' => 'warning', 'title' => '内存使用率偏高', 'hi' => (float) setting('thresh_mem_warn', '85')],
        ['key' => 'temp', 'level' => 'warning', 'title' => '设备温度偏高', 'hi' => (float) setting('thresh_temp_warn', '55')],
    ];
    foreach ($rules as $r) {
        if (!isset($m[$r['key']])) {
            continue;
        }
        $v = (float) $m[$r['key']];
        if ($v < $r['hi']) {
            continue;
        }
        $detail = $r['key'] . '=' . $v;
        $exists = fetch_val(
            'SELECT id FROM alerts WHERE device_id = ? AND title = ? AND acked = 0 AND created_at > ? LIMIT 1',
            [$deviceId, $r['title'], date('Y-m-d H:i:s', time() - 3600)],
            0
        );
        if (!$exists) {
            insert_row('alerts', [
                'device_id' => $deviceId,
                'level'     => $r['level'],
                'title'     => $r['title'],
                'detail'    => $detail,
            ]);
        }
    }
}

function ingest_create_alert(int $deviceId, string $level, string $title, ?string $detail = null): int
{
    return insert_row('alerts', [
        'device_id' => $deviceId ?: null,
        'level'     => $level,
        'title'     => $title,
        'detail'    => $detail,
    ]);
}

/** 根据心跳超时将设备标为离线 */
function ingest_mark_stale_devices_offline(): int
{
    $cut = date('Y-m-d H:i:s', time() - heartbeat_stale_seconds());
    $pdo = db();
    $st  = $pdo->prepare(
        "UPDATE devices SET status = 'offline' WHERE monitor_mode = 'agent'
         AND last_heartbeat_at IS NOT NULL AND last_heartbeat_at < ?
         AND status <> 'maintenance'"
    );
    $st->execute([$cut]);
    return $st->rowCount();
}

function ingest_new_agent_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(INGEST_TOKEN_BYTES)), '+/', '-_'), '=');
}

function ingest_agent_scopes_for_kind(string $kind, int $deviceId = 0): array
{
    if ($kind === 'host') {
        return ['ingest:device'];
    }
    if ($kind === 'snmp_poller') {
        return ['snmp:poll', 'ingest:device'];
    }
    if ($kind === 'notifier') {
        return ['alert:read', 'notify:ack'];
    }
    return $deviceId ? ['ingest:device'] : [];
}

function snmp_profile_catalog(): array
{
    $dir = dirname(__DIR__) . '/agents/snmp/profiles';
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        $raw = file_get_contents($path);
        $j   = json_decode((string) $raw, true);
        if (!is_array($j)) {
            continue;
        }
        $id = $j['id'] ?? basename($path, '.json');
        $out[] = [
            'id'          => $id,
            'name'        => $j['name'] ?? $id,
            'vendor'      => $j['vendor'] ?? '',
            'description' => $j['description'] ?? '',
            'needs_verification' => !empty($j['needs_verification']),
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $out;
}
