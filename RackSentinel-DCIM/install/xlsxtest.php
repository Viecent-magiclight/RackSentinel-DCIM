<?php
/**
 * 开发自检：导出 -> 改表 -> 导入 的往返验证。
 * 用法：php install/xlsxtest.php http://127.0.0.1:8123 [用户名] [密码]
 *
 * 导出导入接口都要登录，所以先用 curl 走一遍登录拿到会话 Cookie。
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/sheetmap.php';
require __DIR__ . '/../lib/xlsx.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$tmp  = sys_get_temp_dir();
$fail = 0;
$jar  = tempnam($tmp, 'rackck');

function http_curl(string $url, array $extra = []): string
{
    global $jar;
    $cf = curl_init($url);
    curl_setopt_array($cf, $extra + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $body = curl_exec($cf);
    curl_close($cf);
    return (string) $body;
}

function http_login(string $base, string $user, string $pass): void
{
    $html = http_curl("$base/login.php");
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    $out = http_curl("$base/login.php", [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query([
            '_csrf' => $m[1] ?? '', 'username' => $user, 'password' => $pass, 'next' => 'index.php',
        ]),
        CURLOPT_HTTPHEADER => ['Origin: ' . $base],
    ]);
    if (str_contains($out, 'login-form')) {
        fwrite(STDERR, "登录失败：请检查账号密码（默认 admin / admin123）\n");
        exit(1);
    }
}

function post_xlsx(string $url, string $file): array
{
    global $base;
    $body = http_curl($url, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => ['file' => new CURLFile($file, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', basename($file))],
        CURLOPT_HTTPHEADER => ['Origin: ' . $base],
    ]);
    return json_decode($body, true) ?? ['error' => 'HTTP 响应不是 JSON：' . substr($body, 0, 300)];
}

http_login($base, $argv[2] ?? 'admin', $argv[3] ?? 'admin123');

/** 返回 [是否有问题, 说明] */
function xlsx_structure_problems(string $file): array
{
    $zip = new ZipArchive();
    $zip->open($file);
    $styles = $zip->getFromName('xl/styles.xml');
    preg_match('/<cellXfs count="(\d+)"/', $styles, $m);
    $xfCount = (int) ($m[1] ?? 0);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $xml  = $zip->getFromIndex($i);
        if (!str_ends_with($name, '.xml') && !str_ends_with($name, '.rels')) {
            continue;
        }
        if (@simplexml_load_string($xml) === false) {
            $zip->close();
            return [true, "$name XML 不合法"];
        }
        if (!str_starts_with($name, 'xl/worksheets/')) {
            continue;
        }
        preg_match_all('/<c [^>]*s="(\d+)"/', $xml, $sm);
        foreach ($sm[1] as $s) {
            if ((int) $s >= $xfCount) {
                $zip->close();
                return [true, "$name 引用了不存在的样式 s={$s}（共 {$xfCount} 个）"];
            }
        }
        // 合并区交叠检查：按单元格展开后看有没有重复
        preg_match_all('/<mergeCell ref="([A-Z]+)(\d+):([A-Z]+)(\d+)"/', $xml, $mm, PREG_SET_ORDER);
        $seen = [];
        foreach ($mm as [, $c1, $r1, $c2, $r2]) {
            for ($c = xlsx_col_index($c1); $c <= xlsx_col_index($c2); $c++) {
                for ($r = (int) $r1; $r <= (int) $r2; $r++) {
                    if (isset($seen["$c:$r"])) {
                        $zip->close();
                        return [true, "$name 合并区在 " . xlsx_col($c) . "$r 交叠"];
                    }
                    $seen["$c:$r"] = true;
                }
            }
        }
    }
    $zip->close();
    return [false, ''];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    printf("[%s] %s%s\n", $ok ? ' OK ' : 'FAIL', $label, $detail ? "  -> $detail" : '');
    if (!$ok) {
        $fail++;
    }
}

/* ---------- 1. 导出 ---------- */
$devFile = "$tmp/t-dev.xlsx";
file_put_contents($devFile, http_curl("$base/export.php?r=devices"));
$sheets = xlsx_read($devFile);
$rows   = $sheets['设备清单'] ?? [];
$before = (int) fetch_val('SELECT COUNT(*) FROM devices', [], 0);
check('导出设备清单', count($rows) === $before + 1, count($rows) - 1 . " 行 / 库里 $before 台");

/* 按机柜排序？ */
$hdr   = device_sheet_header_map($rows[1]);
$racks = [];
foreach ($rows as $rn => $c) {
    if ($rn > 1) {
        $racks[] = $c[$hdr[2]] ?? '';
    }
}
$sorted = $racks;
sort($sorted, SORT_STRING);
check('行按机柜排序', $racks === $sorted);

/* 边框：样式表里应有细边框，且正文单元格引用了带边框的 xf */
$zip = new ZipArchive();
$zip->open($devFile);
$styles = $zip->getFromName('xl/styles.xml');
$sheet1 = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();
check('样式表含细边框', str_contains($styles, '<left style="thin">'));
check('正文单元格套用边框样式', (bool) preg_match('/<c r="C2" s="[1-9]/', $sheet1));

/* ---------- 2. 机柜图 ---------- */
$rkFile = "$tmp/t-rack.xlsx";
file_put_contents($rkFile, http_curl("$base/export.php?r=racks"));
$rk = xlsx_read($rkFile);
$rackNames = fetch_all('SELECT name FROM racks ORDER BY name');
$expect = array_merge(['机房概览'], array_column($rackNames, 'name'));
check('Sheet 数量与命名', array_keys($rk) === $expect,
    count($rk) . ' 个：' . implode(',', array_slice(array_keys($rk), 0, 4)) . '…');
