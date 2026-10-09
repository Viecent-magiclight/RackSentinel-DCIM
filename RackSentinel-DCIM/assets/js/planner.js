/* =========================================================================
   平面图画板：绘制墙体 / 门窗 / 柱子，摆放机柜与传感器
   坐标系统一使用「厘米」，SVG viewBox 即世界坐标
   ========================================================================= */
(() => {
const svg   = $('#svg');
const wrap  = $('#cv');
const tipEl = $('#tip');

/* ---------------- 状态 ---------------- */
const S = {
  room: null,
  walls: [], racks: [], sensors: [],
  tool: 'select',
  snap: true,
  sel: null,            // {k:'wall'|'rack'|'sensor', i:index}
  dirty: false,
};
const V = { x: -80, y: -80, w: 1400, h: 1000 };   // viewBox
let drag = null;                                   // 当前拖拽上下文
const undoStack = [], redoStack = [];

const WALLK = ['wall', 'glass', 'door', 'window', 'pillar'];
let tmpSeq = 0;

/* ---------------- 视图 ---------------- */
function applyView() {
  const r = wrap.getBoundingClientRect();
  V.h = V.w * (r.height / Math.max(1, r.width));
  svg.setAttribute('viewBox', `${V.x} ${V.y} ${V.w} ${V.h}`);
  $('#hudZoom').textContent = Math.round(r.width / V.w * 100) + '%';
}
function fitView() {
  if (!S.room) return;
  const pad = 110;
  const r = wrap.getBoundingClientRect();
  const W = +S.room.width + pad * 2, D = +S.room.depth + pad * 2;
  V.w = Math.max(W, D * (r.width / Math.max(1, r.height)));
  V.x = -pad - (V.w - W) / 2;
  applyView();
  V.y = -pad - (V.h - D) / 2;
  applyView();
}
function zoomAt(factor, cx, cy) {
  const p = toWorld(cx, cy);
  const nw = clamp(V.w * factor, 150, 12000);
  const k  = nw / V.w;
  V.x = p.x - (p.x - V.x) * k;
  V.y = p.y - (p.y - V.y) * k;
  V.w = nw;
  applyView();
}
function toWorld(clientX, clientY) {
  const m = svg.getScreenCTM().inverse();
  const p = svg.createSVGPoint();
  p.x = clientX; p.y = clientY;
  const q = p.matrixTransform(m);
  return { x: q.x, y: q.y };
}
const snap = v => S.snap && S.room ? Math.round(v / S.room.grid) * S.room.grid : Math.round(v);

/* ---------------- 历史 ---------------- */
function snapshot() {
  return JSON.stringify({ walls: S.walls, racks: S.racks, sensors: S.sensors, room: S.room });
}
function pushUndo(json = null) {
  undoStack.push(json || snapshot());
  if (undoStack.length > 60) undoStack.shift();
  redoStack.length = 0;
  S.dirty = true;
}
function restore(json) {
  const d = JSON.parse(json);
  S.walls = d.walls; S.racks = d.racks; S.sensors = d.sensors; S.room = d.room;
  S.sel = null; render(); syncRoomInputs();
}
function undo() { if (!undoStack.length) return; redoStack.push(snapshot()); restore(undoStack.pop()); }
function redo() { if (!redoStack.length) return; undoStack.push(snapshot()); restore(redoStack.pop()); }

/* ---------------- 渲染 ---------------- */
function render() {
  if (!S.room) return;
  const g = S.room.grid || 10;
  const parts = [];

  parts.push(`<defs>
    <pattern id="gfine" width="${g}" height="${g}" patternUnits="userSpaceOnUse">
      <path d="M ${g} 0 L 0 0 0 ${g}" fill="none" stroke="rgba(0,229,255,.07)" stroke-width="1"/>
    </pattern>
    <pattern id="gbold" width="${g * 10}" height="${g * 10}" patternUnits="userSpaceOnUse">
      <rect width="${g * 10}" height="${g * 10}" fill="url(#gfine)"/>
      <path d="M ${g * 10} 0 L 0 0 0 ${g * 10}" fill="none" stroke="rgba(0,229,255,.17)" stroke-width="1.6"/>
    </pattern>
  </defs>`);

  // 机房地面 + 网格
  parts.push(`<rect x="0" y="0" width="${S.room.width}" height="${S.room.depth}"
      fill="rgba(0,70,110,.13)" stroke="rgba(0,229,255,.35)" stroke-width="2" vector-effect="non-scaling-stroke"/>`);
  parts.push(`<rect x="0" y="0" width="${S.room.width}" height="${S.room.depth}" fill="url(#gbold)"/>`);

  // 尺寸标注
  parts.push(dimText(S.room.width / 2, -34, S.room.width + ' cm'));
  parts.push(dimText(-46, S.room.depth / 2, S.room.depth + ' cm', -90));

  // 墙体
  S.walls.forEach((w, i) => parts.push(drawWall(w, i)));
  // 机柜
  S.racks.forEach((r, i) => parts.push(drawRack(r, i)));
  // 传感器
  S.sensors.forEach((s, i) => parts.push(drawSensor(s, i)));
  // 预览
  if (drag && drag.preview) parts.push(drag.preview);

  svg.innerHTML = parts.join('');
  $('#hudWall').textContent = S.walls.length;
  $('#hudRack').textContent = S.racks.length;
  renderProps();
}

function dimText(x, y, t, rot = 0) {
  return `<text x="${x}" y="${y}" fill="#4d8099" font-size="20" text-anchor="middle"
    transform="rotate(${rot} ${x} ${y})" style="pointer-events:none">${esc(t)}</text>`;
}

function drawWall(w, i) {
  const meta = META.walls[w.kind] || META.walls.wall;
  const on   = S.sel && S.sel.k === 'wall' && S.sel.i === i;
  const th   = +w.thickness || meta.thickness;
  const d    = wallPath(w);
  const hit  = `<path d="${d}" fill="none"
      stroke="transparent" stroke-width="${Math.max(th, 26)}" stroke-linecap="round"
      data-k="wall" data-i="${i}" style="cursor:pointer"/>`;
  let body = '';

  if (w.kind === 'pillar') {
    const x = Math.min(w.x1, w.x2), y = Math.min(w.y1, w.y2);
    const ww = Math.max(Math.abs(w.x2 - w.x1), 20), hh = Math.max(Math.abs(w.y2 - w.y1), 20);
    return `<g><rect x="${x}" y="${y}" width="${ww}" height="${hh}" fill="#3c4c5c"
        stroke="${on ? '#fff' : meta.color}" stroke-width="2" vector-effect="non-scaling-stroke"/>
      <rect x="${x}" y="${y}" width="${ww}" height="${hh}" fill="transparent"
        data-k="wall" data-i="${i}" style="cursor:pointer"/>
      ${on ? selRect(x, y, ww, hh) : ''}</g>`;
  }
  if (w.kind === 'door') {
    const dx = w.x2 - w.x1, dy = w.y2 - w.y1;
    const ex = w.x1 - dy, ey = w.y1 + dx;
    const r  = Math.hypot(dx, dy);
    body = `<path d="${d}" fill="none" stroke="${meta.color}"
        stroke-width="${th}" stroke-linecap="butt" opacity=".85"/>
      <path d="M ${w.x2} ${w.y2} A ${r} ${r} 0 0 1 ${ex} ${ey}" fill="none"
        stroke="${meta.color}" stroke-width="1.4" stroke-dasharray="7 6"
        vector-effect="non-scaling-stroke" opacity=".7"/>`;
  } else if (w.kind === 'window') {
    body = `<path d="${d}" fill="none" stroke="#0a2436" stroke-width="${th + 6}"/>
      <path d="${d}" fill="none" stroke="${meta.color}" stroke-width="${th}" opacity=".9"/>`;
  } else if (w.kind === 'glass') {
    body = `<path d="${d}" fill="none" stroke="${meta.color}"
        stroke-width="${th}" opacity=".45" stroke-linecap="round"/>
      <path d="${d}" fill="none" stroke="${meta.color}"
        stroke-width="1.4" vector-effect="non-scaling-stroke" stroke-dasharray="12 8"/>`;
  } else {
    body = `<path d="${d}" fill="none" stroke="${meta.color}"
        stroke-width="${th}" stroke-linecap="round"/>
      <path d="${d}" fill="none" stroke="rgba(190,235,255,.3)"
        stroke-width="${Math.max(2, th * .28)}" stroke-linecap="round"/>`;
  }
  let marks = '';
  if (on) {
    const g = wallGeom(w);
    marks = `<line x1="${w.x1}" y1="${w.y1}" x2="${w.x2}" y2="${w.y2}" stroke="#fff" stroke-width="1.5"
         vector-effect="non-scaling-stroke" stroke-dasharray="5 5" opacity=".6"/>
       <circle cx="${w.x1}" cy="${w.y1}" r="9" fill="#fff" data-k="wp" data-i="${i}" data-e="1" style="cursor:move"/>
       <circle cx="${w.x2}" cy="${w.y2}" r="9" fill="#fff" data-k="wp" data-i="${i}" data-e="2" style="cursor:move"/>
       <rect x="${g.mx - 8}" y="${g.my - 8}" width="16" height="16" transform="rotate(45 ${g.mx} ${g.my})"
         fill="#00e5ff" stroke="#04202e" stroke-width="2" vector-effect="non-scaling-stroke"
         data-k="wc" data-i="${i}" style="cursor:ns-resize">
         <title>拖动改变弧度，双击拉直</title></rect>`;
  }
  return `<g>${body}${hit}${marks}</g>`;
}

function selRect(x, y, w, h) {
  return `<rect x="${x - 5}" y="${y - 5}" width="${w + 10}" height="${h + 10}" fill="none"
    stroke="#fff" stroke-width="1.4" stroke-dasharray="6 5" vector-effect="non-scaling-stroke"
    style="pointer-events:none"/>`;
}

function rackFill(r) {
  const st = r.stats || {};
  const used = +st.used_u || 0, tot = +r.u_total || 42;
  const ratio = clamp(used / tot, 0, 1);
  if (!used) return { fill: 'rgba(60,88,108,.75)', edge: '#5a7a8e' };
  if (ratio > .88) return { fill: 'rgba(255,77,106,.4)',  edge: '#ff4d6a' };
  if (ratio > .65) return { fill: 'rgba(255,176,32,.38)', edge: '#ffb020' };
  return { fill: 'rgba(46,242,163,.3)', edge: '#2ef2a3' };
}

function drawRack(r, i) {
  const on = S.sel && S.sel.k === 'rack' && S.sel.i === i;
  const w = +r.width || 60, d = +r.depth || 110;
  const c = rackFill(r);
  const st = r.stats || { used_u: 0, online: 0, offline: 0, warning: 0 };
  const pct = Math.round((+st.used_u || 0) / (+r.u_total || 42) * 100);
  const fontSize = Math.min(22, w * .34);
  return `<g transform="translate(${r.x},${r.y}) rotate(${r.rotation || 0})"
      data-k="rack" data-i="${i}" style="cursor:pointer">
    <rect x="${-w / 2}" y="${-d / 2}" width="${w}" height="${d}" rx="3"
      fill="${c.fill}" stroke="${on ? '#fff' : c.edge}" stroke-width="${on ? 3 : 2}" vector-effect="non-scaling-stroke"/>
    <rect x="${-w / 2}" y="${-d / 2}" width="${w}" height="7" fill="${c.edge}" opacity=".9"/>
    <rect x="${-w / 2 + 3}" y="${d / 2 - 11}" width="${(w - 6) * pct / 100}" height="5" fill="${c.edge}"/>
    <g transform="rotate(${-(r.rotation || 0)})">
      <text y="-2" fill="#eafcff" font-size="${fontSize}" font-weight="600" text-anchor="middle"
        style="pointer-events:none">${esc(r.name)}</text>
      <text y="${fontSize + 2}" fill="#9fc8da" font-size="${fontSize * .72}" text-anchor="middle"
        style="pointer-events:none">${st.used_u || 0}/${r.u_total || 42}U</text>
    </g>
    ${st.offline ? `<circle cx="${w / 2 - 8}" cy="${-d / 2 + 16}" r="5" fill="#ff4d6a"/>` : ''}
    ${st.warning ? `<circle cx="${w / 2 - 8}" cy="${-d / 2 + 30}" r="5" fill="#ffb020"/>` : ''}
    ${on ? selRect(-w / 2, -d / 2, w, d) : ''}
  </g>`;
}

function drawSensor(s, i) {
  const on = S.sel && S.sel.k === 'sensor' && S.sel.i === i;
  const meta = META.sensors[s.kind] || META.sensors.temp;
  const col = s.status === 'alarm' ? '#ff4d6a' : s.status === 'warning' ? '#ffb020' : '#2ef2a3';
  return `<g transform="translate(${s.x},${s.y})" data-k="sensor" data-i="${i}" style="cursor:pointer">
    <circle r="17" fill="rgba(4,22,36,.9)" stroke="${on ? '#fff' : col}" stroke-width="2" vector-effect="non-scaling-stroke"/>
    <circle r="7" fill="${col}" opacity=".85"/>
    <text y="38" fill="#8fc4da" font-size="15" text-anchor="middle" style="pointer-events:none">${esc(s.name)}</text>
    <text y="-24" fill="${col}" font-size="16" text-anchor="middle" style="pointer-events:none">${num(s.value)}${esc(meta.unit || '')}</text>
  </g>`;
}

/* ---------------- 属性面板 ---------------- */
function renderProps() {
  const box = $('#propForm'), emp = $('#propEmpty');
  if (!S.sel) { box.style.display = 'none'; box.innerHTML = ''; emp.style.display = ''; return; }
  box.style.display = ''; emp.style.display = 'none';
  const { k, i } = S.sel;

  if (k === 'wall') {
    const w = S.walls[i];
    box.innerHTML = `
      <label class="fld"><span>类型</span><select data-f="kind">
        ${WALLK.map(x => `<option value="${x}" ${w.kind === x ? 'selected' : ''}>${esc(META.walls[x].name)}</option>`).join('')}
      </select></label>
      <div class="row2">
        <label class="fld"><span>起点 X</span><input type="number" data-f="x1" value="${w.x1}"></label>
        <label class="fld"><span>起点 Y</span><input type="number" data-f="y1" value="${w.y1}"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>终点 X</span><input type="number" data-f="x2" value="${w.x2}"></label>
        <label class="fld"><span>终点 Y</span><input type="number" data-f="y2" value="${w.y2}"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>厚度 (cm)</span><input type="number" data-f="thickness" value="${w.thickness}"></label>
        <label class="fld"><span>弧高 (cm)</span><input type="number" data-f="curve" step="10" value="${+w.curve || 0}"></label>
      </div>
      <div class="hint">
        弦长 ${Math.round(Math.hypot(w.x2 - w.x1, w.y2 - w.y1))} cm　弧长 ${Math.round(wallLength(w))} cm<br>
        弧高填 0 即为直墙，正负号决定凸向；也可直接拖画布上的 <b style="color:#00e5ff">◆</b> 手柄
      </div>`;
    bindFields(box, w, () => { pushUndo(); render(); });
  }

  if (k === 'rack') {
    const r = S.racks[i];
    const st = r.stats || {};
    box.innerHTML = `
      <label class="fld"><span>机柜编号</span><input type="text" data-f="name" value="${esc(r.name)}"></label>
      <div class="row2">
        <label class="fld"><span>所属列</span><input type="text" data-f="row_label" value="${esc(r.row_label || '')}"></label>
        <label class="fld"><span>用途标签</span><input type="text" data-f="label" value="${esc(r.label || '')}"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>中心 X (cm)</span><input type="number" data-f="x" value="${r.x}"></label>
        <label class="fld"><span>中心 Y (cm)</span><input type="number" data-f="y" value="${r.y}"></label>
      </div>
      <div class="row3">
        <label class="fld"><span>宽</span><input type="number" data-f="width" value="${r.width}"></label>
        <label class="fld"><span>深</span><input type="number" data-f="depth" value="${r.depth}"></label>
        <label class="fld"><span>朝向°</span><input type="number" data-f="rotation" step="90" value="${r.rotation || 0}"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>总 U 数</span><input type="number" data-f="u_total" value="${r.u_total}"></label>
        <label class="fld"><span>额定功率 W</span><input type="number" data-f="power_capacity" value="${r.power_capacity}"></label>
      </div>
      <div class="hint mt8">
        已用 <b style="color:#00e5ff">${st.used_u || 0}U</b> / ${r.u_total}U　设备 ${st.total || 0} 台<br>
        在线 ${st.online || 0}　告警 ${st.warning || 0}　离线 ${st.offline || 0}
      </div>
      ${r.id ? `<a class="btn primary mt8" style="width:100%;justify-content:center" href="racks.php?rack=${r.id}">
        <svg class="ico"><use href="#ico-ui-rack"/></svg>编辑该机柜 42U 设备</a>`
             : '<div class="hint mt8" style="color:#ffb020">新机柜需先保存布局，才能编辑 U 位设备</div>'}`;
    bindFields(box, r, () => { pushUndo(); render(); });
  }

  if (k === 'sensor') {
    const s = S.sensors[i];
    box.innerHTML = `
      <label class="fld"><span>名称</span><input type="text" data-f="name" value="${esc(s.name)}"></label>
      <label class="fld"><span>类型</span><select data-f="kind">
        ${Object.entries(META.sensors).map(([key, m]) =>
          `<option value="${key}" ${s.kind === key ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}
      </select></label>
      <div class="row2">
        <label class="fld"><span>X (cm)</span><input type="number" data-f="x" value="${s.x}"></label>
        <label class="fld"><span>Y (cm)</span><input type="number" data-f="y" value="${s.y}"></label>
      </div>
      <div class="row2">
        <label class="fld"><span>当前值</span><input type="number" step="0.1" data-f="value" value="${s.value}"></label>
        <label class="fld"><span>状态</span><select data-f="status">
          ${['normal', 'warning', 'alarm'].map(x =>
            `<option value="${x}" ${s.status === x ? 'selected' : ''}>${{ normal: '正常', warning: '预警', alarm: '报警' }[x]}</option>`).join('')}
        </select></label>
      </div>`;
    bindFields(box, s, () => {
      s.unit = (META.sensors[s.kind] || {}).unit || '';
      pushUndo(); render();
    });
  }
}

function bindFields(box, obj, after) {
  $$('[data-f]', box).forEach(inp => {
    inp.addEventListener('change', () => {
      const f = inp.dataset.f;
      obj[f] = inp.type === 'number' ? (parseFloat(inp.value) || 0) : inp.value;
      if (f === 'kind' && obj.thickness !== undefined) obj.thickness = META.walls[obj.kind].thickness;
      after();
    });
  });
}

function syncRoomInputs() {
  if (!S.room) return;
  $('#rmName').value = S.room.name;
  $('#rmW').value = S.room.width;
  $('#rmD').value = S.room.depth;
  $('#rmG').value = S.room.grid;
  $('#rmH').value = S.room.wall_height;
}
['rmName', 'rmW', 'rmD', 'rmG', 'rmH'].forEach(id => {
  $('#' + id).addEventListener('change', () => {
    pushUndo();
    S.room.name = $('#rmName').value;
    S.room.width = +$('#rmW').value || 1200;
    S.room.depth = +$('#rmD').value || 900;
    S.room.grid = Math.max(1, +$('#rmG').value || 10);
    S.room.wall_height = +$('#rmH').value || 300;
    render();
  });
});

/* ---------------- 工具切换 ---------------- */
function setTool(t) {
  S.tool = t;
  $$('#rail button[data-tool]').forEach(b => b.classList.toggle('on', b.dataset.tool === t));
  wrap.classList.toggle('pan', t === 'pan');
  wrap.classList.toggle('select', t === 'select');
  const tips = {
    select: '点击选中对象，拖动可移动；选中墙体后拖中间的 ◆ 手柄可以弯成弧墙，双击手柄拉直',
    pan: '按住拖动平移画布',
    wall: '按下并拖动画出一段墙体，按住 Shift 保持水平/垂直；画完拖中间的 ◆ 手柄即可弯成弧墙',
    glass: '按下并拖动画出玻璃隔断，画完可拖 ◆ 手柄弯成弧形',
    door: '按下并拖动画出门洞，自动生成开启方向弧线',
    window: '按下并拖动画出窗',
    pillar: '按下并拖动框出一根柱子',
    rack: '在图上单击放置一个 42U 机柜',
    sensor: '在图上单击放置一个环境传感器',
  };
  showTip(tips[t] || '');
}
let tipTimer = null;
function showTip(t) {
  if (!t) { tipEl.classList.remove('show'); return; }
  tipEl.textContent = t;
  tipEl.classList.add('show');
  clearTimeout(tipTimer);
  tipTimer = setTimeout(() => tipEl.classList.remove('show'), 3200);
}
$$('#rail button[data-tool]').forEach(b => b.onclick = () => setTool(b.dataset.tool));

/* ---------------- 鼠标交互 ---------------- */
svg.addEventListener('mousedown', ev => {
  if (!S.room) return;
  const p   = toWorld(ev.clientX, ev.clientY);
  const hit = ev.target.closest('[data-k]');

  // 平移：中键 / 空格 / 平移工具
  if (ev.button === 1 || spaceDown || S.tool === 'pan') {
    ev.preventDefault();
    drag = { mode: 'pan', sx: ev.clientX, sy: ev.clientY, vx: V.x, vy: V.y };
    return;
  }
  if (ev.button !== 0) return;

  // 放置机柜 / 传感器
  if (S.tool === 'rack') {
    pushUndo();
    const n = S.racks.length + 1;
    S.racks.push({
      tmp_id: 'T' + (++tmpSeq), name: 'R' + String(n).padStart(2, '0'), label: '', row_label: '',
      x: snap(p.x), y: snap(p.y), width: 60, depth: 110, rotation: 0,
      u_total: 42, power_capacity: 6000, stats: { used_u: 0, total: 0 },
    });
    S.sel = { k: 'rack', i: S.racks.length - 1 };
    setTool('select'); render();
    return;
  }
  if (S.tool === 'sensor') {
    pushUndo();
    S.sensors.push({
      name: '传感器' + (S.sensors.length + 1), kind: 'temp',
      x: snap(p.x), y: snap(p.y), value: 24, unit: '℃', status: 'normal',
    });
    S.sel = { k: 'sensor', i: S.sensors.length - 1 };
    setTool('select'); render();
    return;
  }

  // 画墙体类
  if (WALLK.includes(S.tool)) {
    drag = { mode: 'draw', kind: S.tool, x1: snap(p.x), y1: snap(p.y), x2: snap(p.x), y2: snap(p.y) };
    return;
  }

  // 选择 / 移动
  if (hit) {
    const k = hit.dataset.k, i = +hit.dataset.i;
    // 拖拽类操作先留一份快照，等 mouseup 确认真的动过了再压进撤销栈，
    // 否则每次单纯的点选也会占掉一步撤销
    if (k === 'wp') {                       // 墙端点手柄
      drag = { mode: 'wpt', i, e: +hit.dataset.e, before: snapshot() };
      return;
    }
    if (k === 'wc') {                       // 弧度手柄
      drag = { mode: 'bend', i, before: snapshot() };
      return;
    }
    S.sel = { k, i };
    const o = k === 'wall' ? S.walls[i] : k === 'rack' ? S.racks[i] : S.sensors[i];
    drag = {
      mode: 'move', k, i, moved: false, before: snapshot(),
      ox: p.x, oy: p.y,
      snapshot: k === 'wall' ? { x1: o.x1, y1: o.y1, x2: o.x2, y2: o.y2 } : { x: o.x, y: o.y },
    };
    render();
  } else {
    S.sel = null; render();
  }
});

window.addEventListener('mousemove', ev => {
  if (!S.room) return;
  const p = toWorld(ev.clientX, ev.clientY);
  $('#hudXY').textContent = Math.round(p.x) + ', ' + Math.round(p.y);
  if (!drag) return;

  if (drag.mode === 'pan') {
    const r = wrap.getBoundingClientRect();
    const k = V.w / r.width;
    V.x = drag.vx - (ev.clientX - drag.sx) * k;
    V.y = drag.vy - (ev.clientY - drag.sy) * k;
    applyView();
    return;
  }

  if (drag.mode === 'draw') {
    let x2 = snap(p.x), y2 = snap(p.y);
    if (ev.shiftKey && drag.kind !== 'pillar') {
      if (Math.abs(x2 - drag.x1) > Math.abs(y2 - drag.y1)) y2 = drag.y1; else x2 = drag.x1;
    }
    drag.x2 = x2; drag.y2 = y2;
    const meta = META.walls[drag.kind];
    const len  = Math.round(Math.hypot(x2 - drag.x1, y2 - drag.y1));
    drag.preview = drag.kind === 'pillar'
      ? `<rect x="${Math.min(drag.x1, x2)}" y="${Math.min(drag.y1, y2)}"
           width="${Math.abs(x2 - drag.x1)}" height="${Math.abs(y2 - drag.y1)}"
           fill="rgba(100,116,139,.5)" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke"/>`
      : `<line x1="${drag.x1}" y1="${drag.y1}" x2="${x2}" y2="${y2}" stroke="${meta.color}"
           stroke-width="${meta.thickness}" stroke-linecap="round" opacity=".75"/>
         <text x="${(drag.x1 + x2) / 2}" y="${(drag.y1 + y2) / 2 - 16}" fill="#fff" font-size="19"
           text-anchor="middle">${len} cm</text>`;
    render();
    return;
  }

  if (drag.mode === 'wpt') {
    const w = S.walls[drag.i];
    w['x' + drag.e] = snap(p.x);
    w['y' + drag.e] = snap(p.y);
    drag.moved = true;
    render();
    return;
  }

  if (drag.mode === 'bend') {
    // 弓高 = 鼠标到弦的有向垂距，沿法线投影得到，带符号所以能往两侧弯
    const w = S.walls[drag.i], g = wallGeom(w);
    const mx = (g.x1 + g.x2) / 2, my = (g.y1 + g.y2) / 2;
    let c = (p.x - mx) * g.nx + (p.y - my) * g.ny;
    if (ev.shiftKey) c = 0;                                  // 按住 Shift 拉直
    w.curve = clamp(snap(c), -g.len * 1.2, g.len * 1.2);     // 超过这个比例弧线会自己打结
    drag.moved = true;
    render();
    return;
  }

  if (drag.mode === 'move') {
    const dx = snap(p.x - drag.ox + (drag.k === 'wall' ? drag.snapshot.x1 : drag.snapshot.x))
             - (drag.k === 'wall' ? drag.snapshot.x1 : drag.snapshot.x);
    const dy = snap(p.y - drag.oy + (drag.k === 'wall' ? drag.snapshot.y1 : drag.snapshot.y))
             - (drag.k === 'wall' ? drag.snapshot.y1 : drag.snapshot.y);
    if (!dx && !dy && !drag.moved) return;
    drag.moved = true;
    if (drag.k === 'wall') {
      const w = S.walls[drag.i], s = drag.snapshot;
      w.x1 = s.x1 + dx; w.y1 = s.y1 + dy; w.x2 = s.x2 + dx; w.y2 = s.y2 + dy;
    } else {
      const o = (drag.k === 'rack' ? S.racks : S.sensors)[drag.i];
      o.x = drag.snapshot.x + dx; o.y = drag.snapshot.y + dy;
    }
    render();
  }
});

window.addEventListener('mouseup', () => {
  if (!drag) return;
  if (drag.mode === 'draw') {
    const len = Math.hypot(drag.x2 - drag.x1, drag.y2 - drag.y1);
    if (len >= 10) {
      pushUndo();
      S.walls.push({
        kind: drag.kind, x1: drag.x1, y1: drag.y1, x2: drag.x2, y2: drag.y2,
        thickness: META.walls[drag.kind].thickness, curve: 0,
      });
      S.sel = { k: 'wall', i: S.walls.length - 1 };
    }
    drag = null; render(); return;
  }
  if (['move', 'wpt', 'bend'].includes(drag.mode) && drag.moved) { pushUndo(drag.before); }
  drag = null;
  render();
});

svg.addEventListener('dblclick', ev => {
  const bend = ev.target.closest('[data-k="wc"]');
  if (bend) {
    const w = S.walls[+bend.dataset.i];
    if (w && w.curve) { pushUndo(); w.curve = 0; render(); showTip('已拉直'); }
    return;
  }
  const hit = ev.target.closest('[data-k="rack"]');
  if (!hit) return;
  const r = S.racks[+hit.dataset.i];
  if (r && r.id) location.href = 'racks.php?rack=' + r.id;
  else toast('新机柜请先保存布局', 'err');
});

wrap.addEventListener('wheel', ev => {
  ev.preventDefault();
  zoomAt(ev.deltaY > 0 ? 1.12 : 1 / 1.12, ev.clientX, ev.clientY);
}, { passive: false });

svg.addEventListener('contextmenu', e => e.preventDefault());

/* ---------------- 键盘 ---------------- */
let spaceDown = false;
window.addEventListener('keydown', ev => {
  if (/^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName)) return;
  if (ev.code === 'Space') { spaceDown = true; wrap.classList.add('pan'); ev.preventDefault(); return; }
  if (ev.ctrlKey && ev.key.toLowerCase() === 'z') { ev.preventDefault(); undo(); return; }
  if (ev.ctrlKey && ev.key.toLowerCase() === 'y') { ev.preventDefault(); redo(); return; }
  if (ev.ctrlKey && ev.key.toLowerCase() === 's') { ev.preventDefault(); save(); return; }
  if (ev.key === 'Delete' || ev.key === 'Backspace') { ev.preventDefault(); delSel(); return; }
  if (ev.key === 'Escape') { S.sel = null; setTool('select'); render(); return; }
  const map = { v: 'select', w: 'wall', g: 'glass', d: 'door', n: 'window', p: 'pillar', r: 'rack', s: 'sensor' };
  if (map[ev.key.toLowerCase()]) setTool(map[ev.key.toLowerCase()]);
});
window.addEventListener('keyup', ev => {
  if (ev.code === 'Space') { spaceDown = false; wrap.classList.toggle('pan', S.tool === 'pan'); }
});

