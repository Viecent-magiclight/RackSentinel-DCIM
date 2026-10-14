<?php
$PAGE  = 'alerts';
$TITLE = '告警中心';
require __DIR__ . '/partials/head.php';
?>
<div class="page-wrap page-wrap--stack">
  <div class="card table-card">
    <div class="card-t"><h3>活动告警</h3><span class="en">ALERTS</span></div>
    <div class="flex" style="gap:8px;margin-bottom:10px">
      <select id="fLevel" style="width:140px">
        <option value="">全部级别</option>
        <option value="critical">严重</option>
        <option value="warning">预警</option>
        <option value="info">信息</option>
      </select>
      <button class="btn" id="btnRefresh">刷新</button>
    </div>
    <table class="grid" style="width:100%">
      <thead><tr>
        <th style="width:120px">时间</th><th style="width:90px">级别</th><th>标题</th><th>设备</th><th style="width:80px"></th>
      </tr></thead>
      <tbody id="rows"></tbody>
    </table>
  </div>
</div>
<script src="assets/js/common.js"></script>
<script src="assets/js/alerts.js"></script>
<script>startClock();</script>
</body></html>
