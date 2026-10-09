<?php
/**
 * 轻量迁移：给已经建好的库补上后来新增的表和列，让老库不用重装、不丢数据。
 *
 * 这里不维护版本号表——每条迁移自己先查「表/列在不在」，所以重复执行无副作用，
 * 顺序也无所谓。新增结构时往下面两个清单里加一行即可。
 */

/** [表名, MySQL 建表语句, SQLite 建表语句] */
function pending_tables(): array
{
    return [
        [
            'users',
            "CREATE TABLE users (
               id            INT AUTO_INCREMENT PRIMARY KEY,
               username      VARCHAR(40)  NOT NULL UNIQUE,
               password_hash VARCHAR(255) NOT NULL,
               display_name  VARCHAR(60)  NOT NULL DEFAULT '',
               role          VARCHAR(16)  NOT NULL DEFAULT 'admin',
               last_login_at DATETIME     DEFAULT NULL,
               created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE users (
               id            INTEGER PRIMARY KEY AUTOINCREMENT,
               username      TEXT NOT NULL UNIQUE,
               password_hash TEXT NOT NULL,
               display_name  TEXT NOT NULL DEFAULT '',
               role          TEXT NOT NULL DEFAULT 'admin',
               last_login_at TEXT,
               created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
             )",
        ],
        [
            'settings',
            'CREATE TABLE settings (k VARCHAR(64) PRIMARY KEY, v TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE settings (k TEXT PRIMARY KEY, v TEXT)',
        ],
        [
            'login_attempts',
            "CREATE TABLE login_attempts (
               id       INT AUTO_INCREMENT PRIMARY KEY,
               ip       VARCHAR(45) NOT NULL,
               username VARCHAR(40) NOT NULL DEFAULT '',
               at       DATETIME    NOT NULL,
               INDEX idx_attempt (ip, at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE login_attempts (
               id       INTEGER PRIMARY KEY AUTOINCREMENT,
               ip       TEXT NOT NULL,
               username TEXT NOT NULL DEFAULT '',
               at       TEXT NOT NULL
             )",
        ],
    ];
}

/** [表, 列, MySQL 定义, SQLite 定义] */
function pending_columns(): array
{
    return [
        ['walls', 'curve', "INT NOT NULL DEFAULT 0 COMMENT '弧高(cm)，0 为直墙'", 'INTEGER NOT NULL DEFAULT 0'],
    ];
}

function table_exists(string $table): bool
{
    if (db_driver() === 'sqlite') {
        return (bool) fetch_val("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
    }
    return (bool) fetch_val(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
        [cfg('mysql.database'), $table]
    );
}

function table_has_column(string $table, string $col): bool
{
    if (db_driver() === 'sqlite') {
        foreach (db()->query("PRAGMA table_info($table)")->fetchAll() as $c) {
            if (strcasecmp($c['name'], $col) === 0) {
                return true;
            }
        }
        return false;
    }
    return (bool) fetch_val(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [cfg('mysql.database'), $table, $col]
    );
}

/** 返回这次真正执行了的迁移描述，已是最新则返回空数组 */
function run_migrations(): array
{
    $done = [];
    foreach (pending_tables() as [$table, $my, $lite]) {
        if (table_exists($table)) {
            continue;
        }
        db()->exec(db_driver() === 'sqlite' ? $lite : $my);
        $done[] = "表 $table";
    }
    foreach (pending_columns() as [$table, $col, $my, $lite]) {
        if (!table_exists($table) || table_has_column($table, $col)) {
            continue;
        }
        db()->exec("ALTER TABLE $table ADD COLUMN $col " . (db_driver() === 'sqlite' ? $lite : $my));
        $done[] = "字段 $table.$col";
    }
    if (ensure_default_admin()) {
        $done[] = '默认管理员 admin';
    }
    return $done;
}

/**
 * 空用户表时建一个默认管理员。密码写死成 admin123 不太体面，
 * 但总比装完进不去系统强——登录页和设置页都会提醒改掉。
 */
function ensure_default_admin(): bool
{
    if (!table_exists('users') || fetch_val('SELECT COUNT(*) FROM users', [], 0) > 0) {
        return false;
    }
    insert_row('users', [
        'username'      => 'admin',
        'password_hash' => password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
        'display_name'  => '系统管理员',
        'role'          => 'admin',
    ]);
    return true;
}

const DEFAULT_ADMIN_PASSWORD = 'admin123';
