/* =========================================================================
   公共工具：API 封装、图标、提示、模态框、小图表
   ========================================================================= */
const API = 'api/index.php?r=';

async function api(route, { method = 'GET', body = null, query = {} } = {}) {
  const qs = Object.entries(query)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `&${k}=${encodeURIComponent(v)}`).join('');
  const res = await fetch(API + route + qs, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : null,
  });
  let data;
  try { data = await res.json(); }
  catch { throw new Error('服务器返回了非 JSON 内容（HTTP ' + res.status + '）'); }
  // 会话过期后整页都会一直报错，不如直接把人送回登录页，回来还停在原处
  if (res.status === 401 && data.need_login) {
    location.href = 'login.php?next=' + encodeURIComponent(location.pathname + location.search);
    throw new Error(data.error);
  }
  if (!res.ok || data.error) throw new Error(data.error || ('请求失败 HTTP ' + res.status));
  return data;
}

/* ---------- 提示 ---------- */
let _toastTimer = null;
function toast(msg, kind = '') {
  let el = document.getElementById('toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    document.body.appendChild(el);
  }
  el.className = 'show ' + kind;
  el.innerHTML = `<svg class="ico"><use href="#ico-${kind === 'err' ? 'ui-alert' : 'ui-save'}"/></svg><span></span>`;
  el.querySelector('span').textContent = msg;
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
}

/* ---------- DOM 助手 ---------- */
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
function el(tag, attrs = {}, children = []) {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') n.className = v;
    else if (k === 'html') n.innerHTML = v;
    else if (k === 'text') n.textContent = v;
    else if (k.startsWith('on') && typeof v === 'function') n.addEventListener(k.slice(2), v);
    else if (v !== null && v !== undefined) n.setAttribute(k, v);
  }
  (Array.isArray(children) ? children : [children]).forEach(c => c && n.appendChild(c));
  return n;
}
const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ---------- 图标 ---------- */
function icoSvg(id, cls = 'ico') {
  return `<svg class="${cls}"><use href="#ico-${id}"/></svg>`;
}
/** 设备类型图标牌（带类型色） */
function typeBadge(typeKey, size = '') {
  const t = META.types[typeKey] || { icon: 'server', color: '#8fa6b8', name: typeKey };
  return `<span class="ibadge ${size}" style="color:${t.color}" title="${esc(t.name)}">${icoSvg(t.icon)}</span>`;
}
/** 操作系统图标（带品牌色） */
function osIcon(osKey, cls = 'ico') {
  if (!osKey || osKey === 'none') return '';
  const o = META.os[osKey];
  if (!o) return '';
  return `<span style="color:${o.color};display:inline-flex" title="${esc(o.name)}">${icoSvg(o.icon, cls)}</span>`;
}
/** 机柜健康色：零星离线算预警，成片离线才标红 */
function rackHealth(r) {
  const st = r.stats || {};
  const bad = +st.offline || 0, tot = Math.max(1, +st.total || 0);
  if (bad >= 2 || bad / tot > .15) return '#ff4d6a';
  if (bad || +st.warning) return '#ffb020';
  return +st.used_u ? '#2ef2a3' : '#5a7a8e';
}
function statusColor(s) { return (META.statuses[s] || {}).color || '#8fa6b8'; }
function statusName(s)  { return (META.statuses[s] || {}).name  || s; }
function typeName(t)    { return (META.types[t] || {}).name     || t; }
function typeColor(t)   { return (META.types[t] || {}).color    || '#8fa6b8'; }
function osName(o)      { return (META.os[o] || {}).name        || '—'; }

