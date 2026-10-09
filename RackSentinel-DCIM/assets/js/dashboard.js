/* =========================================================================
   运维大屏：数据拉取、面板渲染、实时指标轮询
   ========================================================================= */
(() => {
const D = { sum: null, devices: [], metrics: new Map(), trend: { cpu: [], temp: [], pw: [] },
            mode: '3d', pick: 0 };

/* ---------------- KPI ---------------- */
function renderKpis() {
  const s = D.sum.by_status || {};
  const total = Object.values(s).reduce((a, b) => a + b, 0);
  const cap = D.sum.capacity || { used_u: 0, total_u: 1 };
  const items = [
    { n: '在册设备', v: total, s: `${D.sum.racks.length} 个机柜`, c: '#00e5ff', p: 100, ico: 'server' },
    { n: '在线运行', v: s.online || 0, s: total ? Math.round((s.online || 0) / total * 100) + '% 可用率' : '—',
      c: '#2ef2a3', p: total ? (s.online || 0) / total * 100 : 0, ico: 'ui-power' },
    { n: '告警 / 维护', v: (s.warning || 0) + (s.maintenance || 0), s: `告警 ${s.warning || 0} · 维护 ${s.maintenance || 0}`,
      c: '#ffb020', p: total ? ((s.warning || 0) + (s.maintenance || 0)) / total * 100 : 0, ico: 'ui-alert' },
    { n: '离线设备', v: s.offline || 0, s: `U 位占用 ${Math.round(cap.used_u / Math.max(1, cap.total_u) * 100)}%`,
      c: '#ff4d6a', p: total ? (s.offline || 0) / total * 100 : 0, ico: 'ui-close' },
  ];
  $('#kpis').innerHTML = items.map(i => `
    <div class="kpi">
      <div class="k-n"><span style="color:${i.c};display:inline-flex">${icoSvg(i.ico, 'ico-sm')}</span>${i.n}</div>
      <div class="k-v num" style="color:${i.c}">${i.v}<i>台</i></div>
      <div class="k-s">${esc(i.s)}</div>
      <div class="k-bar" style="width:${i.p}%;background:${i.c}"></div>
    </div>`).join('');
}

/* ---------------- 概览 / 容量环 ---------------- */
function renderRoom() {
  const r = D.sum.room, cap = D.sum.capacity;
  $('#roomInfo').innerHTML = `
    <b style="color:#eafcff;font-size:14px">${esc(r.name)}</b>　<span class="num">${esc(r.code)}</span><br>
    面积 ${(r.width * r.depth / 10000).toFixed(1)} ㎡（${r.width}×${r.depth} cm）　层高 ${r.wall_height} cm`;

  const used = cap.used_u, tot = Math.max(1, cap.total_u);
  donut($('#capRing'), used / tot, '#00e5ff');
  $('#capText').innerHTML = `
    U 位总量 <b class="num" style="color:#eafcff">${tot}U</b><br>
    已安装 <b class="num" style="color:#00e5ff">${used}U</b>　空余 <b class="num" style="color:#2ef2a3">${tot - used}U</b><br>
    实时功率 <b class="num" style="color:#ffb020">${fmtPower(D.sum.power_w || 0)}</b><br>
    机柜数量 <b class="num" style="color:#eafcff">${D.sum.racks.length}</b> 个`;
}

function donut(cv, ratio, color) {
  const dpr = Math.min(devicePixelRatio || 1, 2), s = cv.clientWidth || 94;
  cv.width = s * dpr; cv.height = s * dpr;
  const x = cv.getContext('2d');
  x.setTransform(dpr, 0, 0, dpr, 0, 0);
  x.clearRect(0, 0, s, s);
  const cx = s / 2, cy = s / 2, r = s / 2 - 8;
  x.lineWidth = 9; x.lineCap = 'round';
  x.strokeStyle = 'rgba(0,229,255,.12)';
  x.beginPath(); x.arc(cx, cy, r, 0, Math.PI * 2); x.stroke();
  x.strokeStyle = color; x.shadowColor = color; x.shadowBlur = 12;
  x.beginPath(); x.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * ratio); x.stroke();
  x.shadowBlur = 0;
  x.fillStyle = '#eafcff'; x.font = '600 21px Bahnschrift, sans-serif'; x.textAlign = 'center';
  x.fillText(Math.round(ratio * 100) + '%', cx, cy + 3);
  x.fillStyle = '#5d8298'; x.font = '9px sans-serif';
  x.fillText('空间使用率', cx, cy + 18);
}

/* ---------------- 分布条 ---------------- */
/**
 * 分布条通用渲染：按数量倒序，装不下的尾部合并成一条「其他」，
 * 这样大屏上没有滚动条，合计数也不会对不上。
 */
function renderDist(sel, enSel, obj, metaOf, label) {
  const rows = Object.entries(obj || {}).sort((a, b) => b[1] - a[1]);
  const max = Math.max(1, ...rows.map(x => x[1]));
  const bar = (color, icon, name, v) => `<div class="b">
    <span style="color:${color};display:inline-flex">${icoSvg(icon, 'ico-sm')}</span>
    <span class="nm">${esc(name)}</span>
    <span class="tk"><i style="width:${v / max * 100}%;background:${color}"></i></span>
    <span class="vv">${v}</span></div>`;

  const box = $(sel);
  box.innerHTML = rows.map(([k, v]) => {
    const m = metaOf(k);
    return bar(m.color, m.icon, m.name, v);
  }).join('') || '<div class="empty">暂无数据</div>';

  // 先裁到装得下，再腾出一行放「其他」
  let cut = clipToFit(box, 2);
  if (cut) {
    box.lastElementChild.remove();
    cut++;
    const rest = rows.slice(rows.length - cut);
    const sum = rest.reduce((a, b) => a + b[1], 0);
    box.insertAdjacentHTML('beforeend', bar('#5a7a8e', 'ui-more', `其他 ${cut} 类`, sum));
  }
  $(enSel).textContent = cut ? `显示 ${rows.length - cut}/${rows.length}` : label;
}

function renderBars() {
  renderDist('#byType', '#byTypeEn', D.sum.by_type,
    k => META.types[k] || { name: k, color: '#8fa6b8', icon: 'server' }, 'BY TYPE');
  renderDist('#byOs', '#byOsEn', D.sum.by_os,
    k => META.os[k] || META.os.none, 'BY OS');
}

/** 裁掉装不下的条目，并在卡片右上角标明「显示 m/n」 */
function tagFit(listSel, enSel, total, label) {
  const cut = clipToFit($(listSel));
  const en = $(enSel);
  if (en) en.textContent = cut ? `显示 ${total - cut}/${total}` : label;
}

/** 烟感、漏水这类开关量传感器没有读数，直接显示状态文字 */
function sensorText(s) {
  const unit = (META.sensors[s.kind] || {}).unit || '';
  if (unit) return num(s.value) + unit;
  return { normal: '正常', warning: '预警', alarm: '报警' }[s.status] || s.status;
}

function renderSensors() {
  const list = D.sum.sensors || [];
  $('#sensors').innerHTML = list.map(s => {
    const m = META.sensors[s.kind] || META.sensors.temp;
    const col = s.status === 'alarm' ? '#ff4d6a' : s.status === 'warning' ? '#ffb020' : '#2ef2a3';
    const pct = s.kind === 'temp' ? clamp((s.value - 15) / 25 * 100, 0, 100)
              : s.kind === 'humidity' ? clamp(s.value, 0, 100)
              : s.status === 'normal' ? 100 : 30;
    return `<div class="b">
      <span style="color:${col};display:inline-flex">${icoSvg(m.icon, 'ico-sm')}</span>
      <span class="nm">${esc(s.name)}</span>
      <span class="tk"><i style="width:${pct}%;background:${col}"></i></span>
      <span class="vv">${esc(sensorText(s))}</span></div>`;
  }).join('') || '<div class="empty">未部署传感器</div>';
  tagFit('#sensors', '#sensorsEn', list.length, 'SENSORS');
}

/* ---------------- 告警 ---------------- */
function renderAlerts() {
  const all = D.sum.alerts || [];
  const active = all.filter(a => +a.acked === 0).length;
  // 大屏只滚动最新几条，历史全量在告警列表页看
  const list = all.slice(0, 8);
  $('#alerts').innerHTML = list.map(a => {
    const col = a.level === 'critical' ? '#ff4d6a' : a.level === 'warning' ? '#ffb020' : '#00e5ff';
    return `<div class="alert ${esc(a.level)}" data-id="${a.id}" style="${+a.acked ? 'opacity:.45' : ''}">
      <i style="background:${col}"></i>
      <div><div class="a-t">${esc(a.title)}</div>
        <div class="a-s">${esc(a.device_name || a.rack_name || '机房')} · ${esc(a.detail || '')}</div></div>
      <div class="a-x">${esc(timeAgo(a.created_at))}${+a.acked ? '<br>已确认' : ''}</div>
    </div>`;
  }).join('') || '<div class="empty">当前无告警</div>';

  const shown = list.length - clipToFit($('#alerts'));
  $('#alertCount').textContent = `最近 ${shown} 条 · ${active} 待处理`;

  $$('#alerts .alert').forEach(n => n.onclick = async () => {
    try { await api('alert.ack', { query: { id: n.dataset.id } }); toast('告警已确认', 'ok'); await refresh(); }
    catch (e) { toast(e.message, 'err'); }
  });
}

/* ---------------- 机柜 / 热点设备 ---------------- */
function renderRackTop() {
  const racks = [...D.sum.racks].sort((a, b) => (b.stats.used_u || 0) - (a.stats.used_u || 0));
  $('#rackTop').innerHTML = racks.map(r => {
    const pct = Math.round((r.stats.used_u || 0) / (r.u_total || 42) * 100);
    const col = pct > 88 ? '#ff4d6a' : pct > 65 ? '#ffb020' : '#2ef2a3';
    return `<div class="b" style="cursor:pointer" data-rack="${r.id}">
      <span style="color:${col};display:inline-flex">${icoSvg('ui-rack', 'ico-sm')}</span>
      <span class="nm">${esc(r.name)}</span>
      <span class="tk"><i style="width:${pct}%;background:${col}"></i></span>
      <span class="vv">${pct}%</span></div>`;
  }).join('');
  tagFit('#rackTop', '#rackTopEn', racks.length, 'RACK LOAD');
  $$('#rackTop [data-rack]').forEach(n => n.onclick = () => showPick(+n.dataset.rack));
}

function renderHot() {
  const list = (D.sum.top_load || []).map(d => {
    const live = D.metrics.get(+d.id);
    return { ...d, cpu: num(live ? live.cpu : d.cpu), temp: num(live ? live.temp : d.temp) };
  }).sort((a, b) => b.cpu - a.cpu);
  $('#hotDev').innerHTML = list.map(d => {
    const col = d.cpu > 85 ? '#ff4d6a' : d.cpu > 65 ? '#ffb020' : '#2ef2a3';
    return `<div class="toprow" data-id="${d.id}">
      ${typeBadge(d.type_key)}
      <span class="nm">${esc(d.name)}<small>${esc(d.rack_name)}</small></span>
      <span class="vv" style="color:${col}">${d.cpu}%</span></div>`;
  }).join('') || '<div class="empty">暂无数据</div>';
  tagFit('#hotDev', '#hotDevEn', list.length, 'HOT DEVICES');
}

/* ---------------- 选中机柜（场景浮层） ---------------- */
function hidePick() {
  D.pick = 0;
  $('#pickCard').hidden = true;
  hideDev();
  markPick();
}

/** 同步场景里的选中标记：3D 用脚下光环，2D 用加粗描边 */
function markPick() {
  if (window.Scene3D && Scene3D.isReady()) Scene3D.setSelected(D.pick);
  $$('#view2d [data-rack]').forEach(n => n.classList.toggle('sel', +n.dataset.rack === D.pick));
}

function showPick(rackId) {
  const r = D.sum.racks.find(x => +x.id === +rackId);
  if (!r) return hidePick();
  D.pick = +r.id;
  const st = r.stats;
  const devs = D.devices.filter(d => +d.rack_id === +r.id && d.type_key !== 'blank')
    .sort((a, b) => b.u_start - a.u_start);

  $('#pickCard').hidden = false;
  $('#pickName').textContent = r.name;
  $('#pickLabel').textContent = r.label || '';
  $('#pickSum').innerHTML = `
    已用 <b style="color:#00e5ff">${st.used_u}/${r.u_total}U</b>　设备 ${st.total} 台　
    在线 <b style="color:#2ef2a3">${st.online}</b> 告警 <b style="color:#ffb020">${st.warning}</b>
    离线 <b style="color:#ff4d6a">${st.offline}</b><br>
    功率 ${fmtPower(st.power)} / ${fmtPower(r.power_capacity)}`;
  $('#pickBox').innerHTML = devs.map(d => {
    const live = D.metrics.get(+d.id);
    const cpu = live ? live.cpu : null;
    return `<div class="toprow" data-dev="${d.id}">
      ${typeBadge(d.type_key)}
      <span class="nm">${esc(d.name)}<small>U${d.u_start} · ${esc(d.ip || '')}</small></span>
      <span class="vv" style="color:${statusColor(d.status)}">${cpu !== null ? cpu + '%' : statusName(d.status)}</span>
    </div>`;
  }).join('') || '<div class="empty">该机柜暂无设备</div>';
  $$('#pickBox [data-dev]').forEach(n => n.onclick = () => showDev(+n.dataset.dev, n));
  markPick();
}
$('#pickClose').onclick = hidePick;

/* ---------------- 设备详情浮卡 ---------------- */
function hideDev() { $('#devPop').hidden = true; }

function showDev(id, row) {
  const d = D.devices.find(x => +x.id === id);
  if (!d) return;
  const t = META.types[d.type_key] || { name: d.type_key, color: '#8fa6b8', icon: 'server' };
  const o = META.os[d.os_key] || META.os.none;
  const m = D.metrics.get(id);
  const uEnd = +d.u_start + +d.u_size - 1;
  const kv = [
    ['主机名', d.hostname, 'mono'],
    ['业务 IP', d.ip, 'mono'],
    ['管理 IP', d.mgmt_ip, 'mono'],
    ['系统', o.name],
    ['U 位', `U${d.u_start}${+d.u_size > 1 ? '–U' + uEnd : ''}（${d.u_size}U）`],
    ['厂商型号', [d.vendor, d.model].filter(Boolean).join(' ')],
    ['序列号', d.serial, 'mono'],
    ['资产号', d.asset_tag, 'mono'],
    ['负责人', d.owner],
    ['额定功耗', d.power_w ? d.power_w + ' W' : ''],
  ].filter(x => x[1]);

  // 无监控的设备（PDU / 配线架等）不显示空指标区
  const live = m && +m.cpu > 0 ? `
    <div class="dp-live">
      <div><b>${num(m.cpu)}%</b><span>CPU</span></div>
      <div><b>${num(m.mem)}%</b><span>内存</span></div>
      <div><b>${num(m.disk)}%</b><span>磁盘</span></div>
      <div><b>${num(m.temp)}℃</b><span>温度</span></div>
    </div>` : '';

  $('#devPopIn').innerHTML = `
    <div class="dp-head">
      <span style="color:${t.color};display:inline-flex">${icoSvg(t.icon, 'ico-lg')}</span>
      <div style="min-width:0">
        <div class="nm">${esc(d.name)}</div>
        <div class="sub">${esc(t.name)} · ${esc(d.rack_name || '')}</div>
      </div>
      <span class="tag" style="margin-left:auto;color:${statusColor(d.status)};
        border-color:${statusColor(d.status)}44">${statusName(d.status)}</span>
    </div>
    <dl class="dp-kv">
      ${kv.map(([k, v, c]) => `<dt>${k}</dt><dd class="${c || ''}">${esc(v)}</dd>`).join('')}
    </dl>${live}`;

  const pop = $('#devPop');
  pop.hidden = false;
  // 与被点击的行对齐，再夹回卡片内，避免贴边时溢出场景
  const host = $('#pickCard').getBoundingClientRect();
  const top = row.getBoundingClientRect().top - host.top;
  pop.style.top = clamp(top, 0, Math.max(0, host.height - pop.offsetHeight)) + 'px';
}
$('#pickCard').addEventListener('mouseleave', hideDev);

/* ---------------- 2D 平面图 ---------------- */
function render2d() {
  const svg = $('#view2d');
  const r = D.sum.room;
  const pad = 90;
  const box = $('#stage').getBoundingClientRect();
  // 让整间机房完整居中：按容器宽高比撑开 viewBox，而不是只按宽度推算
  const needW = +r.width + pad * 2, needH = +r.depth + pad * 2;
  const aspect = box.width / Math.max(1, box.height);
  const vw = needW / needH > aspect ? needW : needH * aspect;
  const vh = vw / aspect;
  svg.setAttribute('viewBox', `${(+r.width - vw) / 2} ${(+r.depth - vh) / 2} ${vw} ${vh}`);

  const parts = [`<rect x="0" y="0" width="${r.width}" height="${r.depth}"
      fill="rgba(0,70,110,.14)" stroke="rgba(0,229,255,.35)" stroke-width="2" vector-effect="non-scaling-stroke"/>`];

  (D.sum.walls || []).forEach(w => {
    const meta = META.walls[w.kind] || META.walls.wall;
    if (w.kind === 'pillar') {
      parts.push(`<rect x="${Math.min(+w.x1, +w.x2)}" y="${Math.min(+w.y1, +w.y2)}"
        width="${Math.abs(w.x2 - w.x1)}" height="${Math.abs(w.y2 - w.y1)}" fill="#3c4c5c"/>`);
    } else {
      parts.push(`<path d="${wallPath(w)}" fill="none" stroke="${meta.color}"
        stroke-width="${w.thickness}" stroke-linecap="round" opacity="${w.kind === 'glass' ? .45 : .95}"/>`);
    }
  });

  D.sum.racks.forEach(rk => {
    const st = rk.stats;
    const col = rackHealth(rk);
    const w = +rk.width, d = +rk.depth;
    const pct = Math.round((st.used_u || 0) / (rk.u_total || 42) * 100);
    parts.push(`<g transform="translate(${rk.x},${rk.y}) rotate(${rk.rotation})"
        data-rack="${rk.id}" style="cursor:pointer">
      <rect x="${-w / 2}" y="${-d / 2}" width="${w}" height="${d}" rx="3"
        fill="rgba(10,30,46,.9)" stroke="${col}" stroke-width="2.4" vector-effect="non-scaling-stroke"/>
      <rect x="${-w / 2 + 4}" y="${d / 2 - 14}" width="${(w - 8) * pct / 100}" height="7" fill="${col}"/>
      <rect x="${-w / 2}" y="${-d / 2}" width="${w}" height="8" fill="${col}" opacity=".9"/>
      <g transform="rotate(${-rk.rotation})">
        <text y="0" fill="#eafcff" font-size="19" font-weight="600" text-anchor="middle">${esc(rk.name)}</text>
        <text y="19" fill="#9fc8da" font-size="14" text-anchor="middle">${st.used_u}/${rk.u_total}U</text>
      </g></g>`);
  });

  (D.sum.sensors || []).forEach(s => {
    const col = s.status === 'alarm' ? '#ff4d6a' : s.status === 'warning' ? '#ffb020' : '#2ef2a3';
    parts.push(`<g transform="translate(${s.x},${s.y})">
      <circle r="15" fill="rgba(4,22,36,.9)" stroke="${col}" stroke-width="2" vector-effect="non-scaling-stroke"/>
      <circle r="6" fill="${col}"/>
      <text y="-22" fill="${col}" font-size="15" text-anchor="middle">${esc(sensorText(s))}</text>
    </g>`);
  });

  svg.innerHTML = parts.join('');
  $$('#view2d [data-rack]').forEach(n => {
    n.onclick = () => showPick(+n.dataset.rack);
    n.ondblclick = () => location.href = 'racks.php?rack=' + n.dataset.rack;
  });
  markPick();
}

/* ---------------- 趋势 ---------------- */
function pushTrend(metrics) {
  const live = metrics.filter(m => m.cpu > 0);
  const avg = k => live.length ? live.reduce((a, b) => a + b[k], 0) / live.length : 0;
  const pw = metrics.reduce((a, b) => a + b.power, 0);
  const t = D.trend;
  t.cpu.push(+avg('cpu').toFixed(1));
  t.temp.push(+avg('temp').toFixed(1));
  t.pw.push(+(pw / 1000).toFixed(1));
  ['cpu', 'temp', 'pw'].forEach(k => { if (t[k].length > 40) t[k].shift(); });

  // 功率按机房额定容量折算成百分比，与 CPU / 温度共用 0-100 刻度
  const capKw = Math.max(1, D.sum.racks.reduce((a, b) => a + (+b.power_capacity || 0), 0) / 1000);
  sparkline($('#trend'),
    [t.cpu, t.temp, t.pw.map(v => v / capKw * 100)],
    ['#00e5ff', '#ffb020', '#2ef2a3'], { min: 0, max: 100, fill: 0 });
  $('#trendLegend').innerHTML =
    `<span style="color:#00e5ff">CPU ${t.cpu.at(-1)}%</span> · ` +
    `<span style="color:#ffb020">温度 ${t.temp.at(-1)}℃</span> · ` +
    `<span style="color:#2ef2a3">功率 ${t.pw.at(-1)} kW</span>`;
}

/* ---------------- 轮询 ---------------- */
async function tick() {
  try {
    const d = await api('metrics.tick');
    d.metrics.forEach(m => D.metrics.set(+m.id, m));
    pushTrend(d.metrics);
    renderHot();
    if (window.Scene3D && Scene3D.isReady()) Scene3D.updateMetrics(d.metrics);
  } catch { /* 网络抖动时跳过本轮 */ }
}

async function refresh() {
  D.sum = await api('dashboard.summary');
  renderKpis(); renderRoom(); renderBars(); renderSensors();
  renderAlerts(); renderRackTop(); renderHot();
  if (D.pick) showPick(D.pick);
  if (D.mode === '2d') render2d();
}

/* ---------------- 视图切换 ---------------- */
function setMode(m) {
  D.mode = m;
  $('#tab3d').classList.toggle('on', m === '3d');
  $('#tab2d').classList.toggle('on', m === '2d');
  $('#view2d').style.display = m === '2d' ? '' : 'none';
  $('#view3d').style.display = m === '3d' ? '' : 'none';
  if (window.Scene3D) Scene3D.setVisible(m === '3d');
  $('#labels').style.display = m === '3d' ? '' : 'none';
  if (m === '2d') render2d();
}
$('#tab3d').onclick = () => setMode('3d');
$('#tab2d').onclick = () => setMode('2d');
$('#btnReset').onclick = () => { if (Scene3D.isReady()) Scene3D.reset(); else render2d(); };
$('#btnAuto').onclick = e => {
  if (!Scene3D.isReady()) return;
  const on = !Scene3D.isAuto();
  Scene3D.setAuto(on);
  e.currentTarget.classList.toggle('on', on);
};
// 分辨率变化后可容纳的条目数会变，整体重渲染一次让 clipToFit 重新计算
let rzT = 0;
window.addEventListener('resize', () => {
  clearTimeout(rzT);
  rzT = setTimeout(() => {
    if (!D.sum) return;
    renderBars(); renderSensors(); renderAlerts(); renderRackTop(); renderHot();
    if (D.mode === '2d') render2d();
  }, 180);
});

/* ---------------- 启动 ---------------- */
(async () => {
  try {
    await refresh();
    D.devices = await api('device.list', { query: { limit: 2000 } });

    const ok = await Scene3D.init($('#view3d'), $('#labels'), {
      room: D.sum.room, walls: D.sum.walls, racks: D.sum.racks,
      sensors: D.sum.sensors, devices: D.devices,
    });
    if (ok) {
      Scene3D.onPick(id => id ? showPick(id) : hidePick());
      $('#loading').remove();
    } else {
      $('#loading').innerHTML = '三维引擎加载失败（需联网获取 Three.js），已切换到平面图模式';
      setMode('2d');
    }

    await tick();
    setInterval(tick, POLL_MS);
    setInterval(() => refresh().catch(() => {}), POLL_MS * 6);
  } catch (err) {
    toast('加载失败：' + err.message, 'err');
    const l = $('#loading');
    if (l) l.textContent = '加载失败：' + err.message;
  }
})();
})();
