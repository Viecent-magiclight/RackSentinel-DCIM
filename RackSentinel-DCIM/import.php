<?php
/**
 * 设备清单 xlsx 导入：批量新增 / 更新 / 删除。
 *
 * POST multipart：file=<xlsx>，可选 dry=1 只校验不写库。
 * 返回 JSON：{ ok, summary:{create,update,delete}, errors:[...], preview:[...] }
 *
 * 校验是「整批通过才写库」：先在内存里推演出每个机柜的最终 U 位占用，
 * 再统一检查越界和重叠。逐行对着数据库校验会误报——两台设备互换 U 位时
 * 第一行就会撞上对方还没挪走的旧位置。
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/catalog.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/sheetmap.php';
require __DIR__ . '/lib/xlsx.php';
require __DIR__ . '/lib/auth.php';

if (!db_installed()) {
    json_out(['error' => '系统尚未安装，请先访问 install.php'], 503);
}
require_login_api();
require_same_origin();

try {
    json_out(run_import());
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    json_out(['error' => $e->getMessage()], 500);
}

function run_import(): array
{
    $up = $_FILES['file'] ?? null;
    if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['error' => '没有收到文件，或文件超过了服务器上传大小限制'], 400);
    }
    if (!preg_match('/\.xlsx$/i', (string) $up['name'])) {
        json_out(['error' => '只支持 .xlsx 文件（Excel 里「另存为」选 xlsx 格式）'], 400);
    }

    $sheets = xlsx_read($up['tmp_name']);
    if (!$sheets) {
        json_out(['error' => '文件里没有可读的工作表'], 400);
    }
    // 优先找「设备清单」，没有就用第一个 Sheet
    $rows = $sheets['设备清单'] ?? reset($sheets);
    $sheetName = isset($sheets['设备清单']) ? '设备清单' : array_key_first($sheets);

    $headerRow = null;
    $headerIdx = 0;
    foreach ($rows as $rn => $cells) {      // 允许表头前有空行
        $map = device_sheet_header_map($cells);
        if (isset($map[0]) || isset($map[3])) {   // 认 ID 或「设备名称」
            $headerRow = $map;
            $headerIdx = $rn;
            break;
        }
    }
    if ($headerRow === null) {
        json_out(['error' => "工作表「${sheetName}」里找不到表头行，请先用「导出 Excel」生成模板再修改"], 400);
    }

    $ctx = load_context();
    $ops = [];
    $errors = [];

    foreach ($rows as $rn => $cells) {
        if ($rn <= $headerIdx) {
            continue;
        }
        $get = fn(int $i) => trim((string) ($cells[$headerRow[$i] ?? -1] ?? ''));
        if (trim(implode('', $cells)) === '') {
            continue;
        }
        $op = parse_row($rn, $get, $ctx, $errors);
        if ($op) {
            $ops[] = $op;
        }
    }

    if (!$ops && !$errors) {
        json_out(['error' => '表格里没有数据行'], 400);
    }

    // 推演最终布局，检查越界与重叠
    $errors = array_merge($errors, check_layout($ops, $ctx));
    if ($errors) {
        // 行级错误和布局错误是分两轮攒出来的，按行号归位才方便对着表格改
        usort($errors, fn($a, $b) => $a['row'] <=> $b['row']);
        return ['ok' => false, 'errors' => array_slice($errors, 0, 60),
                'error_total' => count($errors), 'summary' => count_ops($ops)];
    }

    $summary = count_ops($ops);
    if (param('dry')) {
        return ['ok' => true, 'dry' => true, 'summary' => $summary, 'errors' => []];
    }

    db()->beginTransaction();
    foreach ($ops as $op) {
        if ($op['action'] === 'delete') {
            delete_row('devices', $op['id']);
        } elseif ($op['id']) {
            update_row('devices', $op['id'], $op['data']);
        } else {
            insert_row('devices', $op['data'] + ['rack_id' => $op['rack_id']]);
        }
    }
    db()->commit();

    return ['ok' => true, 'summary' => $summary, 'errors' => []];
}

/** 一次性把机柜、现有设备读进内存，避免逐行查库 */
function load_context(): array
{
    $racks = [];
    foreach (fetch_all('SELECT id, name, u_total FROM racks') as $r) {
        $racks[mb_strtolower(trim($r['name']), 'UTF-8')] = $r;
    }
    $devices = [];
    foreach (fetch_all('SELECT id, rack_id, name, u_start, u_size FROM devices') as $d) {
        $devices[(int) $d['id']] = $d;
    }
    return ['racks' => $racks, 'devices' => $devices];
}