/* ---------- 模态框 ---------- */
function modal({ title, body, footer, width = 540, onMount }) {
  const mask = el('div', { class: 'modal-mask' });
  mask.innerHTML = `
    <div class="modal" style="max-width:${width}px">
      <div class="modal-h"><h3>${esc(title)}</h3><span class="x">${icoSvg('ui-close')}</span></div>
      <div class="modal-b"></div>
      <div class="modal-f"></div>
    </div>`;
  const bodyEl = $('.modal-b', mask);
  const footEl = $('.modal-f', mask);
  if (typeof body === 'string') bodyEl.innerHTML = body; else if (body) bodyEl.appendChild(body);
  (footer || []).forEach(b => footEl.appendChild(b));
  const close = () => { mask.classList.remove('show'); setTimeout(() => mask.remove(), 200); };
  $('.x', mask).onclick = close;
  mask.addEventListener('mousedown', e => { if (e.target === mask) close(); });
  document.addEventListener('keydown', function esckey(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esckey); }
  });
  document.body.appendChild(mask);
  requestAnimationFrame(() => mask.classList.add('show'));
  if (onMount) onMount(bodyEl, close);
  return { mask, body: bodyEl, close };
}
function confirmBox(msg, onYes) {
  modal({
    title: '请确认', width: 400, body: `<div style="line-height:1.9">${esc(msg)}</div>`,
    footer: [
      el('button', { class: 'btn', text: '取消', onclick: e => e.target.closest('.modal-mask').querySelector('.x').click() }),
      el('button', {
        class: 'btn danger', text: '确定',
        onclick: e => { e.target.closest('.modal-mask').querySelector('.x').click(); onYes(); },
      }),
    ],
  });
}

/** Promise 版确认框，正文按 HTML 渲染（调用方自己转义变量） */
function askConfirm(html, { title = '请确认', okText = '确定', width = 440 } = {}) {
  return new Promise(resolve => {
    let answer = false;
    const m = modal({
      title, width, body: `<div style="line-height:1.9">${html}</div>`,
      footer: [
        el('button', { class: 'btn', text: '取消', onclick: () => m.close() }),
        el('button', { class: 'btn danger', text: okText, onclick: () => { answer = true; m.close(); } }),
      ],
    });
    // 点遮罩或按 Esc 关闭时也要让 await 返回，否则调用方会一直挂着
    new MutationObserver((_, ob) => {
      if (!document.body.contains(m.mask)) { ob.disconnect(); resolve(answer); }
    }).observe(document.body, { childList: true });
  });
}

/* ---------- 时钟 ---------- */
function startClock(id = 'navclock') {
  const node = document.getElementById(id);
  if (!node) return;
  const tick = () => {
    const d = new Date();
    node.textContent = d.toLocaleTimeString('zh-CN', { hour12: false }) +
      '  ' + d.toLocaleDateString('zh-CN');
  };
  tick(); setInterval(tick, 1000);
}

/* ---------- 迷你折线图 ---------- */
function sparkline(canvas, series, colors, { fill = true, min = null, max = null } = {}) {
  const dpr = Math.min(devicePixelRatio || 1, 2);
  const W = canvas.clientWidth || 300, H = canvas.clientHeight || 80;
  canvas.width = W * dpr; canvas.height = H * dpr;
  const x = canvas.getContext('2d');
  x.setTransform(dpr, 0, 0, dpr, 0, 0);
  x.clearRect(0, 0, W, H);
  const pad = { l: 26, r: 6, t: 8, b: 12 };
  const w = W - pad.l - pad.r, h = H - pad.t - pad.b;
  x.strokeStyle = 'rgba(0,229,255,.09)'; x.lineWidth = 1;
  for (let i = 0; i <= 3; i++) {
    const y = pad.t + h * i / 3;
    x.beginPath(); x.moveTo(pad.l, y); x.lineTo(W - pad.r, y); x.stroke();
  }
  let mn = min, mx = max;
  if (mn === null || mx === null) {
    mn = Infinity; mx = -Infinity;
    series.forEach(s => s.forEach(v => { mn = Math.min(mn, v); mx = Math.max(mx, v); }));
    const sp = (mx - mn) || 1;
    mn = Math.max(0, mn - sp * .2); mx += sp * .2;
  }
  x.fillStyle = '#3c6c85'; x.font = '10px Bahnschrift, sans-serif'; x.textAlign = 'right';
  for (let i = 0; i <= 3; i++) {
    x.fillText(Math.round(mx - (mx - mn) * i / 3), pad.l - 5, pad.t + h * i / 3 + 3.5);
  }
  series.forEach((s, si) => {
    if (!s.length) return;
    const pts = s.map((v, i) => [
      pad.l + w * (s.length === 1 ? 0.5 : i / (s.length - 1)),
      pad.t + h * (1 - (v - mn) / ((mx - mn) || 1)),
    ]);
    if (fill === true || fill === si) {
      const g = x.createLinearGradient(0, pad.t, 0, pad.t + h);
      g.addColorStop(0, colors[si] + '55'); g.addColorStop(1, colors[si] + '00');
      x.beginPath(); x.moveTo(pts[0][0], pad.t + h);
      pts.forEach(p => x.lineTo(p[0], p[1]));
      x.lineTo(pts[pts.length - 1][0], pad.t + h); x.closePath();
      x.fillStyle = g; x.fill();
    }
    x.beginPath(); pts.forEach((p, i) => i ? x.lineTo(p[0], p[1]) : x.moveTo(p[0], p[1]));
    x.strokeStyle = colors[si]; x.lineWidth = 1.8;
    x.shadowColor = colors[si]; x.shadowBlur = 6; x.stroke(); x.shadowBlur = 0;
  });
}

