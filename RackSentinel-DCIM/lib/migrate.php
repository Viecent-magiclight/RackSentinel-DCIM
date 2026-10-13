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
        [
            'monitor_agents',
            "CREATE TABLE monitor_agents (
               id           INT AUTO_INCREMENT PRIMARY KEY,
               name         VARCHAR(80) NOT NULL,
               kind         VARCHAR(24) NOT NULL DEFAULT 'host',
               device_id    INT DEFAULT NULL,
               token_hash   CHAR(64) NOT NULL,
               token_prefix VARCHAR(12) NOT NULL DEFAULT '',
               scopes_json  TEXT,
               enabled      TINYINT NOT NULL DEFAULT 1,
               last_seen_at DATETIME DEFAULT NULL,
               last_ip      VARCHAR(45) DEFAULT NULL,
               meta_json    TEXT,
               created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               UNIQUE KEY uk_agent_token (token_hash),
               INDEX idx_agent_device (device_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE monitor_agents (
               id           INTEGER PRIMARY KEY AUTOINCREMENT,
               name         TEXT NOT NULL,
               kind         TEXT NOT NULL DEFAULT 'host',
               device_id    INTEGER,
               token_hash   TEXT NOT NULL UNIQUE,
               token_prefix TEXT NOT NULL DEFAULT '',
               scopes_json  TEXT,
               enabled      INTEGER NOT NULL DEFAULT 1,
               last_seen_at TEXT,
               last_ip      TEXT,
               meta_json    TEXT,
               created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
             )",
        ],
        [
            'ingest_rate',
            "CREATE TABLE ingest_rate (
               id       INT AUTO_INCREMENT PRIMARY KEY,
               agent_id INT NOT NULL,
               bucket   VARCHAR(20) NOT NULL,
               count    INT NOT NULL DEFAULT 0,
               UNIQUE KEY uk_rate (agent_id, bucket)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE ingest_rate (
               id       INTEGER PRIMARY KEY AUTOINCREMENT,
               agent_id INTEGER NOT NULL,
               bucket   TEXT NOT NULL,
               count    INTEGER NOT NULL DEFAULT 0,
               UNIQUE(agent_id, bucket)
             )",
        ],
        [
            'notification_recipients',
            "CREATE TABLE notification_recipients (
               id         INT AUTO_INCREMENT PRIMARY KEY,
               name       VARCHAR(60) NOT NULL,
               phone      VARCHAR(20) DEFAULT NULL,
               sms_phone  VARCHAR(20) DEFAULT NULL,
               min_level  VARCHAR(16) NOT NULL DEFAULT 'warning',
               enabled    TINYINT NOT NULL DEFAULT 1,
               created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE notification_recipients (
               id         INTEGER PRIMARY KEY AUTOINCREMENT,
               name       TEXT NOT NULL,
               phone      TEXT,
               sms_phone  TEXT,
               min_level  TEXT NOT NULL DEFAULT 'warning',
               enabled    INTEGER NOT NULL DEFAULT 1,
               created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
             )",
        ],
        [
            'device_snmp_meta',
            "CREATE TABLE device_snmp_meta (
               device_id     INT PRIMARY KEY,
               sys_object_id VARCHAR(128) DEFAULT NULL,
               sys_name      VARCHAR(128) DEFAULT NULL,
               sys_descr     VARCHAR(512) DEFAULT NULL,
               profile_id    VARCHAR(64) DEFAULT NULL,
               last_poll_at  DATETIME DEFAULT NULL,
               CONSTRAINT fk_snmpmeta_dev FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE device_snmp_meta (
               device_id     INTEGER PRIMARY KEY,
               sys_object_id TEXT,
               sys_name      TEXT,
               sys_descr     TEXT,
               profile_id    TEXT,
               last_poll_at  TEXT
             )",
        ],
        [
            'device_interfaces',
            "CREATE TABLE device_interfaces (
               id            INT AUTO_INCREMENT PRIMARY KEY,
               device_id     INT NOT NULL,
               if_index      INT NOT NULL,
               name          VARCHAR(128) NOT NULL DEFAULT '',
               alias         VARCHAR(128) DEFAULT '',
               admin_status  VARCHAR(16) DEFAULT '',
               oper_status   VARCHAR(16) DEFAULT '',
               speed_mbps    INT DEFAULT 0,
               in_octets     VARCHAR(24) DEFAULT '0',
               out_octets    VARCHAR(24) DEFAULT '0',
               in_errors     INT DEFAULT 0,
               out_errors    INT DEFAULT 0,
               in_rate_mbps  DECIMAL(12,3) DEFAULT 0,
               out_rate_mbps DECIMAL(12,3) DEFAULT 0,
               recorded_at   DATETIME NOT NULL,
               UNIQUE KEY uk_if (device_id, if_index),
               INDEX idx_if_dev (device_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE device_interfaces (
               id            INTEGER PRIMARY KEY AUTOINCREMENT,
               device_id     INTEGER NOT NULL,
               if_index      INTEGER NOT NULL,
               name          TEXT NOT NULL DEFAULT '',
               alias         TEXT DEFAULT '',
               admin_status  TEXT DEFAULT '',
               oper_status   TEXT DEFAULT '',
               speed_mbps    INTEGER DEFAULT 0,
               in_octets     TEXT DEFAULT '0',
               out_octets    TEXT DEFAULT '0',
               in_errors     INTEGER DEFAULT 0,
               out_errors    INTEGER DEFAULT 0,
               in_rate_mbps  REAL DEFAULT 0,
               out_rate_mbps REAL DEFAULT 0,
               recorded_at   TEXT NOT NULL,
               UNIQUE(device_id, if_index)
             )",
        ],
        [
            'device_sensors',
            "CREATE TABLE device_sensors (
               id          INT AUTO_INCREMENT PRIMARY KEY,
               device_id   INT NOT NULL,
               sensor_key  VARCHAR(64) NOT NULL,
               label       VARCHAR(128) NOT NULL DEFAULT '',
               kind        VARCHAR(24) NOT NULL DEFAULT 'other',
               value       DECIMAL(12,3) DEFAULT 0,
               unit        VARCHAR(16) DEFAULT '',
               status      VARCHAR(16) DEFAULT 'normal',
               recorded_at DATETIME NOT NULL,
               UNIQUE KEY uk_sensor (device_id, sensor_key),
               INDEX idx_sens_dev (device_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE device_sensors (
               id          INTEGER PRIMARY KEY AUTOINCREMENT,
               device_id   INTEGER NOT NULL,
               sensor_key  TEXT NOT NULL,
               label       TEXT NOT NULL DEFAULT '',
               kind        TEXT NOT NULL DEFAULT 'other',
               value       REAL DEFAULT 0,
               unit        TEXT DEFAULT '',
               status      TEXT DEFAULT 'normal',
               recorded_at TEXT NOT NULL,
               UNIQUE(device_id, sensor_key)
             )",
        ],
        [
            'notification_deliveries',
            "CREATE TABLE notification_deliveries (
               id         INT AUTO_INCREMENT PRIMARY KEY,
               alert_id   INT NOT NULL,
               channel    VARCHAR(16) NOT NULL,
               recipient  VARCHAR(40) NOT NULL,
               level      VARCHAR(16) DEFAULT 'warning',
               sent_at    DATETIME NOT NULL,
               INDEX idx_nd_alert (alert_id),
               INDEX idx_nd_dedupe (alert_id, channel, recipient)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE notification_deliveries (
               id         INTEGER PRIMARY KEY AUTOINCREMENT,
               alert_id   INTEGER NOT NULL,
               channel    TEXT NOT NULL,
               recipient  TEXT NOT NULL,
               level      TEXT DEFAULT 'warning',
               sent_at    TEXT NOT NULL
             )",
        ],
        [
            'notification_log',
            "CREATE TABLE notification_log (
               id          INT AUTO_INCREMENT PRIMARY KEY,
               alert_id    INT DEFAULT NULL,
               channel     VARCHAR(16) NOT NULL,
               recipient   VARCHAR(40) NOT NULL,
               status      VARCHAR(16) NOT NULL DEFAULT 'sent',
               detail      TEXT,
               created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               INDEX idx_nlog_alert (alert_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE notification_log (
               id          INTEGER PRIMARY KEY AUTOINCREMENT,
               alert_id    INTEGER,
               channel     TEXT NOT NULL,
               recipient   TEXT NOT NULL,
               status      TEXT NOT NULL DEFAULT 'sent',
               detail      TEXT,
               created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
             )",
        ],
    ];
}

/** [表, 列, MySQL 定义, SQLite 定义] */
function pending_columns(): array
{
    return [
        ['walls', 'curve', "INT NOT NULL DEFAULT 0 COMMENT '弧高(cm)，0 为直墙'", 'INTEGER NOT NULL DEFAULT 0'],
        ['devices', 'monitor_mode', "VARCHAR(16) NOT NULL DEFAULT 'none'", "TEXT NOT NULL DEFAULT 'none'"],
        ['devices', 'last_heartbeat_at', 'DATETIME DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_enabled', 'TINYINT NOT NULL DEFAULT 0', 'INTEGER NOT NULL DEFAULT 0'],
        ['devices', 'snmp_version', "VARCHAR(8) NOT NULL DEFAULT '2c'", "TEXT NOT NULL DEFAULT '2c'"],
        ['devices', 'snmp_host', 'VARCHAR(128) DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_port', 'INT NOT NULL DEFAULT 161', 'INTEGER NOT NULL DEFAULT 161'],
        ['devices', 'snmp_community', 'VARCHAR(128) DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_v3_user', 'VARCHAR(64) DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_v3_auth_proto', "VARCHAR(16) DEFAULT 'SHA'", "TEXT DEFAULT 'SHA'"],
        ['devices', 'snmp_v3_auth_pass', 'VARCHAR(128) DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_v3_priv_proto', "VARCHAR(16) DEFAULT 'AES'", "TEXT DEFAULT 'AES'"],
        ['devices', 'snmp_v3_priv_pass', 'VARCHAR(128) DEFAULT NULL', 'TEXT'],
        ['devices', 'snmp_profile', 'VARCHAR(64) DEFAULT NULL', 'TEXT'],
        ['notification_recipients', 'escalate_min', 'INT NOT NULL DEFAULT 15', 'INTEGER NOT NULL DEFAULT 15'],
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
