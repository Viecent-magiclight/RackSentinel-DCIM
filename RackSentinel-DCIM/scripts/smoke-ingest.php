<?php
/**
 * 采集 API 冒烟测试（CLI）
 *   RACK_DB_DRIVER=sqlite php scripts/smoke-ingest.php
 */
$root = dirname(__DIR__);
putenv('RACK_DB_DRIVER=sqlite');
$_SERVER['REQUEST_METHOD'] = 'GET';

require $root . '/lib/db.php';
require $root . '/lib/helpers.php';
require $root . '/lib/settings.php';
require $root . '/lib/migrate.php';
require $root . '/lib/ingest.php';

if (!db_installed()) {
    fwrite(STDERR, "DB not installed. Run install first.\n");
    exit(1);
}
run_migrations();

$deviceId = (int) fetch_val('SELECT id FROM devices ORDER BY id LIMIT 1', [], 0);
if (!$deviceId) {
    fwrite(STDERR, "No devices in DB.\n");
    exit(1);
}

$plain = ingest_new_agent_token();
insert_row('monitor_agents', [
    'name'         => 'smoke-agent',
    'kind'         => 'host',
    'device_id'    => $deviceId,
    'token_hash'   => ingest_hash_token($plain),
    'token_prefix' => ingest_token_prefix($plain),
    'scopes_json'  => json_encode(['ingest:device']),
    'enabled'      => 1,
]);

$base = 'http://localhost/RackSentinel-DCIM/api/ingest.php';
$phpServer = getenv('SMOKE_PHP_BASE');
if ($phpServer) {
    $base = rtrim($phpServer, '/') . '/api/ingest.php';
}

function smoke_post(string $url, string $token, array $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $raw, true)];
}

// 若未提供外部 URL，直接调用 ingest 逻辑
if (!$phpServer) {
    $_GET['r'] = 'metrics';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $plain;
    $GLOBALS['smoke_in'] = ['metrics' => ['cpu' => 33.3, 'mem' => 50, 'disk' => 40, 'temp' => 36]];
    ob_start();
    // inline: duplicate minimal path
    $agent = ingest_agent_by_token($plain);
    ingest_record_metrics($deviceId, $GLOBALS['smoke_in']['metrics'], true);
    ob_end_clean();
    $cpu = fetch_val('SELECT cpu FROM device_metrics WHERE device_id = ? ORDER BY id DESC LIMIT 1', [$deviceId]);
    if ((float) $cpu < 33) {
        fwrite(STDERR, "metrics not persisted\n");
        exit(1);
    }
    echo "OK inline ingest metrics for device $deviceId\n";
    q('DELETE FROM monitor_agents WHERE name = ?', ['smoke-agent']);
    exit(0);
}

[$code, $j] = smoke_post($base . '?r=metrics', $plain, ['metrics' => ['cpu' => 22, 'mem' => 30]]);
if ($code !== 200 || empty($j['ok'])) {
    fwrite(STDERR, "HTTP $code " . json_encode($j) . "\n");
    exit(1);
}
echo "OK HTTP ingest\n";
q('DELETE FROM monitor_agents WHERE name = ?', ['smoke-agent']);
