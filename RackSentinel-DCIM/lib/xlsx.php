<?php
/**
 * 极简 XLSX 读写（只依赖 ZipArchive + SimpleXML，不需要 composer）
 *
 * 写入：xlsx_download($filename, $sheets)
 *   $sheets = [[
 *     'name'   => 'Sheet 名',
 *     'cols'   => [12, 20, ...],          // 列宽（字符数）
 *     'freeze' => 1,                      // 冻结前 N 行
 *     'filter' => true,                   // 给首行加筛选器
 *     'rows'   => [
 *        ['文本', 123, ['v' => '带样式', 's' => XLSX_TH]],
 *     ],
 *     'merges' => ['A1:C1', ...],
 *   ]]
 *
 * 单元格样式常量见下方 XLSX_* 定义；写数字请传 int/float，
 * IP、序列号这类「看着像数字」的内容一律当字符串写，避免 Excel 自作主张转格式。
 */

/* 样式索引（与 xlsx_styles_xml() 中 cellXfs 的顺序严格对应） */
const XLSX_BASE    = 0;   // 无边框正文
const XLSX_TH      = 1;   // 表头：深蓝底白字、居中、边框
const XLSX_TXT     = 2;   // 正文文本 + 边框
const XLSX_NUM     = 3;   // 正文数字 + 边框，居中
const XLSX_TITLE   = 4;   // 大标题，无边框
const XLSX_DIM     = 5;   // 灰色小字，无边框
const XLSX_LABEL   = 6;   // 浅底加粗标签 + 边框
const XLSX_CENTER  = 7;   // 居中文本 + 边框
const XLSX_ONLINE  = 8;   // 状态：在线
const XLSX_WARN    = 9;   // 状态：告警
const XLSX_OFF     = 10;  // 状态：离线
const XLSX_MAINT   = 11;  // 状态：维护
const XLSX_EMPTY   = 12;  // 空 U 位：灰底灰字
const XLSX_SECTION = 13;  // 区块标题：加粗无边框

/** 状态 key -> 样式索引 */
function xlsx_status_style(string $status): int
{
    return [
        'online'      => XLSX_ONLINE,
        'warning'     => XLSX_WARN,
        'offline'     => XLSX_OFF,
        'maintenance' => XLSX_MAINT,
    ][$status] ?? XLSX_CENTER;
}

/* ====================== 写入 ====================== */

/** 列号 -> 列名：1 => A，27 => AA */
function xlsx_col(int $n): string
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1 - $m, 26);
    }
    return $s;
}

function xlsx_esc(string $s): string
{
    // Excel 不接受 XML 1.0 的非法控制字符，先剔除
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Sheet 名限制：最长 31 字符，且不能含 : \ / ? * [ ] */
function xlsx_sheet_name(string $name, array $used = []): string
{
    $n = preg_replace('#[:\\\\/?*\[\]]#u', '-', trim($name)) ?: 'Sheet';
    $n = mb_substr($n, 0, 31, 'UTF-8');
    $base = $n;
    $i = 2;
    while (in_array($n, $used, true)) {
        $suffix = '-' . $i++;
        $n = mb_substr($base, 0, 31 - strlen($suffix), 'UTF-8') . $suffix;
    }
    return $n;
}

function xlsx_cell_xml(string $ref, $cell): string
{
    $style = XLSX_BASE;
    $value = $cell;
    if (is_array($cell)) {
        $style = (int) ($cell['s'] ?? XLSX_BASE);
        $value = $cell['v'] ?? '';
    }
    $s = $style ? " s=\"$style\"" : '';

    if ($value === null || $value === '') {
        return "<c r=\"$ref\"$s/>";
    }
    if (is_int($value) || is_float($value)) {
        return "<c r=\"$ref\"$s><v>$value</v></c>";
    }
    return "<c r=\"$ref\"$s t=\"inlineStr\"><is><t xml:space=\"preserve\">"
        . xlsx_esc((string) $value) . '</t></is></c>';
}

function xlsx_sheet_xml(array $sheet): string
{
    $rows   = $sheet['rows'] ?? [];
    $cols   = $sheet['cols'] ?? [];
    $merges = $sheet['merges'] ?? [];
    $maxCol = 1;
    foreach ($rows as $r) {
        $maxCol = max($maxCol, count($r));
    }
    $nRows = max(1, count($rows));
    $dim   = 'A1:' . xlsx_col($maxCol) . $nRows;

    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . "<dimension ref=\"$dim\"/><sheetViews><sheetView workbookViewId=\"0\">";

    $freeze = (int) ($sheet['freeze'] ?? 0);
    if ($freeze > 0) {
        $top = $freeze + 1;
        $x .= "<pane ySplit=\"$freeze\" topLeftCell=\"A$top\" activePane=\"bottomLeft\" state=\"frozen\"/>"
            . '<selection pane="bottomLeft"/>';
    }
    $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="16.5"/>';

    if ($cols) {
        $x .= '<cols>';
        foreach ($cols as $i => $w) {
            $n = $i + 1;
            $x .= "<col min=\"$n\" max=\"$n\" width=\"$w\" customWidth=\"1\"/>";
        }
        $x .= '</cols>';
    }

    $x .= '<sheetData>';
    foreach ($rows as $i => $row) {
        $rn = $i + 1;
        $h  = isset($sheet['heights'][$i]) ? ' ht="' . $sheet['heights'][$i] . '" customHeight="1"' : '';
        $x .= "<row r=\"$rn\"$h>";
        foreach (array_values($row) as $j => $cell) {
            $x .= xlsx_cell_xml(xlsx_col($j + 1) . $rn, $cell);
        }
        $x .= '</row>';
    }
    $x .= '</sheetData>';

    // 顺序不能换：CT_Worksheet 规定 autoFilter 必须排在 mergeCells 之前
    if (!empty($sheet['filter']) && $rows) {
        $x .= '<autoFilter ref="A1:' . xlsx_col($maxCol) . $nRows . '"/>';
    }
    if ($merges) {
        $x .= '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $m) {
            $x .= "<mergeCell ref=\"$m\"/>";
        }
        $x .= '</mergeCells>';
    }
    $x .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
    return $x . '</worksheet>';
}

