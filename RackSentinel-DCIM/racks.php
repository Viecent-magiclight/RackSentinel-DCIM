<?php
$PAGE  = 'racks';
$TITLE = '机柜 U 位编辑';
require __DIR__ . '/partials/head.php';
?>
<div class="workspace ws-racks">

  <!-- 机柜列表 -->
  <div class="listpane">
    <div class="flex" style="margin-bottom:10px">
      <input type="search" id="rkSearch" placeholder="搜索机柜编号…">
    </div>
    <div class="racklist" id="rackList"></div>
  </div>

  <!-- 机柜立面 -->
  <div style="display:flex;flex-direction:column;overflow:hidden">
    <div class="flex" style="padding:10px 14px;border-bottom:1px solid var(--line);gap:10px">
      <span id="rkTitle" style="font-size:15px;color:#eafcff;letter-spacing:1px">请选择机柜</span>
      <span class="tag" id="rkUsage" style="color:#00e5ff">—</span>
      <span class="tag" id="rkPower" style="color:#ffb020">—</span>
      <div style="margin-left:auto" class="flex">
        <span class="hint" style="margin-right:4px">视图</span>
        <button class="btn sm" id="viewOne"><svg class="ico-sm"><use href="#ico-ui-rack"/></svg>单柜</button>
        <button class="btn sm on" id="viewRow"><svg class="ico-sm"><use href="#ico-ui-grid"/></svg>整列对比</button>
        <button class="btn sm" id="btnDensity">紧凑</button>
        <button class="btn sm" id="btnExport" title="每个机柜导出为一个 Sheet">
          <svg class="ico-sm"><use href="#ico-ui-export"/></svg>导出 Excel</button>
        <button class="btn sm primary" id="btnAdd"><svg class="ico-sm"><use href="#ico-ui-plus"/></svg>新增设备</button>
      </div>
    </div>
    <div class="rack-stage grow scroll" id="stage">
      <div class="empty">从左侧选择一个机柜</div>
    </div>
  </div>

  <!-- 设备属性 -->
  <div class="sidepane">
    <div>
      <h4><svg class="ico"><use href="#ico-server"/></svg>设备详情 / 编辑</h4>
      <div class="empty" id="dvEmpty">点击机柜中的设备即可在此编辑<br>点击空白 U 位可直接安装新设备</div>
      <div id="dvBox" style="display:none"></div>
    </div>
    <div id="typeLegendBox">
      <h4><svg class="ico"><use href="#ico-ui-grid"/></svg>图例</h4>
      <div class="rack-legend" id="typeLegend"></div>
    </div>
    <div style="margin-top:auto">
      <h4><svg class="ico"><use href="#ico-ui-link"/></svg>操作说明</h4>
      <div class="hint">
        · 单击设备后，在上方 <b>直接修改</b> 名称 / 主机名 / IP 等，点 <b>保存修改</b> 写入数据库。<br>
        · 上下 <b>拖动设备</b> 改 U 位，拖到相邻机柜可搬迁，<b>松手即保存</b>，冲突会被拒绝。<br>
        · 单击 <b>空白 U 位</b> 快速安装设备，起始 U 已自动填好。<br>
        · U 位从机柜 <b>底部 U1</b> 向上编号。
      </div>
    </div>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script src="assets/js/rackview.js"></script>
<script>startClock();</script>
</body>
</html>
