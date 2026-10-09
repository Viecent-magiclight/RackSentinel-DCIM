<?php
/**
 * 开发自检：读回导出的 xlsx，确认结构能被解析。
 * 用法：php install/xlsxcheck.php <file.xlsx> [sheet前几行]
 */
require __DIR__ . '/../lib/xlsx.php';

$file = $argv[1] ?? '';
$peek = (int) ($argv[2] ?? 4);
if (!is_file($file)) {
    exit("用法：php install/xlsxcheck.php <file.xlsx>\n");
}

$sheets = xlsx_read($file);
printf("共 %d 个 Sheet：%s\n", count($sheets), implode('、', array_keys($sheets)));

foreach (array_slice($sheets, 0, 3, true) as $name => $rows) {
    printf("\n--- %s（%d 行）---\n", $name, count($rows));
    foreach (array_slice($rows, 0, $peek, true) as $rn => $cells) {
        ksort($cells);
        printf("  %3d: %s\n", $rn, implode(' | ', array_slice($cells, 0, 10)));
    }
}
