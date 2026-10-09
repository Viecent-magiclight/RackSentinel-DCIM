<div class="dash">

  <!-- 左列 -->
  <div class="dash-col left">
    <div class="card">
      <div class="card-t"><h3>机房概览</h3><span class="en">ROOM OVERVIEW</span></div>
      <div id="roomInfo" class="hint"></div>
      <div style="margin-top:8px;display:grid;grid-template-columns:94px 1fr;gap:12px;align-items:center">
        <canvas id="capRing" style="width:94px;height:94px"></canvas>
        <div id="capText" class="hint"></div>
      </div>
    </div>

    <div class="card">
      <div class="card-t"><h3>设备类型分布</h3><span class="en" id="byTypeEn">BY TYPE</span></div>
      <div class="barlist fit" id="byType"></div>
    </div>

    <div class="card">
      <div class="card-t"><h3>操作系统分布</h3><span class="en" id="byOsEn">BY OS</span></div>
      <div class="barlist fit" id="byOs"></div>
    </div>

    <div class="card">
      <div class="card-t"><h3>环境传感器</h3><span class="en" id="sensorsEn">SENSORS</span></div>
      <div class="barlist fit" id="sensors"></div>
    </div>
  </div>

  <!-- 中间 -->
  <div class="dash-main">
    <div class="kpi4" id="kpis"></div>

    <div class="stage" id="stage">
      <div class="stage-bar">
        <button class="btn sm on" id="tab3d"><svg class="ico-sm"><use href="#ico-ui-cube"/></svg>3D 机房</button>
        <button class="btn sm" id="tab2d"><svg class="ico-sm"><use href="#ico-ui-plan"/></svg>平面图</button>
        <button class="btn sm on" id="btnAuto"><svg class="ico-sm"><use href="#ico-ui-redo"/></svg>自动巡航</button>
        <button class="btn sm" id="btnReset"><svg class="ico-sm"><use href="#ico-ui-fit"/></svg>复位视角</button>
      </div>
      <div id="view3d" style="position:absolute;inset:0"></div>
      <svg id="view2d" style="position:absolute;inset:0;display:none"></svg>
      <div id="labels" style="position:absolute;inset:0;pointer-events:none;overflow:hidden"></div>
      <div class="stage-legend">
        <span><i class="dot" style="background:#2ef2a3"></i>在线</span>
        <span><i class="dot" style="background:#ffb020"></i>告警</span>
        <span><i class="dot" style="background:#ff4d6a"></i>离线</span>
        <span><i class="dot" style="background:#7c8cff"></i>维护</span>
      </div>
      <div class="canvas-hud" id="loading">正在加载三维场景…</div>

      <!-- 选中机柜：悬浮在场景之上，不占用右侧栏 -->
      <aside class="stage-panel" id="pickCard" hidden>
        <!-- 外层只负责定位：切角 clip-path 必须留在内层，否则会把左侧浮卡裁掉 -->
        <div class="sp-in">
          <div class="sp-head">
            <b id="pickName"></b>
            <span class="hint" id="pickLabel"></span>
            <button class="btn sm icon" id="pickClose" title="关闭">
              <svg class="ico-sm"><use href="#ico-ui-close"/></svg></button>
          </div>
          <div class="sp-sum hint" id="pickSum"></div>
          <div class="toplist sp-list" id="pickBox"></div>
        </div>

        <!-- 设备详情：贴在机柜卡左侧弹出，移开鼠标即收起 -->
        <div class="dev-pop" id="devPop" hidden><div class="dev-pop-in" id="devPopIn"></div></div>
      </aside>
    </div>

    <div class="card trend-card">
      <div class="card-t"><h3>机房总负载趋势</h3>
        <span class="en" id="trendLegend">CPU · 温度 · 功率</span></div>
      <canvas id="trend" class="grow" style="width:100%"></canvas>
    </div>
  </div>

  <!-- 右列 -->
  <div class="dash-col right">
    <div class="card">
      <div class="card-t"><h3>实时告警</h3><span class="en" id="alertCount">0 ACTIVE</span></div>
      <div class="alertlist fit" id="alerts"></div>
    </div>

    <div class="card">
      <div class="card-t"><h3>机柜容量 TOP</h3><span class="en" id="rackTopEn">RACK LOAD</span></div>
      <div class="barlist fit" id="rackTop"></div>
    </div>

    <div class="card">
      <div class="card-t"><h3>高负载设备</h3><span class="en" id="hotDevEn">HOT DEVICES</span></div>
      <div class="toplist fit" id="hotDev"></div>
    </div>
  </div>
</div>