check('首个 Sheet 是机房概览', array_key_first($rk) === '机房概览');

/* 结构体检：XML 合法、样式索引不越界、合并区不交叠——这三样出问题 Excel 会直接报文件损坏 */
foreach ([$devFile, $rkFile] as $f) {
    [$bad, $why] = xlsx_structure_problems($f);
    check('结构体检 ' . basename($f), !$bad, $why);
}

/* ---------- 3. 导入：无改动应为纯更新 ---------- */
$r = post_xlsx("$base/import.php?dry=1", $devFile);
check('原样导入可通过校验', !empty($r['ok']), json_encode($r['errors'] ?? $r, JSON_UNESCAPED_UNICODE));
check('原样导入识别为全量更新', ($r['summary']['update'] ?? -1) === $before
    && ($r['summary']['create'] ?? -1) === 0 && ($r['summary']['delete'] ?? -1) === 0,
    json_encode($r['summary'] ?? [], JSON_UNESCAPED_UNICODE));

/* ---------- 4. 构造一份增 / 改 / 删 ---------- */
$cols = device_sheet_columns();
$out  = [array_map(fn($c) => ['v' => $c[0], 's' => XLSX_TH], $cols)];

$targetRack = fetch_one('SELECT r.id, r.name FROM racks r ORDER BY r.name LIMIT 1');
$freeU = null;
$used  = [];
foreach (fetch_all('SELECT u_start, u_size FROM devices WHERE rack_id = ?', [$targetRack['id']]) as $d) {
    for ($u = (int) $d['u_start']; $u < (int) $d['u_start'] + (int) $d['u_size']; $u++) {
        $used[$u] = true;
    }
}
for ($u = 1; $u <= 42; $u++) {
    if (!isset($used[$u])) { $freeU = $u; break; }
}

$victim = fetch_one("SELECT * FROM devices WHERE type_key = 'server' ORDER BY id DESC LIMIT 1");
$edit   = fetch_one("SELECT * FROM devices WHERE type_key = 'server' AND id <> ? ORDER BY id LIMIT 1", [$victim['id']]);

$line = function (array $v) use ($cols) {
    $row = [];
    foreach ($cols as $i => $c) {
        $row[] = ['v' => $v[$i] ?? '', 's' => XLSX_TXT];
    }
    return $row;
};
$out[] = $line([ '', '新增', $targetRack['name'], 'ZZ-IMPORT-TEST', 'zz.test.local',
                 '10.99.0.1', '172.31.0.1', '服务器', 'Ubuntu Server', $freeU, 1,
                 'Dell', 'R750', 'SNTEST001', 'ZCTEST001', '测试组', '维护中', 321, '导入自检' ]);
$out[] = $line([ $edit['id'], '更新', '', 'ZZ-EDITED-' . $edit['id'], '', '', '', '', '', '', '',
                 '', '', '', '', '', '', '', '' ]);
$out[] = $line([ $victim['id'], '删除' ]);

file_put_contents("$tmp/t-mix.xlsx", xlsx_build([['name' => '设备清单', 'rows' => $out]]));
$r = post_xlsx("$base/import.php", "$tmp/t-mix.xlsx");
check('混合导入执行成功', !empty($r['ok']), json_encode($r, JSON_UNESCAPED_UNICODE));
check('统计为 1 增 1 改 1 删',
    ($r['summary'] ?? []) == ['create' => 1, 'update' => 1, 'delete' => 1],
    json_encode($r['summary'] ?? [], JSON_UNESCAPED_UNICODE));

$added = fetch_one("SELECT * FROM devices WHERE name = 'ZZ-IMPORT-TEST'");
check('新增设备已落库', $added
    && $added['hostname'] === 'zz.test.local'
    && $added['status'] === 'maintenance'
    && (int) $added['power_w'] === 321
    && (int) $added['rack_id'] === (int) $targetRack['id'],
    json_encode($added ? array_intersect_key($added, array_flip(['hostname', 'status', 'power_w'])) : null, JSON_UNESCAPED_UNICODE));

check('更新生效且未动其他字段',
    fetch_val('SELECT name FROM devices WHERE id = ?', [$edit['id']]) === 'ZZ-EDITED-' . $edit['id']
    && fetch_val('SELECT ip FROM devices WHERE id = ?', [$edit['id']]) === null);
check('删除生效', !fetch_one('SELECT id FROM devices WHERE id = ?', [$victim['id']]));

/* ---------- 5. U 位冲突必须被拦下 ---------- */
$occupied = fetch_one('SELECT * FROM devices WHERE rack_id = ? AND u_size = 1 LIMIT 1', [$targetRack['id']]);
$bad = [array_map(fn($c) => ['v' => $c[0], 's' => XLSX_TH], $cols),
        $line(['', '新增', $targetRack['name'], 'ZZ-CONFLICT', '', '', '', '服务器', '', $occupied['u_start'], 1])];
file_put_contents("$tmp/t-bad.xlsx", xlsx_build([['name' => '设备清单', 'rows' => $bad]]));
$r = post_xlsx("$base/import.php", "$tmp/t-bad.xlsx");
check('U 位冲突被拦截', empty($r['ok']) && !empty($r['errors']),
    json_encode($r['errors'][0] ?? $r, JSON_UNESCAPED_UNICODE));
check('冲突时未写入任何数据', !fetch_one("SELECT id FROM devices WHERE name = 'ZZ-CONFLICT'"));

/* ---------- 6. 清理 ---------- */
q("DELETE FROM devices WHERE name = 'ZZ-IMPORT-TEST'");
q('UPDATE devices SET name = ? WHERE id = ?', [$edit['name'], $edit['id']]);

echo $fail ? "\n$fail 项未通过\n" : "\n全部通过\n";
exit($fail ? 1 : 0);
