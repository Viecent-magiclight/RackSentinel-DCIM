<?php
/** 命令行自检： php install/stats.php —— 打印各表记录数与机柜概况 */
if (PHP_SAPI !== 'cli') {
    exit('仅限命令行执行');
}
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';

foreach (['rooms', 'walls', 'racks', 'devices', 'device_metrics', 'alerts', 'sensors'] as $t) {
    printf("%-15s %d\n", $t, fetch_val("SELECT COUNT(*) FROM $t"));
}
echo str_repeat('-', 46), "\n";
printf("%-10s %-6s %-6s %-6s %s\n", '机柜', 'X', 'Y', '设备', 'U 占用');
foreach (fetch_all('SELECT id, name, x, y FROM racks ORDER BY name') as $r) {
    $s = rack_stats((int) $r['id']);
    printf("%-10s %-6d %-6d %-6d %dU\n", $r['name'], $r['x'], $r['y'], $s['total'], $s['used_u']);
}
