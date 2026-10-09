<?php
/** 开发自检：生成一份带各类错误的导入文件，用来验证前端的错误弹窗 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/sheetmap.php';
require __DIR__ . '/../lib/xlsx.php';

$cols = device_sheet_columns();
$rows = [array_map(fn($c) => ['v' => $c[0], 's' => XLSX_TH], $cols)];
$mk = function (array $v) use ($cols) {
    $r = [];
    foreach ($cols as $i => $c) {
        $r[] = ['v' => $v[$i] ?? '', 's' => XLSX_TXT];
    }
    return $r;
};

$rows[] = $mk(['', '新增', 'A01', 'BAD-1 占用已有 U 位', '', '', '', '服务器', '', 1, 1]);
$rows[] = $mk(['', '新增', '不存在的柜', 'BAD-2 机柜不存在', '', '', '', '服务器', '', 40, 1]);
$rows[] = $mk(['', '新增', 'A01', 'BAD-3 类型拼错', '', '', '', '烤箱', '', 41, 1]);
$rows[] = $mk(['', '报废', 'A01', 'BAD-4 操作列非法']);
$rows[] = $mk(['', '新增', 'A01', 'BAD-5 超出柜高', '', '', '', '服务器', '', 42, 4]);
$rows[] = $mk([999999, '更新', 'A01', 'BAD-6 ID 不存在']);

// 写到临时目录，别留在站点根目录下被当成正经文件
$out = sys_get_temp_dir() . '/rack-bad.xlsx';
file_put_contents($out, xlsx_build([['name' => '设备清单', 'rows' => $rows]]));
echo "已生成 $out\n";
