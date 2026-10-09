<?php
/**
 * 公共辅助函数
 */

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 读取 JSON 请求体 */
function json_in(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $_POST ?: [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function param(string $key, $default = null)
{
    return $_GET[$key] ?? $_POST[$key] ?? $default;
}

function int_param(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $_POST[$key] ?? $default);
}

function arr_get(array $a, string $k, $default = null)
{
    return array_key_exists($k, $a) ? $a[$k] : $default;
}

/** 为 IN (...) 生成等量占位符 */
function placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

/** 只保留白名单字段，避免前端写入未知列 */
function pick(array $src, array $fields): array
{
    $out = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $src)) {
            $out[$f] = $src[$f] === '' ? null : $src[$f];
        }
    }
    return $out;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** U 位冲突检测：返回冲突的设备名，无冲突返回 null */
function u_conflict(int $rackId, int $uStart, int $uSize, int $ignoreId = 0): ?string
{
    $end = $uStart + $uSize - 1;
    $rows = fetch_all(
        'SELECT name, u_start, u_size FROM devices WHERE rack_id = ? AND id <> ?',
        [$rackId, $ignoreId]
    );
    foreach ($rows as $r) {
        $rs = (int) $r['u_start'];
        $re = $rs + (int) $r['u_size'] - 1;
        if ($uStart <= $re && $end >= $rs) {
            return $r['name'] . "（U{$rs}-U{$re}）";
        }
    }
    return null;
}

/** 机柜占用统计 */
function rack_stats(int $rackId): array
{
    $row = fetch_one(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(u_size), 0) AS used_u,
            COALESCE(SUM(power_w), 0) AS power,
            SUM(CASE WHEN status = 'online'  THEN 1 ELSE 0 END) AS online,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) AS warning,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline,
            SUM(CASE WHEN type_key = 'blank' THEN 1 ELSE 0 END) AS blanks
         FROM devices WHERE rack_id = ?",
        [$rackId]
    ) ?: [];
    return [
        'total'   => (int) ($row['total'] ?? 0),
        'used_u'  => (int) ($row['used_u'] ?? 0),
        'power'   => (int) ($row['power'] ?? 0),
        'online'  => (int) ($row['online'] ?? 0),
        'warning' => (int) ($row['warning'] ?? 0),
        'offline' => (int) ($row['offline'] ?? 0),
        'blanks'  => (int) ($row['blanks'] ?? 0),
    ];
}

/** 页面统一头部导航 */
function render_nav(string $active, ?array $me = null): void
{
    $items = [
        'index'    => ['运维大屏', 'ui-plan'],
        'planner'  => ['平面图画板', 'ui-wall'],
        'racks'    => ['机柜 U 位', 'ui-rack'],
        'devices'  => ['设备清单', 'server'],
        'settings' => ['系统设置', 'ui-edit'],
    ];
    echo '<nav class="topnav"><div class="brand"><svg class="ico"><use href="#ico-ui-rack"/></svg><span>'
        . e(setting('app_name')) . '</span></div><div class="navlinks">';
    foreach ($items as $file => [$label, $icon]) {
        $cls = $active === $file ? 'navlink on' : 'navlink';
        echo "<a class=\"$cls\" href=\"$file.php\"><svg class=\"ico\"><use href=\"#ico-$icon\"/></svg>$label</a>";
    }
    echo '</div><div class="navright">'
        . '<a class="navlink wall-go" href="wall.php" target="_blank" title="在新标签页打开无导航的大屏画面">'
        . '<svg class="ico"><use href="#ico-ui-cube"/></svg>大屏模式</a>'
        . '<span class="clock num" id="navclock">--:--:--</span>';
    if ($me) {
        echo '<span class="navuser" title="' . e($me['username']) . '">'
            . e($me['display_name'] ?: $me['username']) . '</span>'
            . '<a class="navlink" href="logout.php" title="退出登录">'
            . '<svg class="ico"><use href="#ico-ui-close"/></svg></a>';
    }
    echo '</div></nav>';
}
