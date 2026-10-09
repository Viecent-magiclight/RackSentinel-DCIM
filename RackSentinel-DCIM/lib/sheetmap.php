<?php
/**
 * 设备清单 xlsx 的列定义，导出与导入共用一份，
 * 避免两边各写一套表头导致导出的文件自己导不回来。
 */

function device_sheet_columns(): array
{
    // [表头, 列宽, 取值字段]
    return [
        ['ID',       7,  'id'],
        ['操作',     8,  null],      // 导入时填：新增 / 更新 / 删除；导出时留空
        ['机柜',     10, 'rack_name'],
        ['设备名称', 22, 'name'],
        ['主机名',   24, 'hostname'],
        ['业务 IP',  15, 'ip'],
        ['管理 IP',  15, 'mgmt_ip'],
        ['设备类型', 12, 'type_key'],
        ['操作系统', 15, 'os_key'],
        ['起始 U',   8,  'u_start'],
        ['占用 U',   8,  'u_size'],
        ['厂商',     14, 'vendor'],
        ['型号',     20, 'model'],
        ['序列号',   16, 'serial'],
        ['资产编号', 14, 'asset_tag'],
        ['负责人',   12, 'owner'],
        ['状态',     10, 'status'],
        ['功耗 W',   9,  'power_w'],
        ['备注',     26, 'note'],
    ];
}

/** 表头 -> 列序号（1 起）。表头做了归一化，容忍空格与全角差异 */
function device_sheet_header_map(array $headerRow): array
{
    $norm = fn($s) => preg_replace('/\s+/u', '', mb_strtolower(trim((string) $s), 'UTF-8'));
    $want = [];
    foreach (device_sheet_columns() as $i => [$label]) {
        $want[$norm($label)] = $i;
    }
    $map = [];
    foreach ($headerRow as $col => $text) {
        $k = $norm($text);
        if (isset($want[$k])) {
            $map[$want[$k]] = $col;
        }
    }
    return $map;
}

/** 中文名或 key 都能解析成 key；解析不出返回 null */
function resolve_catalog_key(string $input, array $catalog): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (isset($catalog[$input])) {
        return $input;
    }
    $lower = mb_strtolower($input, 'UTF-8');
    foreach ($catalog as $key => $meta) {
        if ($lower === mb_strtolower($key, 'UTF-8') || $input === $meta['name']) {
            return $key;
        }
    }
    return null;
}

/** 导入时的「操作」列：返回 upsert / delete，无法识别返回 null */
function resolve_sheet_action(string $raw): ?string
{
    $v = mb_strtolower(trim($raw), 'UTF-8');
    if ($v === '' || in_array($v, ['新增', '更新', '添加', '修改', 'add', 'new', 'update', 'upsert', 'u'], true)) {
        return 'upsert';
    }
    if (in_array($v, ['删除', '下架', '移除', 'delete', 'del', 'remove', 'd'], true)) {
        return 'delete';
    }
    return null;
}