/* ---------- 墙体几何 ---------- */
/**
 * 墙体用「弦 + 弓高」描述曲率：curve 是弧线中点到两端连线的垂距（cm），
 * 0 为直墙，正负决定往哪一侧凸。存一个标量比存控制点省事，
 * 拖动端点时弧形会自然跟着变，不用额外维护控制点。
 *
 * 画成二次贝塞尔时注意：曲线在 t=0.5 只走到控制点偏移量的一半，
 * 所以控制点要抬 2 倍弓高，弧中点才正好落在 curve 上。
 */
function wallGeom(w) {
  const x1 = +w.x1, y1 = +w.y1, x2 = +w.x2, y2 = +w.y2;
  const c = +w.curve || 0;
  const mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
  const len = Math.hypot(x2 - x1, y2 - y1) || 1;
  const nx = -(y2 - y1) / len, ny = (x2 - x1) / len;   // 左法线
  return {
    x1, y1, x2, y2, len, nx, ny,
    curved: Math.abs(c) >= 0.5,
    mx: mx + nx * c, my: my + ny * c,                  // 弧中点，拖拽手柄落在这
    cx: mx + nx * c * 2, cy: my + ny * c * 2,          // 贝塞尔控制点
  };
}
function wallPath(w) {
  const g = wallGeom(w);
  return g.curved
    ? `M ${g.x1} ${g.y1} Q ${g.cx} ${g.cy} ${g.x2} ${g.y2}`
    : `M ${g.x1} ${g.y1} L ${g.x2} ${g.y2}`;
}
/** 把墙体离散成折线点，供 3D 挤出和弧长估算使用 */
function wallPoints(w, seg = 0) {
  const g = wallGeom(w);
  if (!g.curved) return [[g.x1, g.y1], [g.x2, g.y2]];
  const n = seg || clamp(Math.round(g.len / 12), 8, 48);
  const pts = [];
  for (let i = 0; i <= n; i++) {
    const t = i / n, u = 1 - t;
    pts.push([
      u * u * g.x1 + 2 * u * t * g.cx + t * t * g.x2,
      u * u * g.y1 + 2 * u * t * g.cy + t * t * g.y2,
    ]);
  }
  return pts;
}
/** 实际弧长（折线近似），属性面板显示用 */
function wallLength(w) {
  const pts = wallPoints(w);
  let s = 0;
  for (let i = 1; i < pts.length; i++) {
    s += Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]);
  }
  return s;
}

/* ---------- 其它 ---------- */
/**
 * 从列表尾部摘掉装不下的条目，让大屏在任何分辨率下都不出现滚动条。
 * 返回被摘掉的条数，调用方可以在卡片标题上标出「显示 m/n」。
 */
function clipToFit(box, keepAtLeast = 1) {
  if (!box || !box.clientHeight) return 0;
  let cut = 0;
  while (box.children.length > keepAtLeast && box.scrollHeight > box.clientHeight) {
    box.lastElementChild.remove();
    cut++;
  }
  return cut;
}

const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
/** MySQL 的 DECIMAL 以字符串返回（"22.60"），SQLite 返回数字，统一成去掉多余零的数字 */
const num = (v, digits = 1) => Math.round((+v || 0) * 10 ** digits) / 10 ** digits;
const fmtPower = w => w >= 1000 ? (w / 1000).toFixed(1) + ' kW' : Math.round(w) + ' W';
function timeAgo(str) {
  if (!str) return '';
  const t = new Date(str.replace(' ', 'T')).getTime();
  const s = Math.max(0, (Date.now() - t) / 1000);
  if (s < 60) return Math.floor(s) + ' 秒前';
  if (s < 3600) return Math.floor(s / 60) + ' 分钟前';
  if (s < 86400) return Math.floor(s / 3600) + ' 小时前';
  return Math.floor(s / 86400) + ' 天前';
}
