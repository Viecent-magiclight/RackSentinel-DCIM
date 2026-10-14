<?php
$PAGE  = 'devices';
$TITLE = '设备监控详情';
require __DIR__ . '/partials/head.php';
$id = (int) ($_GET['id'] ?? 0);
?>
<div class="page-wrap page-wrap--stack">
  <div class="card">
    <div class="card-t"><h3 id="devTitle">设备监控</h3>
      <a class="btn sm" href="racks.php">返回机柜</a>
    </div>
    <div id="summary" class="hint">加载中…</div>
  </div>
  <div class="monitor-detail-pair">
    <div class="card"><div class="card-t"><h3>指标趋势</h3></div><canvas id="trend" height="180"></canvas></div>
    <div class="card"><div class="card-t"><h3>硬件传感器</h3></div><div id="sensors"></div></div>
  </div>
  <div class="card table-card">
    <div class="card-t"><h3>网络接口</h3><span class="en">IF-MIB</span></div>
    <table class="grid" style="width:100%">
      <thead><tr>
        <th>索引</th><th>名称</th><th>管理</th><th>运行</th><th>速率 In/Out Mbps</th><th>错误</th>
      </tr></thead>
      <tbody id="ifaces"></tbody>
    </table>
  </div>
</div>
<script>const DEVICE_ID = <?= (int) $id ?>;</script>
<script src="assets/js/common.js"></script>
<script src="assets/js/device-monitor.js"></script>
<script>startClock();</script>
</body></html>
