<?php
/** 命令行数据自检： php install/verify.php —— 检查样本数据是否自洽 */
if (PHP_SAPI !== 'cli') {
    exit('仅限命令行执行');
}
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';

$bad = 0;

echo "【1】设备类型是否都在目录中\n";
foreach (fetch_all('SELECT DISTINCT type_key FROM devices') as $r) {
    if (!isset(device_types()[$r['type_key']])) {
        echo "  ✗ 未知类型 {$r['type_key']}\n";
        $bad++;
    }
}
echo "【2】操作系统是否都在目录中\n";
foreach (fetch_all('SELECT DISTINCT os_key FROM devices WHERE os_key IS NOT NULL') as $r) {
    if (!isset(os_types()[$r['os_key']])) {
        echo "  ✗ 未知系统 {$r['os_key']}\n";
        $bad++;
    }
}
echo "【3】设备名称是否重复\n";
foreach (fetch_all("SELECT name, COUNT(*) c FROM devices WHERE type_key <> 'blank' GROUP BY name HAVING c > 1") as $r) {
    echo "  ✗ 重名 {$r['name']} ×{$r['c']}\n";
    $bad++;
}
echo "【4】主机名是否重复\n";
foreach (fetch_all("SELECT hostname, COUNT(*) c FROM devices WHERE hostname IS NOT NULL GROUP BY hostname HAVING c > 1") as $r) {
    echo "  ✗ 重复主机名 {$r['hostname']} ×{$r['c']}\n";
    $bad++;
}
echo "【5】IP 是否重复\n";
foreach (fetch_all("SELECT ip, COUNT(*) c FROM devices WHERE ip IS NOT NULL GROUP BY ip HAVING c > 1") as $r) {
    echo "  ✗ 重复 IP {$r['ip']} ×{$r['c']}\n";
    $bad++;
}
echo "【6】U 位是否越界或重叠\n";
foreach (fetch_all('SELECT id, u_total FROM racks') as $rk) {
    $rows = fetch_all('SELECT name, u_start, u_size FROM devices WHERE rack_id = ? ORDER BY u_start', [$rk['id']]);
    $prevEnd = 0;
    foreach ($rows as $d) {
        $end = $d['u_start'] + $d['u_size'] - 1;
        if ($d['u_start'] <= $prevEnd) {
            echo "  ✗ 机柜 {$rk['id']} 中 {$d['name']} 与上一台重叠\n";
            $bad++;
        }
        if ($end > $rk['u_total']) {
            echo "  ✗ 机柜 {$rk['id']} 中 {$d['name']} 超出 {$rk['u_total']}U\n";
            $bad++;
        }
        $prevEnd = $end;
    }
}
echo "【7】网络设备厂商与系统是否匹配\n";
$pair = ['Cisco' => 'ciscoios', '华为' => 'vrp', '新华三' => 'comware', '锐捷' => 'rgos', 'Arista' => 'eos'];
foreach (fetch_all("SELECT name, vendor, os_key FROM devices WHERE type_key IN ('switch','router')") as $d) {
    if (isset($pair[$d['vendor']]) && $pair[$d['vendor']] !== $d['os_key']) {
        echo "  ✗ {$d['name']}：{$d['vendor']} 却标成 {$d['os_key']}\n";
        $bad++;
    }
}

echo str_repeat('-', 40), "\n";
echo $bad === 0 ? "全部检查通过\n" : "发现 $bad 处问题\n";
exit($bad === 0 ? 0 : 1);
