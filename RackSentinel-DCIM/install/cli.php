<?php
/**
 * 命令行安装： php install/cli.php
 * 与 install.php 的网页安装等价，便于在服务器上一次性初始化。
 */
if (PHP_SAPI !== 'cli') {
    exit('仅限命令行执行');
}
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/catalog.php';
require __DIR__ . '/seed.php';

$driver = db_driver();
echo "驱动：$driver\n";

if ($driver === 'mysql') {
    $name = cfg('mysql.database');
    db(false)->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "数据库 `$name` 已就绪\n";
} else {
    echo '数据文件：' . cfg('sqlite.path') . "\n";
}

$pdo = db();
foreach (sql_statements(file_get_contents(__DIR__ . '/schema.' . $driver . '.sql')) as $stmt) {
    $pdo->exec($stmt);
}
echo "表结构创建完成\n";

$t = microtime(true);
$s = seed_database();
foreach ($s as $k => $v) {
    echo str_pad($k, 10) . $v . "\n";
}
printf("样本数据写入完成，耗时 %.1fs\n", microtime(true) - $t);
