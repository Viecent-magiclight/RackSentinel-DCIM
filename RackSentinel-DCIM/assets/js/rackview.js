/* =========================================================================
   42U 机柜立面编辑器
   · 按「列」并排展示整排机柜，便于对比与规划
   · 设备可在机柜内上下拖动改 U 位，也可拖到相邻机柜完成搬迁
   · U 位冲突前端预判 + 后端二次校验
   ========================================================================= */
(() => {
const stage = $('#stage');
const S = {
  racks: [],          // 侧边栏全部机柜（含 stats）
  view: [],           // 当前并排展示的机柜 [{rack, devices}]
  curId: 0,           // 选中的机柜
  sel: 0,             // 选中的设备
  compact: false,
  mode: localStorage.getItem('rackViewMode') === 'one' ? 'one' : 'row',  // one=单柜 row=整列
};
const uhOf = () => (S.compact ? 12 : 17);

/* ---------------- 机柜列表 ---------------- */
function renderList(filter = '') {
  const box = $('#rackList');
  const kw = filter.trim().toLowerCase();
  const list = S.racks.filter(r =>
    !kw || r.name.toLowerCase().includes(kw) || (r.row_label || '').toLowerCase().includes(kw));
  if (!list.length) { box.innerHTML = '<div class="empty">没有匹配的机柜</div>'; return; }

  box.innerHTML = list.map(r => {
    const st = r.stats || {};
    const pct = Math.round((st.used_u || 0) / (r.u_total || 42) * 100);
    const col = pct > 88 ? '#ff4d6a' : pct > 65 ? '#ffb020' : st.used_u ? '#2ef2a3' : '#5a7a8e';
    return `<div class="rackitem ${+S.curId === +r.id ? 'on' : ''}" data-id="${r.id}" style="border-left-color:${col}">
      <span class="dot" style="background:${col}"></span>
      <div><b>${esc(r.name)}</b><div class="r-sub">${esc(r.label || r.row_label || '通用机柜')}</div></div>
      <div class="r-u">${st.used_u || 0}/${r.u_total}U<br><span style="color:#5d8298">${st.total || 0} 台</span></div>
      <div class="r-bar"><i style="width:${pct}%;background:${col}"></i></div>
    </div>`;
  }).join('');
  $$('.rackitem', box).forEach(n => n.onclick = () => openRack(+n.dataset.id));
}
$('#rkSearch').addEventListener('input', e => renderList(e.target.value));

/* ---------------- 立面渲染 ---------------- */
function renderStage() {
  if (!S.view.length) return;
  const uh = uhOf();
  stage.innerHTML = S.view.map(v => frameHtml(v, uh)).join('');
  bindStage(uh);
  updateHeader();
}

function frameHtml({ rack, devices }, uh) {
  const total = +rack.u_total || 42;
  const cur = +rack.id === +S.curId;

  let ruler = '';
  for (let u = total; u >= 1; u--) {
    ruler += `<div class="${u % 5 === 0 || u === 1 ? 'mark' : ''}">${u}</div>`;
  }

  const occupied = new Set();
  devices.forEach(d => {
    for (let u = d.u_start; u < d.u_start + d.u_size; u++) occupied.add(u);
  });
  let empties = '';
  for (let u = 1; u <= total; u++) {
    if (occupied.has(u)) continue;
    empties += `<div class="u-empty" data-u="${u}" style="top:${(total - u) * uh}px;height:${uh}px"></div>`;
  }

  const st = rack.stats || {};
  const slim = S.view.length > 3 ? ' slim' : '';
  return `<div class="rack-frame${cur ? ' cur' : ''}${slim}" style="--u:${uh}px" data-frame="${rack.id}">
    <div class="rack-title">
      <svg class="ico-lg" style="color:${cur ? '#00e5ff' : '#4d7a92'}"><use href="#ico-ui-rack"/></svg>
      <b>${esc(rack.name)}</b>
      <span class="sub">${esc(rack.label || '')} · ${st.used_u || 0}/${total}U</span>
    </div>
    <div class="rack-body">
      <div class="u-ruler">${ruler}</div>
      <div class="rack-slots" data-rack="${rack.id}" style="height:${total * uh}px">${empties}${devices.map(d => devHtml(d, total, uh)).join('')}</div>
      <div class="u-ruler">${ruler}</div>
    </div>
  </div>`;
}

function devHtml(d, total, uh) {
  const t = META.types[d.type_key] || META.types.server;
  const h = d.u_size * uh;
  const top = (total - (d.u_start + d.u_size - 1)) * uh;
  const sel = +S.sel === +d.id ? ' sel' : '';
  const cp = h < 22 ? ' compact' : '';
  return `<div class="dev t-${d.type_key}${sel}${cp}" data-id="${d.id}"
      style="top:${top}px;height:${h - 1}px;border-left-color:${t.color}"
      title="${esc(d.name)}  U${d.u_start}-${d.u_start + d.u_size - 1}">
    <span class="d-led" style="color:${statusColor(d.status)}"></span>
    <span class="d-ic" style="color:${t.color}">${icoSvg(t.icon)}</span>
    ${d.os_key && d.os_key !== 'none' ? `<span class="d-os">${osIcon(d.os_key)}</span>` : ''}
    <span class="d-name">${esc(d.name)}</span>
    ${h >= 22 && d.hostname ? `<span class="d-u">${esc(d.hostname)}</span>` : ''}
    <span class="d-meta">${esc(d.ip || d.model || '')}　U${d.u_start}${d.u_size > 1 ? '-' + (d.u_start + d.u_size - 1) : ''}</span>
  </div>`;
}

function updateHeader() {
  const v = entry(S.curId);
  if (!v) return;
  const st = v.rack.stats || {};
  $('#rkTitle').textContent = `${v.rack.name} · ${v.rack.label || '通用机柜'}` +
    (S.view.length > 1 ? `（${v.rack.row_label || ''} 列共 ${S.view.length} 台机柜）` : '');
  $('#rkUsage').innerHTML = `<s></s>已用 ${st.used_u || 0}/${v.rack.u_total}U`;
  $('#rkPower').innerHTML = `<s></s>${fmtPower(st.power || 0)} / ${fmtPower(v.rack.power_capacity)}`;
}

const entry = id => S.view.find(v => +v.rack.id === +id);

/* ---------------- 交互：选中 / 新增 / 拖放 ---------------- */
function bindStage(uh) {
  $$('.rack-frame', stage).forEach(f => {
    f.addEventListener('mousedown', () => {
      const id = +f.dataset.frame;
      if (id !== +S.curId) { S.curId = id; renderList($('#rkSearch').value); updateHeader(); }
    }, true);
  });

  $$('.u-empty', stage).forEach(n => n.onclick = () => {
    const rackId = +n.closest('.rack-slots').dataset.rack;
    S.curId = rackId;
    openForm(null, +n.dataset.u);
  });

  $$('.dev', stage).forEach(n => {
    const id = +n.dataset.id;
    n.addEventListener('dblclick', () => openForm(findDevice(id)));
    n.addEventListener('mousedown', ev => startDrag(ev, n, id, uh));
  });
}

function findDevice(id) {
  for (const v of S.view) {
    const d = v.devices.find(x => +x.id === +id);
    if (d) return d;
  }
  return null;
}

function startDrag(ev, node, id, uh) {
  if (ev.button !== 0) return;
  ev.preventDefault();
  const dev = findDevice(id);
  if (!dev) return;

  S.sel = id;
  $$('.dev', stage).forEach(x => x.classList.toggle('sel', x === node));
  showDevice(id);

  const srcRackId = +node.closest('.rack-slots').dataset.rack;
  const r0 = node.getBoundingClientRect();
  const grabDY = ev.clientY - r0.top;

  // 跟随光标的浮动副本，原位留半透明占位
  const ghost = node.cloneNode(true);
  ghost.style.cssText += `position:fixed;left:${r0.left}px;top:${r0.top}px;width:${r0.width}px;
    height:${r0.height}px;margin:0;pointer-events:none;z-index:999;opacity:.95;
    box-shadow:0 10px 30px rgba(0,0,0,.6)`;
  document.body.appendChild(ghost);
  node.classList.add('dragging');

  const marker = document.createElement('div');
  marker.style.cssText = 'position:absolute;left:2px;right:2px;pointer-events:none;z-index:1;border:2px dashed';
  let target = null;   // {rackId, uStart, ok}

  const onMove = e => {
    ghost.style.left = (r0.left + (e.clientX - ev.clientX)) + 'px';
    ghost.style.top = (e.clientY - grabDY) + 'px';

    const slots = document.elementsFromPoint(e.clientX, e.clientY)
      .find(el => el.classList && el.classList.contains('rack-slots'));
    if (!slots) { marker.remove(); target = null; return; }

    const v = entry(+slots.dataset.rack);
    if (!v) { marker.remove(); target = null; return; }
    const total = +v.rack.u_total || 42;
    const sr = slots.getBoundingClientRect();
    const topIdx = Math.round((e.clientY - grabDY - sr.top) / uh);
    const uStart = clamp(total - topIdx - dev.u_size + 1, 1, total - dev.u_size + 1);
    const ok = !conflictAt(v, id, uStart, dev.u_size);

    target = { rackId: +slots.dataset.rack, uStart, ok };
    marker.style.top = ((total - (uStart + dev.u_size - 1)) * uh) + 'px';
    marker.style.height = (dev.u_size * uh - 1) + 'px';
    marker.style.borderColor = ok ? '#2ef2a3' : '#ff4d6a';
    marker.style.background = ok ? 'rgba(46,242,163,.14)' : 'rgba(255,77,106,.14)';
    slots.appendChild(marker);
  };

  const onUp = async () => {
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
    ghost.remove(); marker.remove();
    node.classList.remove('dragging');

    if (!target || (target.rackId === srcRackId && target.uStart === dev.u_start)) return;
    if (!target.ok) { toast('U 位冲突，已被其它设备占用', 'err'); return; }
    try {
      await api('device.move', {
        method: 'POST',
        body: { id, rack_id: target.rackId, u_start: target.uStart },
      });
      const moved = target.rackId !== srcRackId;
      toast(moved
        ? `${dev.name} 已搬迁到 ${entry(target.rackId).rack.name} U${target.uStart}`
        : `${dev.name} 已移动到 U${target.uStart}`, 'ok');
      await openRack(S.curId, true);
    } catch (err) {
      toast(err.message, 'err');
      renderStage();
    }
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function conflictAt(v, id, uStart, uSize) {
  const end = uStart + uSize - 1;
  return v.devices.some(d => {
    if (+d.id === +id) return false;
    const s = d.u_start, e = s + d.u_size - 1;
    return uStart <= e && end >= s;
  });
}
function firstFreeU(v, size) {
  const total = +v.rack.u_total || 42;
  for (let u = 1; u <= total - size + 1; u++) if (!conflictAt(v, 0, u, size)) return u;
  return 0;
}

/* ---------------- 设备表单字段（右侧面板与弹窗共用） ---------------- */
const FIELDS = ['name', 'hostname', 'ip', 'mgmt_ip', 'type_key', 'os_key', 'u_start', 'u_size',
                'vendor', 'model', 'serial', 'asset_tag', 'owner', 'status', 'power_w', 'note'];

function deviceFields(d, uTotal, p) {
  const opt = (obj, cur) => Object.entries(obj)
    .map(([k, m]) => `<option value="${k}" ${cur === k ? 'selected' : ''}>${esc(m.name)}</option>`).join('');
  return `
    <div class="row2">
      <label class="fld"><span>设备名称 *</span><input type="text" id="${p}name" value="${esc(d.name)}" placeholder="如 WEB-A02-01"></label>
      <label class="fld"><span>主机名 Hostname</span><input type="text" id="${p}hostname" value="${esc(d.hostname)}" placeholder="web01.dc-a.local"></label>
    </div>
    <div class="row2">
      <label class="fld"><span>业务 IP</span><input type="text" id="${p}ip" value="${esc(d.ip)}" placeholder="10.20.12.31"></label>
      <label class="fld"><span>管理 IP (BMC)</span><input type="text" id="${p}mgmt_ip" value="${esc(d.mgmt_ip)}" placeholder="172.16.12.31"></label>
    </div>
    <div class="row2">
      <label class="fld"><span>设备类型</span><select id="${p}type_key">${opt(META.types, d.type_key)}</select></label>
      <label class="fld"><span>操作系统 / 固件</span><select id="${p}os_key">${opt(META.os, d.os_key || 'none')}</select></label>
    </div>
    <div class="row3">
      <label class="fld"><span>起始 U 位</span><input type="number" id="${p}u_start" min="1" max="${uTotal}" value="${d.u_start}"></label>
      <label class="fld"><span>占用 U 数</span><input type="number" id="${p}u_size" min="1" max="20" value="${d.u_size}"></label>
      <label class="fld"><span>运行状态</span><select id="${p}status">${opt(META.statuses, d.status)}</select></label>
    </div>
    <div class="row3">
      <label class="fld"><span>厂商</span><input type="text" id="${p}vendor" value="${esc(d.vendor)}"></label>
      <label class="fld"><span>型号</span><input type="text" id="${p}model" value="${esc(d.model)}"></label>
      <label class="fld"><span>功耗 W</span><input type="number" id="${p}power_w" value="${esc(d.power_w)}"></label>
    </div>
    <div class="row3">
      <label class="fld"><span>序列号</span><input type="text" id="${p}serial" value="${esc(d.serial)}"></label>
      <label class="fld"><span>资产编号</span><input type="text" id="${p}asset_tag" value="${esc(d.asset_tag)}"></label>
      <label class="fld"><span>负责人</span><input type="text" id="${p}owner" value="${esc(d.owner)}"></label>
    </div>
    <label class="fld"><span>备注</span><textarea id="${p}note" rows="2">${esc(d.note)}</textarea></label>
    <div class="hint" id="${p}hint"></div>`;
}

/** 类型变化时联动：无操作系统的类型禁用系统下拉，并提示默认高度 */
function bindTypeSync(scope, p, onTypeChange) {
  const ts = $('#' + p + 'type_key', scope);
  const os = $('#' + p + 'os_key', scope);
  const sync = () => {
    const t = META.types[ts.value];
    os.disabled = !t.os;
    if (!t.os) os.value = 'none';
    $('#' + p + 'hint', scope).innerHTML =
      `默认高度 <code>${t.u}U</code>，${t.os ? '该类型需选择操作系统 / 固件' : '该类型无操作系统'}`;
  };
  ts.addEventListener('change', () => { onTypeChange && onTypeChange(); sync(); });
  sync();
}

function readFields(scope, p) {
  const o = {};
  FIELDS.forEach(f => { o[f] = $('#' + p + f, scope).value; });
  return o;
}

/* ---------------- 右侧：选中设备直接编辑 ---------------- */
function showDevice(id) {
  const d = findDevice(id);
  const box = $('#dvBox'), emp = $('#dvEmpty');
  if (!d) { box.style.display = 'none'; box.innerHTML = ''; emp.style.display = ''; return; }
  box.style.display = ''; emp.style.display = 'none';

  const v = entry(rackOf(d.id));
  const t = META.types[d.type_key] || META.types.server;
  box.innerHTML = `
    <div class="flex" style="margin-bottom:10px">
      ${typeBadge(d.type_key, 'lg')}
      <div style="min-width:0">
        <div style="font-size:13.5px;color:#eafcff;font-weight:600">${esc(d.name)}</div>
        <div style="font-size:11px;color:#6f90a6">${esc(t.name)} · ${esc(v ? v.rack.name : '')} U${d.u_start}-${d.u_start + d.u_size - 1}</div>
      </div>
      <span class="tag" style="color:${statusColor(d.status)};margin-left:auto"><s></s>${esc(statusName(d.status))}</span>
    </div>
    ${d.cpu !== undefined && d.cpu !== null && +d.cpu > 0
      ? `<div class="hint" style="margin-bottom:10px">实时负载　CPU <b style="color:#00e5ff">${num(d.cpu)}%</b>　内存 <b>${num(d.mem)}%</b>　温度 <b>${num(d.temp)}℃</b></div>`
      : ''}
    ${deviceFields(d, v ? v.rack.u_total : 42, 'e_')}
    <div class="flex mt12">
      <button class="btn sm primary" id="dvSave"><svg class="ico-sm"><use href="#ico-ui-save"/></svg>保存修改</button>
      <button class="btn sm danger" id="dvDel"><svg class="ico-sm"><use href="#ico-ui-trash"/></svg>下架</button>
    </div>`;

  bindTypeSync(box, 'e_', () => {
    $('#e_u_size', box).value = META.types[$('#e_type_key', box).value].u;
  });

  const btn = $('#dvSave', box);
  btn.onclick = async () => {
    const body = { id: d.id, rack_id: rackOf(d.id), ...readFields(box, 'e_') };
    if (!body.name.trim()) { toast('请填写设备名称', 'err'); return; }
    btn.disabled = true;
    try {
      await api('device.save', { method: 'POST', body });
      toast('已保存', 'ok');
      await openRack(S.curId, true);
    } catch (err) {
      toast(err.message, 'err');
      btn.disabled = false;
    }
  };

  $('#dvDel', box).onclick = () => confirmBox(`确定下架「${d.name}」？该设备记录将被删除。`, async () => {
    try {
      await api('device.delete', { query: { id: d.id } });
      toast('设备已下架', 'ok');
      S.sel = 0;
      await openRack(S.curId);
    } catch (err) { toast(err.message, 'err'); }
  });
}

/* ---------------- 新增 / 编辑表单 ---------------- */
function openForm(dev, presetU = 0) {
  const v = entry(S.curId);
  if (!v) { toast('请先选择机柜', 'err'); return; }
  const isNew = !dev;
  const d = dev || {
    type_key: 'server', os_key: 'winserver', status: 'online',
    u_start: presetU || firstFreeU(v, 2) || 1, u_size: 2,
    name: '', hostname: '', ip: '', mgmt_ip: '', vendor: '', model: '',
    serial: '', asset_tag: '', owner: '', power_w: 350, note: '',
  };
  const rackId = isNew ? S.curId : rackOf(d.id);

  const btnSave = el('button', { class: 'btn primary', text: isNew ? '安装到机柜' : '保存修改' });
  const m = modal({
    title: isNew ? `在 ${v.rack.name} 安装新设备` : `编辑设备 · ${d.name}`,
    width: 620,
    body: deviceFields(d, v.rack.u_total, 'f_'),
    footer: [el('button', { class: 'btn', text: '取消', onclick: () => m.close() }), btnSave],
    onMount(bodyEl) {
      bindTypeSync(bodyEl, 'f_', () => {
        $('#f_u_size', bodyEl).value = META.types[$('#f_type_key', bodyEl).value].u;
      });
    },
  });

  btnSave.onclick = async () => {
    const body = { id: d.id || 0, rack_id: rackId, ...readFields(m.body, 'f_') };
    if (!body.name.trim()) { toast('请填写设备名称', 'err'); return; }
    btnSave.disabled = true;
    try {
      const res = await api('device.save', { method: 'POST', body });
      m.close();
      toast(isNew ? '设备已安装' : '已保存', 'ok');
      S.sel = res.id;
      await openRack(S.curId, true);
    } catch (err) {
      toast(err.message, 'err');
      btnSave.disabled = false;
    }
  };
}

function rackOf(deviceId) {
  for (const v of S.view) {
    if (v.devices.some(x => +x.id === +deviceId)) return +v.rack.id;
  }
  return S.curId;
}

/* ---------------- 载入 ---------------- */
function norm(rack) {
  return {
    rack,
    devices: rack.devices.map(d => ({ ...d, u_start: +d.u_start, u_size: +d.u_size })),
  };
}

async function openRack(id, keepSel = false) {
  S.curId = +id;
  if (!keepSel) S.sel = 0;

  // 整列模式下把同一列的机柜并排展示，单柜模式只看当前这台
  const me = S.racks.find(r => +r.id === +id);
  const row = S.mode === 'row' && me && me.row_label
    ? S.racks.filter(r => r.row_label === me.row_label)
    : (me ? [me] : []);
  const detail = await Promise.all(row.map(r => api('rack.get', { query: { id: r.id } })));

  S.view = detail.map(norm);
  S.view.forEach(v => {
    const i = S.racks.findIndex(x => +x.id === +v.rack.id);
    if (i >= 0) S.racks[i].stats = v.rack.stats;
  });

  renderList($('#rkSearch').value);
  renderStage();
  showDevice(S.sel);
  history.replaceState(null, '', 'racks.php?rack=' + id);
}

$('#btnAdd').onclick = () => openForm(null, 0);
$('#btnExport').onclick = () => { location.href = 'export.php?r=racks'; };
$('#btnDensity').onclick = e => {
  S.compact = !S.compact;
  e.currentTarget.classList.toggle('on', S.compact);
  renderStage();
};

function setMode(m) {
  S.mode = m;
  localStorage.setItem('rackViewMode', m);
  $('#viewOne').classList.toggle('on', m === 'one');
  $('#viewRow').classList.toggle('on', m === 'row');
  if (S.curId) openRack(S.curId, true).catch(e => toast(e.message, 'err'));
}
$('#viewOne').onclick = () => setMode('one');
$('#viewRow').onclick = () => setMode('row');

function renderLegend() {
  $('#typeLegend').innerHTML = Object.entries(META.types).map(([, t]) =>
    `<span><span style="color:${t.color};display:inline-flex">${icoSvg(t.icon, 'ico-sm')}</span>${esc(t.name)}</span>`).join('');
}

(async () => {
  try {
    renderLegend();
    $('#viewOne').classList.toggle('on', S.mode === 'one');
    $('#viewRow').classList.toggle('on', S.mode === 'row');
    S.racks = await api('rack.list');
    const want = +new URLSearchParams(location.search).get('rack');
    const first = S.racks.find(r => +r.id === want) || S.racks[0];
    if (first) await openRack(first.id);
    else renderList();
  } catch (err) { toast('加载失败：' + err.message, 'err'); }
})();
})();
