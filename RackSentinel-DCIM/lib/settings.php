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
        'metrics_simulation' => ['大屏指标模拟', '0', '1=使用随机游走模拟（演示）；0=仅展示 Agent/SNMP 上报的真实数据'],
        'heartbeat_stale_sec' => ['主机心跳超时 (秒)', '180', '超过该时间未收到心跳则将 Agent 设备标为离线'],
        'ingest_rate_per_min' => ['单令牌每分钟请求上限', '120', '采集 API 限流，防止异常刷写'],
        'thresh_cpu_warn'  => ['CPU 预警阈值 %', '85', '超过则生成 warning 告警'],
        'thresh_cpu_crit'  => ['CPU 严重阈值 %', '95', '超过则生成 critical 告警'],
        'thresh_mem_warn'  => ['内存预警阈值 %', '85', ''],
        'thresh_temp_warn' => ['温度预警阈值 ℃', '55', ''],
        'notify_enabled'   => ['启用外部通知', '0', '1=告警通知器可向腾讯云发送语音/短信'],
        'notify_dedupe_sec'=> ['通知去重间隔 (秒)', '600', '同一告警标题在该时间内不重复外呼'],
        'tencent_secret_id' => ['腾讯云 SecretId', '', '留空则从环境变量 TENCENT_SECRET_ID 读取'],
        'tencent_secret_key'=> ['腾讯云 SecretKey', '', '仅保存掩码预览；完整密钥请用环境变量 TENCENT_SECRET_KEY'],
        'tencent_sms_app_id' => ['短信 SdkAppId', '', ''],
        'tencent_sms_sign'   => ['短信签名', '', ''],
        'tencent_sms_template_id' => ['短信模板 ID', '', ''],
        'tencent_vms_app_id' => ['语音 SdkAppId', '', ''],
        'tencent_vms_template_id' => ['语音模板 ID', '', ''],
        'notify_rate_per_hour' => ['每小时最大外呼次数', '30', '全局限流，防止风暴'],
        'notify_voice_mode' => ['语音模式', 'tts', 'tts=SendTtsVoice，code=SendCodeVoice'],
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
