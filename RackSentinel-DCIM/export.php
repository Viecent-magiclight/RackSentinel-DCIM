<?php
/**
 * xlsx 导出：
 *   export.php?r=devices  设备清单（单表，按机柜排序，全表边框）
 *   export.php?r=racks    机柜图（首页机房概览，之后每个机柜一个 Sheet）
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/catalog.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/sheetmap.php';
require __DIR__ . '/lib/xlsx.php';
require __DIR__ . '/lib/auth.php';

if (!db_installed()) {
    http_response_code(503);
    exit('系统尚未安装，请先访问 install.php');
}
// 浏览器是直接跳转下载的，没登录就按页面跳转处理，跳回登录页
require_login();

$what = (string) param('r', 'devices');
$stamp = date('Ymd-Hi');

try {
    if ($what === 'racks') {
        xlsx_download("机柜布局-$stamp.xlsx", build_rack_sheets(), "racks-$stamp.xlsx");
    }
    xlsx_download("设备清单-$stamp.xlsx", [build_device_sheet()], "devices-$stamp.xlsx");
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '导出失败：' . $e->getMessage();
}

/* ===================== 设备清单 ===================== */

function export_device_rows(): array
{
    // 支持沿用页面上的筛选条件，不传就是全量
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

    // 按机柜排序，柜内从顶部 U 往下排，和机柜立面图的阅读顺序一致
    $sql = 'SELECT d.*, r.name AS rack_name FROM devices d JOIN racks r ON r.id = d.rack_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY r.name, d.u_start DESC';
    return fetch_all($sql, $args);
}

function build_device_sheet(): array
{
    $cols    = device_sheet_columns();
    $types   = device_types();
    $oses    = os_types();
    $statuses = device_statuses();

    $rows = [array_map(fn($c) => ['v' => $c[0], 's' => XLSX_TH], $cols)];

    $prevRack = null;
    foreach (export_device_rows() as $d) {
        $line = [];
        foreach ($cols as $c) {
            $field = $c[2];
            if ($field === null) {                       // 操作列导出时留空
                $line[] = ['v' => '', 's' => XLSX_CENTER];
                continue;
            }
            switch ($field) {
                case 'id':
                case 'u_start':
                case 'u_size':
                case 'power_w':
                    $line[] = ['v' => (int) $d[$field], 's' => XLSX_NUM];
                    break;
                case 'rack_name':
                    $line[] = ['v' => $d['rack_name'], 's' => XLSX_CENTER];
                    break;
                case 'type_key':
                    $line[] = ['v' => $types[$d['type_key']]['name'] ?? $d['type_key'], 's' => XLSX_CENTER];
                    break;
                case 'os_key':
                    $line[] = ['v' => $oses[$d['os_key'] ?: 'none']['name'] ?? $d['os_key'], 's' => XLSX_TXT];
                    break;
                case 'status':
                    $line[] = ['v' => $statuses[$d['status']]['name'] ?? $d['status'],
                               's' => xlsx_status_style($d['status'])];
                    break;
                default:
                    $line[] = ['v' => (string) ($d[$field] ?? ''), 's' => XLSX_TXT];
            }
        }
        $rows[] = $line;
    }

    return [
        'name'   => '设备清单',
        'cols'   => array_map(fn($c) => $c[1], $cols),
        'rows'   => $rows,
        'freeze' => 1,
        'filter' => true,
        'heights' => [0 => 26],
    ];
}

/* ===================== 机柜图 ===================== */

function build_rack_sheets(): array
{
    $roomId = int_param('room_id') ?: (int) fetch_val('SELECT id FROM rooms ORDER BY id LIMIT 1', [], 0);
    $room   = fetch_one('SELECT * FROM rooms WHERE id = ?', [$roomId]);
    if (!$room) {
        throw new RuntimeException('机房不存在');
    }
    $racks = fetch_all('SELECT * FROM racks WHERE room_id = ? ORDER BY name', [$roomId]);

    $sheets = [overview_sheet($room, $racks)];
    foreach ($racks as $rack) {
        $sheets[] = rack_sheet($rack);
    }
    return $sheets;
}

