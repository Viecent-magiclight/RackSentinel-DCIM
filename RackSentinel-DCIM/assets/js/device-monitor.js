(() => {
if (!DEVICE_ID) {
  $('#summary').textContent = '缺少设备 ID';
  return;
}

function drawTrend(metrics) {
  const cv = $('#trend');
  const dpr = Math.min(devicePixelRatio || 1, 2);
  cv.width = cv.clientWidth * dpr;
  cv.height = 160 * dpr;
  const x = cv.getContext('2d');
  x.setTransform(dpr, 0, 0, dpr, 0, 0);
  x.clearRect(0, 0, cv.width, cv.height);
  const pts = metrics.slice().reverse();
  if (pts.length < 2) return;
  const w = cv.clientWidth, h = 160;
  const line = (key, color) => {
    x.strokeStyle = color;
    x.beginPath();
    pts.forEach((p, i) => {
      const px = i / (pts.length - 1) * (w - 20) + 10;
      const py = h - 10 - (+p[key] || 0) / 100 * (h - 20);
      i ? x.lineTo(px, py) : x.moveTo(px, py);
    });
    x.stroke();
  };
  line('cpu', 'var(--cyan)');
  line('mem', 'var(--amber)');
}

async function load() {
  const d = await api('device.monitor', { query: { id: DEVICE_ID } });
  $('#devTitle').textContent = d.name + ' · 监控';
  $('#summary').innerHTML = `
    <span class="tag" style="color:${statusColor(d.status)}"><s></s>${esc(statusName(d.status))}</span>
    ${esc(d.rack_name)} · ${esc(d.hostname || '')} · 模式 <b>${esc(d.monitor_mode || 'none')}</b>
    ${d.snmp_meta ? `<br>sysObjectID <code class="mono">${esc(d.snmp_meta.sys_object_id || '')}</code> 配置 ${esc(d.snmp_profile || d.snmp_meta.profile_id || '—')}` : ''}`;
  drawTrend(d.metrics || []);
  $('#sensors').innerHTML = (d.hw_sensors || []).map(s =>
    `<div class="flex" style="justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--line)">
      <span>${esc(s.label)}</span><b class="num">${num(s.value)} ${esc(s.unit)}</b></div>`).join('') || '<div class="empty">暂无传感器数据</div>';
  $('#ifaces').innerHTML = (d.interfaces || []).map(i => `<tr>
    <td class="mono">${i.if_index}</td><td>${esc(i.name)}</td>
    <td>${esc(i.admin_status)}</td><td>${esc(i.oper_status)}</td>
    <td class="mono">${num(i.in_rate_mbps)} / ${num(i.out_rate_mbps)}</td>
    <td class="mono">${i.in_errors}/${i.out_errors}</td></tr>`).join('') || '<tr><td colspan="6" class="empty">暂无接口数据（需 SNMP 轮询）</td></tr>';
}
load().catch(e => toast(e.message, 'err'));
})();
