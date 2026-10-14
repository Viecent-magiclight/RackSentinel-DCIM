<?php
/**
 * Visio (.vsdx) export — delegates to scripts/build_rack_visio.py (python vsdx + template.vsdx).
 * PHP web app stays Composer-free; requires python3 + `pip install vsdx` on the server.
 */

function vsdx_racks_for_export(int $rackId): array
{
    $anchor = fetch_one('SELECT * FROM racks WHERE id = ?', [$rackId]);
    if (!$anchor) {
        throw new InvalidArgumentException('机柜不存在');
    }
    $rowLabel = trim((string) ($anchor['row_label'] ?? ''));
    $racks    = $rowLabel !== ''
        ? fetch_all(
            'SELECT * FROM racks WHERE room_id = ? AND row_label = ? ORDER BY name',
            [(int) $anchor['room_id'], $rowLabel]
        )
        : [$anchor];

    $out = [];
    foreach ($racks as $rack) {
        $id = (int) $rack['id'];
        $rack['stats']   = rack_stats($id);
        $rack['devices'] = fetch_all(
            'SELECT * FROM devices WHERE rack_id = ? ORDER BY u_start ASC',
            [$id]
        );
        $out[] = $rack;
    }
    $title = ($anchor['name'] ?? 'Rack') . ' · ' . ($anchor['label'] ?? '机柜');
    if ($rowLabel !== '') {
        $names = implode('、', array_column($out, 'name'));
        $title = $rowLabel . ' — ' . count($out) . ' 台机柜（' . $names . '）';
    }
    $title .= "\nRackSentinel DCIM · " . date('Y-m-d H:i');

    return [
        'title'    => $title,
        'racks'    => $out,
        'anchor'   => $anchor,
        'rowLabel' => $rowLabel,
    ];
}

function vsdx_python_script(): string
{
    return dirname(__DIR__) . '/scripts/build_rack_visio.py';
}

function vsdx_build_row_file(int $rackId): string
{
    $script = vsdx_python_script();
    if (!is_file($script)) {
        throw new RuntimeException('Visio builder script missing');
    }
    $out = tempnam(sys_get_temp_dir(), 'racksentinel-');
    if ($out === false) {
        throw new RuntimeException('temp file failed');
    }
    @unlink($out);
    $outPath = $out . '.vsdx';

    $py = trim((string) shell_exec('command -v python3 2>/dev/null') ?: '');
    if ($py === '') {
        throw new RuntimeException('python3 not found (required for Visio export)');
    }

    $lines = [];
    $code  = 1;

    if (db_driver() === 'sqlite') {
        $dbPath = cfg('sqlite.path');
        if (!is_file($dbPath)) {
            $dbPath = dirname(__DIR__) . '/' . ltrim($dbPath, './');
        }
        $cmd = escapeshellarg($py) . ' ' . escapeshellarg($script)
            . ' --rack-id ' . (int) $rackId
            . ' --db ' . escapeshellarg($dbPath)
            . ' -o ' . escapeshellarg($outPath);
        exec($cmd . ' 2>&1', $lines, $code);
    } else {
        $bundle  = vsdx_racks_for_export($rackId);
        $payload = [
            'title' => $bundle['title'],
            'racks' => array_map(function (array $rack) {
                return [
                    'name'           => $rack['name'],
                    'label'          => $rack['label'] ?? '',
                    'u_total'        => (int) ($rack['u_total'] ?? 42),
                    'power_capacity' => (int) ($rack['power_capacity'] ?? 0),
                    'stats'          => $rack['stats'] ?? [],
                    'devices'        => $rack['devices'] ?? [],
                ];
            }, $bundle['racks']),
        ];
        $jsonFile = tempnam(sys_get_temp_dir(), 'vsdx-json-');
        file_put_contents($jsonFile, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $cmd = escapeshellarg($py) . ' ' . escapeshellarg($script)
            . ' --payload ' . escapeshellarg($jsonFile)
            . ' -o ' . escapeshellarg($outPath);
        exec($cmd . ' 2>&1', $lines, $code);
        @unlink($jsonFile);
    }

    if ($code !== 0 || !is_file($outPath)) {
        throw new RuntimeException(
            'Visio export failed (install: pip install vsdx). ' . implode("\n", $lines)
        );
    }

    return $outPath;
}

function vsdx_download(string $filename, int $rackId): void
{
    $path = vsdx_build_row_file($rackId);
    header('Content-Type: application/vnd.ms-visio.drawing');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}