function xlsx_styles_xml(): string
{
    $border = '<left style="thin"><color rgb="FF9FB3C0"/></left>'
        . '<right style="thin"><color rgb="FF9FB3C0"/></right>'
        . '<top style="thin"><color rgb="FF9FB3C0"/></top>'
        . '<bottom style="thin"><color rgb="FF9FB3C0"/></bottom><diagonal/>';

    $fonts = [
        '<font><sz val="11"/><color theme="1"/><name val="微软雅黑"/></font>',                        // 0
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="微软雅黑"/></font>',               // 1 白粗
        '<font><b/><sz val="15"/><color rgb="FF1F3B53"/><name val="微软雅黑"/></font>',               // 2 大标题
        '<font><sz val="10"/><color rgb="FF7A8C99"/><name val="微软雅黑"/></font>',                   // 3 灰字
        '<font><b/><sz val="11"/><color rgb="FF1F3B53"/><name val="微软雅黑"/></font>',               // 4 深蓝粗
        '<font><sz val="11"/><color rgb="FFAAB7C0"/><name val="微软雅黑"/></font>',                   // 5 浅灰
    ];
    $fills = [
        '<fill><patternFill patternType="none"/></fill>',                                              // 0
        '<fill><patternFill patternType="gray125"/></fill>',                                           // 1 占位
        '<fill><patternFill patternType="solid"><fgColor rgb="FF1F3B53"/><bgColor indexed="64"/></patternFill></fill>', // 2 表头
        '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF4F8"/><bgColor indexed="64"/></patternFill></fill>', // 3 浅底
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE4F7EC"/><bgColor indexed="64"/></patternFill></fill>', // 4 在线
        '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3DC"/><bgColor indexed="64"/></patternFill></fill>', // 5 告警
        '<fill><patternFill patternType="solid"><fgColor rgb="FFFDE4E9"/><bgColor indexed="64"/></patternFill></fill>', // 6 离线
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE9ECFF"/><bgColor indexed="64"/></patternFill></fill>', // 7 维护
        '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F9FA"/><bgColor indexed="64"/></patternFill></fill>', // 8 空位
    ];
    $borders = [
        '<border><left/><right/><top/><bottom/><diagonal/></border>',   // 0 无
        "<border>$border</border>",                                      // 1 细框
    ];

    // 顺序必须与文件顶部的 XLSX_* 常量一一对应
    $xfs = [
        '<xf fontId="0" fillId="0" borderId="0" xfId="0"/>',
        '<xf fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>',
        '<xf fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>',
        '<xf fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>',
        '<xf fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>',
        '<xf fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>',
        '<xf fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="5" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
        '<xf fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>',
    ];

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
        . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
        . '<borders count="' . count($borders) . '">' . implode('', $borders) . '</borders>'
        . '<cellStyleXfs count="1"><xf fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="常规" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

/** 生成 xlsx 二进制内容 */
function xlsx_build(array $sheets): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('服务器未启用 PHP zip 扩展，无法生成 xlsx');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('无法创建临时 xlsx 文件');
    }

    $n = count($sheets);
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $n; $i++) {
        $types .= "<Override PartName=\"/xl/worksheets/sheet$i.xml\" "
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $zip->addFromString('[Content_Types].xml', $types . '</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $wb   = '';
    $rels = '';
    $used = [];
    foreach ($sheets as $i => $sheet) {
        $id   = $i + 1;
        $name = xlsx_sheet_name((string) ($sheet['name'] ?? "Sheet$id"), $used);
        $used[] = $name;
        $wb   .= '<sheet name="' . xlsx_esc($name) . "\" sheetId=\"$id\" r:id=\"rId$id\"/>";
        $rels .= "<Relationship Id=\"rId$id\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$id.xml\"/>";
        $zip->addFromString("xl/worksheets/sheet$id.xml", xlsx_sheet_xml($sheet));
    }
    $styleId = $n + 1;

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . "<sheets>$wb</sheets></workbook>");

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $rels
        . "<Relationship Id=\"rId$styleId\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
        . '</Relationships>');

    $zip->addFromString('xl/styles.xml', xlsx_styles_xml());
    $zip->close();

    $bin = file_get_contents($tmp);
    unlink($tmp);
    return $bin;
}

