<?php
$PAGE  = 'planner';
$TITLE = '平面图画板';
require __DIR__ . '/partials/head.php';
?>
<div class="workspace ws-planner">

  <!-- 左侧工具栏 -->
  <div class="toolrail" id="rail">
    <button data-tool="select"  data-tip="选择 / 移动  (V)" class="on"><svg><use href="#ico-ui-cursor"/></svg></button>
    <button data-tool="pan"     data-tip="平移画布  (空格拖动)"><svg><use href="#ico-ui-fit"/></svg></button>
    <div class="sep"></div>
    <button data-tool="wall"    data-tip="画墙体  (W)"><svg><use href="#ico-ui-wall"/></svg></button>
    <button data-tool="glass"   data-tip="玻璃隔断  (G)"><svg><use href="#ico-ui-window"/></svg></button>
    <button data-tool="door"    data-tip="门  (D)"><svg><use href="#ico-ui-door"/></svg></button>
    <button data-tool="window"  data-tip="窗  (N)"><svg><use href="#ico-ui-window"/></svg></button>
    <button data-tool="pillar"  data-tip="柱子  (P)"><svg><use href="#ico-ui-pillar"/></svg></button>
    <div class="sep"></div>
    <button data-tool="rack"    data-tip="放置机柜  (R)"><svg><use href="#ico-ui-rack"/></svg></button>
    <button data-tool="sensor"  data-tip="放置传感器  (S)"><svg><use href="#ico-ui-sensor"/></svg></button>
    <div class="sep"></div>
    <button id="btnGrid"  data-tip="网格吸附 开/关" class="on"><svg><use href="#ico-ui-grid"/></svg></button>
    <button id="btnUndo"  data-tip="撤销  (Ctrl+Z)"><svg><use href="#ico-ui-undo"/></svg></button>
    <button id="btnRedo"  data-tip="重做  (Ctrl+Y)"><svg><use href="#ico-ui-redo"/></svg></button>
    <button id="btnDel"   data-tip="删除选中  (Delete)"><svg><use href="#ico-ui-trash"/></svg></button>
    <div class="sep"></div>
    <button id="btnSave"  data-tip="保存布局  (Ctrl+S)"><svg><use href="#ico-ui-save"/></svg></button>
  </div>

  <!-- 画布 -->
  <div class="canvas-wrap" id="cv">
    <svg id="svg"></svg>
    <div class="canvas-tip" id="tip"></div>
    <div class="canvas-hud">
      <span>坐标 <b id="hudXY">0, 0</b> cm</span>
      <span>缩放 <b id="hudZoom">100%</b></span>
      <span>墙体 <b id="hudWall">0</b></span>
      <span>机柜 <b id="hudRack">0</b></span>
    </div>
    <div class="zoombar">
      <button id="zOut">－</button>
      <button id="zFit"><svg class="ico-sm"><use href="#ico-ui-fit"/></svg></button>
      <button id="zIn">＋</button>
    </div>
  </div>

  <!-- 右侧属性面板 -->
  <div class="sidepane">
    <div>
      <h4><svg class="ico"><use href="#ico-ui-plan"/></svg>机房参数</h4>
      <label class="fld"><span>机房名称</span><input type="text" id="rmName"></label>
      <div class="row2">
        <label class="fld"><span>宽度 (cm)</span><input type="number" id="rmW" min="200" step="10"></label>
        <label class="fld"><span>进深 (cm)</span><input type="number" id="rmD" min="200" step="10"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>网格 (cm)</span><input type="number" id="rmG" min="1" step="5"></label>
        <label class="fld"><span>层高 (cm)</span><input type="number" id="rmH" min="200" step="10"></label>
      </div>
    </div>

    <div id="propBox">
      <h4><svg class="ico"><use href="#ico-ui-edit"/></svg>对象属性</h4>
      <div class="empty" id="propEmpty">在画布中选中一个对象</div>
      <div id="propForm" style="display:none"></div>
    </div>

    <div style="margin-top:auto">
      <h4><svg class="ico"><use href="#ico-ui-link"/></svg>使用说明</h4>
      <div class="hint">
        · 选 <code>墙体</code> 后在画布 <b>按下拖拽</b> 即可画出一段墙；按住 <code>Shift</code> 强制水平/垂直。<br>
        · <b>弧形墙</b>：选中墙体后拖动中间的 <b style="color:#00e5ff">◆</b> 手柄即可弯曲，拖动时按
          <code>Shift</code> 或双击手柄可拉直，也能在上方「弧高」里直接填数值。<br>
        · 选 <code>机柜</code> 后 <b>单击</b> 放置，单击已有机柜可拖动位置、在右侧改名或旋转。<br>
        · 双击机柜进入该机柜的 <b>42U 立面编辑</b>。<br>
        · 滚轮缩放，按住空格或中键平移画布。<br>
        · 所有改动需点 <code>保存布局</code> 才会写入数据库。
      </div>
    </div>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script src="assets/js/planner.js"></script>
<script>startClock();</script>
</body>
</html>
