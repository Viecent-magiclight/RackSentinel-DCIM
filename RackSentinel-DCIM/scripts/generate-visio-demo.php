<?php
/**
 * Write a sample row Visio export (A 排) for docs / artifacts.
 *   RACK_DB_DRIVER=sqlite php scripts/generate-visio-demo.php [output.vsdx]
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
putenv('RACK_DB_DRIVER=sqlite');
require $root . '/lib/db.php';
require $root . '/lib/helpers.php';
require $root . '/lib/catalog.php';
require $root . '/lib/vsdx.php';

if (!db_installed()) {
    fwrite(STDERR, "Run install/cli.php first.\n");
    exit(1);
}

$rack = fetch_one("SELECT id, name FROM racks WHERE name = 'A01' LIMIT 1");
if (!$rack) {
    $rack = fetch_one('SELECT id, name FROM racks ORDER BY name LIMIT 1');
}
if (!$rack) {
    fwrite(STDERR, "No racks in database.\n");
    exit(1);
}

$out = $argv[1] ?? '/opt/cursor/artifacts/RackSentinel-RowA-demo.vsdx';
$dir = dirname($out);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$tmp = vsdx_build_row_file((int) $rack['id']);
if (!rename($tmp, $out)) {
    copy($tmp, $out);
    @unlink($tmp);
}
echo "Wrote {$out} (anchor rack {$rack['name']}, id {$rack['id']})\n";
