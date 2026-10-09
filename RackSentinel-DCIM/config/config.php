<?php
/**
 * 全局配置
 *
 * 默认使用 MySQL。本地若暂时没有 MySQL，可设置环境变量 RACK_DB_DRIVER=sqlite
 * 直接以 SQLite 文件库运行（功能完全一致，便于先看效果）。
 *
 *   Windows PowerShell:  $env:RACK_DB_DRIVER="sqlite"; php -S 127.0.0.1:8080
 */
return [
    'driver' => getenv('RACK_DB_DRIVER') ?: 'mysql',

    'mysql' => [
        'host'     => getenv('RACK_DB_HOST') ?: '127.0.0.1',
        'port'     => (int)(getenv('RACK_DB_PORT') ?: 3306),
        'database' => getenv('RACK_DB_NAME') ?: 'server_rack',
        'username' => getenv('RACK_DB_USER') ?: 'root',
        'password' => getenv('RACK_DB_PASS') !== false ? getenv('RACK_DB_PASS') : 'wadewade',
        'charset'  => 'utf8mb4',
    ],

    'sqlite' => [
        'path' => __DIR__ . '/../storage/server_rack.sqlite',
    ],

    'app' => [
        'name'     => '机房机柜资产与运维管理平台',
        'short'    => 'Server Rack DCIM',
        'timezone' => 'Asia/Shanghai',
        // 大屏轮询间隔（毫秒）
        'poll_ms'  => 5000,
    ],
];