function overview_sheet(array $room, array $racks): array
{
    $types = device_types();
    $rows  = [
        [['v' => $room['name'] . '　机房概览', 's' => XLSX_TITLE]],
        [['v' => '导出时间 ' . date('Y-m-d H:i') . '　编码 ' . $room['code'], 's' => XLSX_DIM]],
        [],
    ];

    $totU = $usedU = $power = $capacity = $devCount = 0;
    foreach ($racks as $r) {
        $st = rack_stats((int) $r['id']);
        $totU     += (int) $r['u_total'];
        $usedU    += $st['used_u'];
        $power    += $st['power'];
        $capacity += (int) $r['power_capacity'];
        $devCount += $st['total'];
    }

    $kv = [
        ['机房名称', $room['name']],
        ['机房编码', $room['code']],
        ['平面尺寸', $room['width'] . ' × ' . $room['depth'] . ' cm　（'
            . round($room['width'] * $room['depth'] / 10000, 1) . ' ㎡）'],
        ['层高', $room['wall_height'] . ' cm'],
        ['机柜数量', count($racks) . ' 个'],
        ['设备总数', $devCount . ' 台'],
        ['U 位总量', $totU . ' U'],
        ['已用 U 位', $usedU . ' U　（占用率 ' . ($totU ? round($usedU / $totU * 100, 1) : 0) . '%）'],
        ['额定功率', round($capacity / 1000, 1) . ' kW'],
        ['实时功率', round($power / 1000, 1) . ' kW'],
        ['备注', (string) ($room['note'] ?? '')],
    ];
    foreach ($kv as [$k, $v]) {
        $rows[] = [['v' => $k, 's' => XLSX_LABEL], ['v' => $v, 's' => XLSX_TXT]];
    }

    $rows[] = [];
    $rows[] = [['v' => '机柜一览', 's' => XLSX_SECTION]];
    $rows[] = array_map(fn($t) => ['v' => $t, 's' => XLSX_TH],
        ['机柜', '用途', '排/列', '设备数', '已用 U', '容量 U', '占用率', '功率 kW', '在线', '告警', '离线']);

    foreach ($racks as $r) {
        $st  = rack_stats((int) $r['id']);
        $cap = max(1, (int) $r['u_total']);
        $rows[] = [
            ['v' => $r['name'], 's' => XLSX_CENTER],
            ['v' => (string) ($r['label'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($r['row_label'] ?? ''), 's' => XLSX_CENTER],
            ['v' => $st['total'], 's' => XLSX_NUM],
            ['v' => $st['used_u'], 's' => XLSX_NUM],
            ['v' => (int) $r['u_total'], 's' => XLSX_NUM],
            ['v' => round($st['used_u'] / $cap * 100, 1) . '%', 's' => XLSX_CENTER],
            ['v' => round($st['power'] / 1000, 2), 's' => XLSX_NUM],
            ['v' => $st['online'], 's' => XLSX_ONLINE],
            ['v' => $st['warning'], 's' => XLSX_WARN],
            ['v' => $st['offline'], 's' => XLSX_OFF],
        ];
    }

    $rows[] = [];
    $rows[] = [['v' => '设备类型统计', 's' => XLSX_SECTION]];
    $rows[] = array_map(fn($t) => ['v' => $t, 's' => XLSX_TH], ['设备类型', '数量']);
    $byType = fetch_all(
        'SELECT d.type_key, COUNT(*) c FROM devices d JOIN racks r ON r.id = d.rack_id
         WHERE r.room_id = ? GROUP BY d.type_key ORDER BY c DESC',
        [$room['id']]
    );
    foreach ($byType as $t) {
        $rows[] = [
            ['v' => $types[$t['type_key']]['name'] ?? $t['type_key'], 's' => XLSX_TXT],
            ['v' => (int) $t['c'], 's' => XLSX_NUM],
        ];
    }

    return [
        'name'    => '机房概览',
        'cols'    => [14, 30, 10, 9, 9, 9, 9, 10, 8, 8, 8],
        'rows'    => $rows,
        'heights' => [0 => 30],
        'merges'  => ['A1:E1', 'A2:E2'],
    ];
}

/** 单个机柜：从柜顶 U 往下逐行铺开，多 U 设备纵向合并 */
function rack_sheet(array $rack): array
{
    $types    = device_types();
    $oses     = os_types();
    $statuses = device_statuses();

    $rackId = (int) $rack['id'];
    $uTotal = max(1, (int) $rack['u_total']);
    $st     = rack_stats($rackId);

    $occupied = [];          // u => 占用该 U 的设备
    foreach (fetch_all('SELECT * FROM devices WHERE rack_id = ? ORDER BY u_start', [$rackId]) as $d) {
        for ($u = (int) $d['u_start']; $u < (int) $d['u_start'] + (int) $d['u_size']; $u++) {
            $occupied[$u] = $d;
        }
    }

    // [表头, 列宽]。改这里就够了，下面的空行填充、合并范围都按列数自动算
    $head = [
        ['U 位', 7], ['设备名称', 24], ['类型', 12], ['主机名', 24], ['业务 IP', 15],
        ['管理 IP', 15], ['操作系统', 15], ['厂商', 12], ['型号', 18], ['序列号', 16],
        ['资产编号', 14], ['负责人', 10], ['状态', 10], ['功耗 W', 9], ['备注', 26],
    ];
    $nCol    = count($head);
    $lastCol = xlsx_col($nCol);

    $rows = [
        [['v' => $rack['name'] . '　' . ($rack['label'] ?? ''), 's' => XLSX_TITLE]],
        [['v' => sprintf(
            '已用 %d/%dU　设备 %d 台　在线 %d / 告警 %d / 离线 %d　功率 %.1f kW / %.1f kW',
            $st['used_u'], $uTotal, $st['total'], $st['online'], $st['warning'], $st['offline'],
            $st['power'] / 1000, $rack['power_capacity'] / 1000
        ), 's' => XLSX_DIM]],
        [],
        array_map(fn($h) => ['v' => $h[0], 's' => XLSX_TH], $head),
    ];

    $headerRows  = count($rows);        // 之后的行号都要加上这个偏移
    $merges      = ["A1:{$lastCol}1", "A2:{$lastCol}2"];
    $mergedUntil = $headerRows;         // 已被合并覆盖到的最后一行

    for ($u = $uTotal; $u >= 1; $u--) {
        $excelRow = $headerRows + ($uTotal - $u) + 1;

        if (!isset($occupied[$u])) {
            $row = array_fill(0, $nCol, ['v' => '', 's' => XLSX_EMPTY]);
            $row[0] = ['v' => 'U' . $u, 's' => XLSX_NUM];
            $row[1] = ['v' => '空闲', 's' => XLSX_EMPTY];
            $rows[] = $row;
            continue;
        }

        $d    = $occupied[$u];
        $size = (int) $d['u_size'];
        $top  = (int) $d['u_start'] + $size - 1;      // 多 U 设备只在最顶一行写内容

        if ($u !== $top) {
            // 被合并掉的行：仍要写出带边框的空单元格，否则合并区域缺边线
            $rows[] = array_fill(0, $nCol, ['v' => '', 's' => XLSX_TXT]);
            $rows[count($rows) - 1][0] = ['v' => 'U' . $u, 's' => XLSX_NUM];
            continue;
        }

        // 合并区不能交叠，否则 Excel 会判定文件损坏。
        // 正常数据不会重叠（写入路径都做了 U 位校验），这里只是兜底。
        $bottomRow = min($excelRow + $size - 1, $headerRows + $uTotal);
        if ($bottomRow > $excelRow && $bottomRow > $mergedUntil) {
            $excelRow = max($excelRow, $mergedUntil + 1);
            foreach (range(2, $nCol) as $ci) {
                $c = xlsx_col($ci);
                $merges[] = "$c$excelRow:$c$bottomRow";
            }
            $mergedUntil = $bottomRow;
        }
        $rows[] = [
            ['v' => 'U' . $u, 's' => XLSX_NUM],
            ['v' => $d['name'], 's' => XLSX_TXT],
            ['v' => $types[$d['type_key']]['name'] ?? $d['type_key'], 's' => XLSX_CENTER],
            ['v' => (string) ($d['hostname'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['ip'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['mgmt_ip'] ?? ''), 's' => XLSX_TXT],
            ['v' => $oses[$d['os_key'] ?: 'none']['name'] ?? '', 's' => XLSX_TXT],
            ['v' => (string) ($d['vendor'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['model'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['serial'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['asset_tag'] ?? ''), 's' => XLSX_TXT],
            ['v' => (string) ($d['owner'] ?? ''), 's' => XLSX_TXT],
            ['v' => $statuses[$d['status']]['name'] ?? $d['status'], 's' => xlsx_status_style($d['status'])],
            ['v' => (int) $d['power_w'], 's' => XLSX_NUM],
            ['v' => (string) ($d['note'] ?? ''), 's' => XLSX_TXT],
        ];
    }

    return [
        'name'    => $rack['name'],
        'cols'    => array_column($head, 1),
        'rows'    => $rows,
        'merges'  => $merges,
        'freeze'  => $headerRows,
        'heights' => [0 => 30],
    ];
}