function delSel() {
  if (!S.sel) return;
  pushUndo();
  const { k, i } = S.sel;
  if (k === 'wall') S.walls.splice(i, 1);
  else if (k === 'rack') S.racks.splice(i, 1);
  else S.sensors.splice(i, 1);
  S.sel = null; render();
}

/* ---------------- 按钮 ---------------- */
$('#btnGrid').onclick = e => { S.snap = !S.snap; e.currentTarget.classList.toggle('on', S.snap); };
$('#btnUndo').onclick = undo;
$('#btnRedo').onclick = redo;
$('#btnDel').onclick  = delSel;
$('#btnSave').onclick = save;
$('#zIn').onclick  = () => { const r = wrap.getBoundingClientRect(); zoomAt(1 / 1.25, r.left + r.width / 2, r.top + r.height / 2); };
$('#zOut').onclick = () => { const r = wrap.getBoundingClientRect(); zoomAt(1.25, r.left + r.width / 2, r.top + r.height / 2); };
$('#zFit').onclick = fitView;
window.addEventListener('resize', applyView);
window.addEventListener('beforeunload', e => { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });

/* ---------------- 读写 ---------------- */
async function load() {
  const d = await api('layout.get');
  S.room = {
    id: +d.room.id, name: d.room.name, code: d.room.code,
    width: +d.room.width, depth: +d.room.depth,
    grid: +d.room.grid || 10, wall_height: +d.room.wall_height,
  };
  S.walls = d.walls.map(w => ({
    id: +w.id, kind: w.kind, x1: +w.x1, y1: +w.y1, x2: +w.x2, y2: +w.y2,
    thickness: +w.thickness, curve: +w.curve || 0,
  }));
  S.racks = d.racks.map(r => ({
    id: +r.id, name: r.name, label: r.label || '', row_label: r.row_label || '',
    x: +r.x, y: +r.y, width: +r.width, depth: +r.depth, rotation: +r.rotation,
    u_total: +r.u_total, power_capacity: +r.power_capacity, stats: r.stats,
  }));
  S.sensors = d.sensors.map(s => ({
    id: +s.id, name: s.name, kind: s.kind, x: +s.x, y: +s.y,
    value: +s.value, unit: s.unit, status: s.status,
  }));
  S.sel = null; S.dirty = false;
  syncRoomInputs(); fitView(); render();
}

async function save() {
  try {
    await api('layout.save', {
      method: 'POST',
      body: {
        room_id: S.room.id,
        room: {
          name: S.room.name, width: S.room.width, depth: S.room.depth,
          grid: S.room.grid, wall_height: S.room.wall_height,
        },
        walls: S.walls, racks: S.racks, sensors: S.sensors,
      },
    });
    S.dirty = false;
    toast('布局已保存', 'ok');
    const keep = S.sel;
    await load();
    if (keep && keep.k === 'rack' && S.racks[keep.i]) { S.sel = keep; render(); }
  } catch (err) {
    toast('保存失败：' + err.message, 'err');
  }
}

load().catch(err => toast('加载失败：' + err.message, 'err'));
})();
