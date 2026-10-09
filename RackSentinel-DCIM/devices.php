<?php
$PAGE  = 'devices';
$TITLE = '设备清单';
require __DIR__ . '/partials/head.php';
?>
<div style="padding:12px;height:calc(100vh - var(--nav-h));display:flex;flex-direction:column;gap:12px">

  <div class="card plain" style="flex:none">
    <div class="flex" style="flex-wrap:wrap;gap:9px">
      <div style="position:relative;width:260px">
        <input type="search" id="q" placeholder="搜索名称 / 主机名 / IP / 型号 / 资产号…">
      </div>
      <select id="fType" style="width:150px"><option value="">全部类型</option>
        <?php foreach (device_types() as $k => $t): ?>
          <option value="<?= e($k) ?>"><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="fOs" style="width:160px"><option value="">全部操作系统</option>
        <?php foreach (os_types() as $k => $o): ?>
          <option value="<?= e($k) ?>"><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="fStatus" style="width:130px"><option value="">全部状态</option>
        <?php foreach (device_statuses() as $k => $s): ?>
          <option value="<?= e($k) ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="fRack" style="width:150px"><option value="">全部机柜</option></select>
      <button class="btn" id="btnClear"><svg class="ico"><use href="#ico-ui-close"/></svg>重置</button>
      <span class="hint" style="margin-left:auto" id="count">—</span>
      <button class="btn" id="btnExport"><svg class="ico"><use href="#ico-ui-export"/></svg>导出 Excel</button>
      <button class="btn primary" id="btnImport"><svg class="ico"><use href="#ico-ui-import"/></svg>导入 Excel</button>
      <input type="file" id="fileXlsx" accept=".xlsx" hidden>
    </div>
  </div>

  <div class="card plain grow" style="padding:0;overflow:auto">
    <table class="grid">
      <thead><tr>
        <th style="width:34px"></th>
        <th>设备名称</th><th>主机名</th><th>业务 IP</th><th>管理 IP</th>
        <th style="width:140px">操作系统</th><th style="width:110px">机柜 / U 位</th>
        <th>厂商型号</th><th style="width:80px">功耗</th><th style="width:92px">状态</th>
      </tr></thead>
      <tbody id="rows"><tr><td colspan="10" class="empty">加载中…</td></tr></tbody>
    </table>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script>
(() => {
let timer = null;

async function load() {
  const q = {
    q: $('#q').value, type: $('#fType').value, os: $('#fOs').value,
    status: $('#fStatus').value, rack_id: $('#fRack').value, limit: 1000,
  };
  const list = await api('device.list', { query: q });
  $('#count').textContent = `共 ${list.length} 台设备`;
  $('#rows').innerHTML = list.map(d => {
    const t = META.types[d.type_key] || META.types.server;
    return `<tr data-rack="${d.rack_id}">
      <td>${typeBadge(d.type_key)}</td>
      <td><b style="color:#eafcff">${esc(d.name)}</b><div style="font-size:10px;color:#5d8298">${esc(t.name)} · ${d.u_size}U</div></td>
      <td class="mono">${esc(d.hostname || '—')}</td>
      <td class="mono">${esc(d.ip || '—')}</td>
      <td class="mono">${esc(d.mgmt_ip || '—')}</td>
      <td><span class="flex">${osIcon(d.os_key)}<span>${esc(osName(d.os_key))}</span></span></td>
      <td class="mono">${esc(d.rack_name)} · U${d.u_start}</td>
      <td>${esc([d.vendor, d.model].filter(Boolean).join(' ') || '—')}</td>
      <td class="mono">${d.power_w} W</td>
      <td><span class="tag" style="color:${statusColor(d.status)}"><s></s>${esc(statusName(d.status))}</span></td>
    </tr>`;
  }).join('') || '<tr><td colspan="10" class="empty">没有符合条件的设备</td></tr>';

  $$('#rows tr[data-rack]').forEach(n =>
    n.onclick = () => location.href = 'racks.php?rack=' + n.dataset.rack);
}

/* ---------------- 导出 / 导入 ---------------- */
/** 导出跟随页面上的筛选条件，看到什么就导出什么 */
$('#btnExport').onclick = () => {
  const p = new URLSearchParams({ r: 'devices' });
  const f = { q: $('#q').value, type: $('#fType').value, os: $('#fOs').value,
              status: $('#fStatus').value, rack_id: $('#fRack').value };
  Object.entries(f).forEach(([k, v]) => v && p.set(k, v));
  location.href = 'export.php?' + p;
};

$('#btnImport').onclick = () => $('#fileXlsx').click();
$('#fileXlsx').onchange = async e => {
  const file = e.target.files[0];
  if (file) await doImport(file);
  e.target.value = '';          // 允许连续导入同一个文件
};

async function doImport(file) {
  const btn = $('#btnImport');
  btn.disabled = true;
  try {
    const send = dry => {
      const fd = new FormData();
      fd.append('file', file);
      return fetch('import.php' + (dry ? '?dry=1' : ''), { method: 'POST', body: fd })
        .then(r => r.json());
    };

    const pre = await send(true);
    if (pre.error) throw new Error(pre.error);
    if (!pre.ok) return showErrors(pre);

    const s = pre.summary;
    const ok = await askConfirm(
      `即将导入 <b>${esc(file.name)}</b>：<br><br>
       新增 <b style="color:#2ef2a3">${s.create}</b> 台　
       更新 <b style="color:#00e5ff">${s.update}</b> 台　
       删除 <b style="color:#ff4d6a">${s.delete}</b> 台<br><br>
       <span class="hint">删除不可撤销，确认后立即写入数据库。</span>`,
      { title: '确认导入', okText: '确认写入' });
    if (!ok) return;

    const res = await send(false);
    if (res.error) throw new Error(res.error);
    if (!res.ok) return showErrors(res);

    toast(`导入完成：新增 ${res.summary.create} · 更新 ${res.summary.update} · 删除 ${res.summary.delete}`, 'ok');
    await load();
  } catch (err) {
    toast('导入失败：' + err.message, 'err');
  } finally {
    btn.disabled = false;
  }
}

function showErrors(res) {
  const list = res.errors.map(e => `<tr><td class="mono">第 ${e.row} 行</td><td>${esc(e.msg)}</td></tr>`).join('');
  const more = res.error_total > res.errors.length
    ? `<div class="hint mt8">另有 ${res.error_total - res.errors.length} 条未列出</div>` : '';
  modal({
    title: '校验未通过，未写入任何数据', width: 640,
    body: `
      <div class="hint" style="margin-bottom:10px">共 ${res.error_total} 处问题，请修正后重新导入。</div>
      <div style="max-height:46vh;overflow:auto">
        <table class="grid"><thead><tr><th style="width:86px">位置</th><th>说明</th></tr></thead>
        <tbody>${list}</tbody></table>
      </div>${more}`,
  });
}

const debounced = () => { clearTimeout(timer); timer = setTimeout(() => load().catch(e => toast(e.message, 'err')), 260); };
$('#q').addEventListener('input', debounced);
['fType', 'fOs', 'fStatus', 'fRack'].forEach(id => $('#' + id).addEventListener('change', debounced));
$('#btnClear').onclick = () => {
  $('#q').value = '';
  ['fType', 'fOs', 'fStatus', 'fRack'].forEach(id => $('#' + id).value = '');
  load();
};

(async () => {
  try {
    const racks = await api('rack.list');
    $('#fRack').innerHTML += racks.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
    await load();
  } catch (e) { toast('加载失败：' + e.message, 'err'); }
})();
})();
startClock();
</script>
</body>
</html>
