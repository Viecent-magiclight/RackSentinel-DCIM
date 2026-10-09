<?php
/**
 * PDO 连接与方言适配（MySQL / SQLite 双驱动）
 */

function cfg(?string $key = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/config.php';
        date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Shanghai');
    }
    if ($key === null) {
        return $cfg;
    }
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return null;
        }
        $node = $node[$part];
    }
    return $node;
}

function db_driver(): string
{
    return cfg('driver') === 'sqlite' ? 'sqlite' : 'mysql';
}

/**
 * 获取 PDO 连接。$withDatabase=false 时连接到服务器但不指定库（用于建库）。
 */
function db(bool $withDatabase = true): PDO
{
    static $pool = [];
    $key = $withDatabase ? 'main' : 'server';
    if (isset($pool[$key])) {
        return $pool[$key];
    }

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (db_driver() === 'sqlite') {
        $path = cfg('sqlite.path');
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        $c   = cfg('mysql');
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $c['host'], $c['port'], $c['charset']);
        if ($withDatabase) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], $c['port'], $c['database'], $c['charset']
            );
        }
        $pdo = new PDO($dsn, $c['username'], $c['password'], $opts);
    }

    return $pool[$key] = $pdo;
}

/** 数据库 / 表是否已经初始化 */
function db_installed(): bool
{
    try {
        db()->query('SELECT 1 FROM rooms LIMIT 1')->fetch();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_val(string $sql, array $params = [], $default = null)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function insert_row(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql  = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', $cols),
        implode(',', array_map(fn($c) => ':' . $c, $cols))
    );
    q($sql, $data);
    return (int) db()->lastInsertId();
}

function update_row(string $table, int $id, array $data): void
{
    if (!$data) {
        return;
    }
    $set = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
    $data['__id'] = $id;
    q("UPDATE $table SET $set WHERE id = :__id", $data);
}

function delete_row(string $table, int $id): void
{
    q("DELETE FROM $table WHERE id = ?", [$id]);
}

/**
 * 把建表脚本拆成可逐条执行的语句。
 * 必须先剥掉 -- 行注释再按分号切分：否则「注释 + 语句」会被当成一整段注释跳过。
 */
function sql_statements(string $sql): array
{
    $clean = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $clean)), fn($s) => $s !== ''));
}

function now_str(): string
{
    return date('Y-m-d H:i:s');
}
