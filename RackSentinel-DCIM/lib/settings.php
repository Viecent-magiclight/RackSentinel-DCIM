<?php
/**
 * 站点配置：存在 settings 表里的键值对，设置页可改，改完立即生效。
 *
 * config/config.php 里的同名项只作为「还没在设置页改过」时的默认值，
 * 所以部署时不用动代码也能改标题。
 */

/** 配置项定义：键 => [标签, 默认值, 说明] */
function setting_schema(): array
{
    return [
        'app_name'      => ['平台名称', cfg('app.name'), '显示在导航栏左上角和浏览器标签'],
        'wall_title'    => ['大屏标题', cfg('app.name'), '大屏独立页顶部居中显示的主标题'],
        'wall_subtitle' => ['大屏副标题', '', '显示在主标题下方，留空则不显示'],
        'poll_ms'       => ['数据刷新间隔 (毫秒)', (string) cfg('app.poll_ms'), '大屏自动拉取最新数据的间隔，建议 3000 以上'],
    ];
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = [];
    foreach (setting_schema() as $k => [, $default]) {
        $cache[$k] = $default;
    }
    try {
        foreach (fetch_all('SELECT k, v FROM settings') as $row) {
            if (array_key_exists($row['k'], $cache) && $row['v'] !== null && $row['v'] !== '') {
                $cache[$row['k']] = $row['v'];
            }
        }
    } catch (Throwable $e) {
        // 老库还没迁移出 settings 表时退回默认值，不要让整站 500
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    return (string) (settings_all()[$key] ?? $default);
}

function settings_save(array $values): void
{
    $schema = setting_schema();
    foreach ($values as $k => $v) {
        if (!isset($schema[$k])) {
            continue;
        }
        // 没有现成的 upsert 方言封装，先删后插最省事，配置项数量很少
        q('DELETE FROM settings WHERE k = ?', [$k]);
        q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, (string) $v]);
    }
    settings_all(true);
}
