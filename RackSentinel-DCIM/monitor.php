<?php
$PAGE  = 'monitor';
$TITLE = '监控与通知';
require __DIR__ . '/partials/head.php';
?>
<div class="setwrap" style="max-width:1100px">
  <div class="card">
    <div class="card-t"><h3>采集与数据模式</h3><span class="en">INGEST</span></div>
    <p class="hint" style="margin-bottom:12px">
      采集 API 入口：<code class="mono">api/ingest.php?r=...</code>（Bearer 令牌）。
      完整说明见仓库 <code>docs/INGEST_API.md</code>。
    </p>
    <form method="post" action="settings.php" class="setform" id="monForm">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="site">
      <input type="hidden" name="redirect" value="monitor.php?saved=1">
      <label class="fld">
        <span>大屏指标模拟</span>
        <select name="metrics_simulation">
          <option value="0" <?= setting('metrics_simulation', '0') === '0' ? 'selected' : '' ?>>关闭（真实上报数据）</option>
          <option value="1" <?= setting('metrics_simulation', '0') === '1' ? 'selected' : '' ?>>开启（演示随机游走）</option>
        </select>
        <em class="hint">生产环境请关闭模拟，由主机 Agent / SNMP 轮询写入指标。</em>
      </label>
      <div class="row2">
        <label class="fld"><span>心跳超时 (秒)</span>
          <input type="number" name="heartbeat_stale_sec" min="30" value="<?= e(setting('heartbeat_stale_sec', '180')) ?>">
        </label>
        <label class="fld"><span>单令牌限流 (次/分钟)</span>
          <input type="number" name="ingest_rate_per_min" min="10" value="<?= e(setting('ingest_rate_per_min', '120')) ?>">
        </label>
      </div>
      <button class="btn primary" type="submit"><svg class="ico"><use href="#ico-ui-save"/></svg>保存采集设置</button>
    </form>
  </div>

  <div class="card">
    <div class="card-t"><h3>采集代理</h3><span class="en">AGENTS</span></div>
    <div class="flex" style="margin-bottom:10px;flex-wrap:wrap;gap:8px">
      <button class="btn primary" id="btnAgentHost">+ 主机 Agent</button>
      <button class="btn" id="btnAgentSnmp">+ SNMP 轮询器令牌</button>
      <button class="btn" id="btnAgentNotify">+ 通知器令牌</button>
    </div>
    <table class="grid" style="width:100%">
      <thead><tr>
        <th>名称</th><th>类型</th><th>绑定设备</th><th>令牌前缀</th><th>最近上报</th><th>状态</th><th></th>
      </tr></thead>
      <tbody id="agentRows"><tr><td colspan="7" class="empty">加载中…</td></tr></tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-t"><h3>告警通知（腾讯云）</h3><span class="en">NOTIFY</span></div>
    <p class="hint">SecretKey 请勿写入代码库；优先使用环境变量 <code>TENCENT_SECRET_ID</code> / <code>TENCENT_SECRET_KEY</code>。设置页保存的密钥字段仅作备用。</p>
    <form method="post" action="settings.php" class="setform">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="site">
      <input type="hidden" name="redirect" value="monitor.php?saved=1">
      <label class="fld"><span>启用外部通知</span>
        <select name="notify_enabled">
          <option value="0" <?= setting('notify_enabled', '0') === '0' ? 'selected' : '' ?>>关闭</option>
          <option value="1" <?= setting('notify_enabled', '0') === '1' ? 'selected' : '' ?>>开启</option>
        </select>
      </label>
      <div class="row2">
        <label class="fld"><span>SecretId</span><input name="tencent_secret_id" value="<?= e(setting('tencent_secret_id')) ?>"></label>
        <label class="fld"><span>SecretKey（留空不修改）</span><input type="password" name="tencent_secret_key" placeholder="••••••••" autocomplete="new-password"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>短信 SdkAppId</span><input name="tencent_sms_app_id" value="<?= e(setting('tencent_sms_app_id')) ?>"></label>
        <label class="fld"><span>短信模板 ID</span><input name="tencent_sms_template_id" value="<?= e(setting('tencent_sms_template_id')) ?>"></label>
      </div>
      <label class="fld"><span>短信签名</span><input name="tencent_sms_sign" value="<?= e(setting('tencent_sms_sign')) ?>"></label>
      <div class="row2">
        <label class="fld"><span>语音 SdkAppId</span><input name="tencent_vms_app_id" value="<?= e(setting('tencent_vms_app_id')) ?>"></label>
        <label class="fld"><span>语音模板 ID</span><input name="tencent_vms_template_id" value="<?= e(setting('tencent_vms_template_id')) ?>"></label>
      </div>
      <label class="fld"><span>去重间隔 (秒)</span><input type="number" name="notify_dedupe_sec" value="<?= e(setting('notify_dedupe_sec', '600')) ?>"></label>
      <button class="btn primary" type="submit"><svg class="ico"><use href="#ico-ui-save"/></svg>保存通知配置</button>
    </form>

    <div class="card-t" style="margin-top:18px"><h3>收件人</h3></div>
    <button class="btn sm primary" id="btnAddRcpt">+ 收件人</button>
    <table class="grid" style="width:100%;margin-top:8px">
      <thead><tr><th>姓名</th><th>语音号码</th><th>短信号码</th><th>最低级别</th><th>启用</th><th></th></tr></thead>
      <tbody id="rcptRows"></tbody>
    </table>
  </div>
</div>
<?php if (param('saved')): ?><div class="flash ok" style="position:fixed;top:60px;right:16px;z-index:80">设置已保存</div><?php endif; ?>
<script src="assets/js/common.js"></script>
<script src="assets/js/monitor.js"></script>
<script>startClock();</script>
</body>
</html>