function parse_row(int $rn, callable $get, array $ctx, array &$errors): ?array
{
    $fail = function (string $msg) use ($rn, &$errors) {
        $errors[] = ['row' => $rn, 'msg' => $msg];
        return null;
    };

    $action = resolve_sheet_action($get(1));
    if ($action === null) {
        return $fail('「操作」列只能填 新增 / 更新 / 删除，当前为「' . $get(1) . '」');
    }

    $id   = (int) $get(0);
    $name = $get(3);

    if ($id && !isset($ctx['devices'][$id])) {
        return $fail("ID $id 在库中不存在，新增设备请把 ID 列留空");
    }

    if ($action === 'delete') {
        if (!$id) {
            // 没填 ID 就按 机柜 + 设备名称 定位
            $rack = $ctx['racks'][mb_strtolower($get(2), 'UTF-8')] ?? null;
            foreach ($ctx['devices'] as $d) {
                if ($d['name'] === $name && (!$rack || (int) $d['rack_id'] === (int) $rack['id'])) {
                    $id = (int) $d['id'];
                    break;
                }
            }
        }
        return $id ? ['action' => 'delete', 'id' => $id, 'row' => $rn]
                   : $fail("要删除的设备「${name}」没找到，请填写 ID 或确认机柜与名称");
    }

    if ($name === '') {
        return $fail('设备名称不能为空');
    }

    $rackCell = $get(2);
    $rack = $rackCell !== '' ? ($ctx['racks'][mb_strtolower($rackCell, 'UTF-8')] ?? null) : null;
    if ($rackCell !== '' && !$rack) {
        return $fail("机柜「${rackCell}」不存在，请先在平面图画板里创建");
    }
    $rackId = $rack ? (int) $rack['id'] : (int) ($ctx['devices'][$id]['rack_id'] ?? 0);
    if (!$rackId) {
        return $fail('缺少「机柜」列，无法确定设备装在哪个机柜');
    }

    $typeKey = resolve_catalog_key($get(7) ?: 'server', device_types());
    if (!$typeKey) {
        return $fail('设备类型「' . $get(7) . '」无法识别');
    }
    $osCell = $get(8);
    $osKey  = $osCell === '' ? 'none' : resolve_catalog_key($osCell, os_types());
    if (!$osKey) {
        return $fail("操作系统「${osCell}」无法识别");
    }
    $statusCell = $get(16);
    $status = $statusCell === '' ? 'online' : resolve_catalog_key($statusCell, device_statuses());
    if (!$status) {
        return $fail("状态「${statusCell}」无法识别");
    }

    $old    = $id ? $ctx['devices'][$id] : null;
    $uStart = $get(9) !== '' ? (int) $get(9) : (int) ($old['u_start'] ?? 0);
    $uSize  = $get(10) !== '' ? (int) $get(10) : (int) ($old['u_size'] ?? type_meta($typeKey)['u']);
    if ($uStart < 1) {
        return $fail('「起始 U」必须是大于 0 的整数');
    }
    if ($uSize < 1) {
        return $fail('「占用 U」必须是大于 0 的整数');
    }

    return [
        'action'  => 'upsert',
        'id'      => $id,
        'row'     => $rn,
        'rack_id' => $rackId,
        'u_start' => $uStart,
        'u_size'  => $uSize,
        'name'    => $name,
        'data'    => [
            'name'      => $name,
            'hostname'  => $get(4) ?: null,
            'ip'        => $get(5) ?: null,
            'mgmt_ip'   => $get(6) ?: null,
            'type_key'  => $typeKey,
            'os_key'    => $osKey,
            'u_start'   => $uStart,
            'u_size'    => $uSize,
            'vendor'    => $get(11) ?: null,
            'model'     => $get(12) ?: null,
            'serial'    => $get(13) ?: null,
            'asset_tag' => $get(14) ?: null,
            'owner'     => $get(15) ?: null,
            'status'    => $status,
            'power_w'   => (int) $get(17),
            'note'      => $get(18) ?: null,
        ],
    ];
}

/** 把所有操作应用到内存中的布局，再检查 U 位越界与重叠 */
function check_layout(array $ops, array $ctx): array
{
    $uTotal = [];
    foreach ($ctx['racks'] as $r) {
        $uTotal[(int) $r['id']] = max(1, (int) $r['u_total']);
    }

    // 以现状为起点：key = 设备 id（新增行用 new#N 占位）
    $layout = [];
    foreach ($ctx['devices'] as $d) {
        $layout[(int) $d['id']] = [
            'rack' => (int) $d['rack_id'], 'start' => (int) $d['u_start'],
            'size' => (int) $d['u_size'], 'name' => $d['name'], 'row' => null,
        ];
    }
    $n = 0;
    foreach ($ops as $op) {
        if ($op['action'] === 'delete') {
            unset($layout[$op['id']]);
            continue;
        }
        $key = $op['id'] ?: 'new#' . (++$n);
        $layout[$key] = [
            'rack' => $op['rack_id'], 'start' => $op['u_start'],
            'size' => $op['u_size'], 'name' => $op['name'], 'row' => $op['row'],
        ];
    }

    $errors = [];
    $slots  = [];           // rack => [u => 设备]
    foreach ($layout as $item) {
        $end = $item['start'] + $item['size'] - 1;
        $cap = $uTotal[$item['rack']] ?? 42;
        if ($end > $cap && $item['row']) {
            $errors[] = ['row' => $item['row'],
                         'msg' => "{$item['name']}：U{$item['start']}-U$end 超出机柜高度（共 {$cap}U）"];
            continue;
        }
        for ($u = $item['start']; $u <= min($end, $cap); $u++) {
            $hit = $slots[$item['rack']][$u] ?? null;
            if ($hit) {
                // 只对导入行报错：两台都是库里的老数据说明冲突本来就存在，不该赖导入
                $row = $item['row'] ?? $hit['row'];
                if ($row) {
                    $errors[] = ['row' => $row,
                                 'msg' => "U$u 冲突：{$item['name']} 与 {$hit['name']} 重叠"];
                }
                break;
            }
            $slots[$item['rack']][$u] = $item;
        }
    }
    return $errors;
}

function count_ops(array $ops): array
{
    $s = ['create' => 0, 'update' => 0, 'delete' => 0];
    foreach ($ops as $op) {
        $s[$op['action'] === 'delete' ? 'delete' : ($op['id'] ? 'update' : 'create')]++;
    }
    return $s;
}