/** 直接以附件形式输出 xlsx */
function xlsx_download(string $filename, array $sheets, string $asciiName = ''): never
{
    $bin = xlsx_build($sheets);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // 中文文件名走 RFC 5987，同时留一个 ASCII 兜底名给老浏览器。
    // 直接把中文替换成下划线会得到一串「____-20261008.xlsx」，所以调用方可以自带英文名。
    $ascii = preg_replace('/[^\x20-\x7E]/', '', $asciiName ?: $filename);
    $ascii = trim(preg_replace('/[-_]{2,}/', '-', $ascii), '-_') ?: 'export.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-store');
    echo $bin;
    exit;
}

/* ====================== 读取 ====================== */

/** 列名 -> 列号：A => 1，AA => 27 */
function xlsx_col_index(string $ref): int
{
    preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
    $n = 0;
    foreach (str_split($m[1] ?? 'A') as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n;
}

/**
 * 读取 xlsx，返回 [sheetName => [rowIndex => [colIndex(1起) => 字符串]]]。
 * 只取值，不关心样式；空单元格不出现在数组里。
 */
function xlsx_read(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('文件无法打开，请确认是 .xlsx 格式（不支持旧版 .xls）');
    }

    $loadXml = function (string $name) use ($zip) {
        $raw = $zip->getFromName($name);
        if ($raw === false) {
            return null;
        }
        return @simplexml_load_string($raw) ?: null;
    };

    // <si> / <is> 可能是单个 <t>，也可能被拆成多段富文本 <r><t>。
    // 这里不能用 xpath('.//t')：表格 XML 带默认命名空间，无前缀的 xpath 永远匹配不到。
    $textOf = function ($node): string {
        if ($node === null) {
            return '';
        }
        $s = isset($node->t) ? (string) $node->t : '';
        foreach ($node->r as $run) {
            $s .= (string) $run->t;
        }
        return $s;
    };

    // 共享字符串表
    $shared = [];
    if ($sst = $loadXml('xl/sharedStrings.xml')) {
        foreach ($sst->si as $si) {
            $shared[] = $textOf($si);
        }
    }

    // 工作表名 -> 文件路径
    $relMap = [];
    if ($rels = $loadXml('xl/_rels/workbook.xml.rels')) {
        foreach ($rels->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
        }
    }
    $wb = $loadXml('xl/workbook.xml');
    if (!$wb) {
        $zip->close();
        throw new RuntimeException('文件结构异常，缺少 workbook.xml');
    }

    $out = [];
    $i   = 0;
    foreach ($wb->sheets->sheet as $sh) {
        $i++;
        $name = (string) $sh['name'];
        $rid  = (string) $sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $file = 'xl/' . ($relMap[$rid] ?? "worksheets/sheet$i.xml");
        $xml  = $loadXml($file);
        if (!$xml) {
            continue;
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rn    = (int) $row['r'] ?: count($rows) + 1;
            $cells = [];
            foreach ($row->c as $c) {
                $t  = (string) $c['t'];
                $cn = xlsx_col_index((string) $c['r']);
                if ($t === 'inlineStr') {
                    $v = $textOf($c->is);
                } elseif ($t === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } else {
                    $v = (string) $c->v;
                }
                $v = trim($v);
                if ($v !== '') {
                    $cells[$cn] = $v;
                }
            }
            if ($cells) {
                $rows[$rn] = $cells;
            }
        }
        $out[$name] = $rows;
    }

    $zip->close();
    return $out;
}
