<?php
/**
 * 升级已有数据库的表结构，不动数据。
 * 用法：php install/migrate.php
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/migrate.php';

if (!db_installed()) {
    fwrite(STDERR, "数据库还没初始化，请先运行 install.php\n");
    exit(1);
}

$done = run_migrations();
echo $done ? '已补齐：' . implode('、', $done) . PHP_EOL : '表结构已是最新，无需升级' . PHP_EOL;
