(() => {
const levelColor = { critical: 'var(--red)', warning: 'var(--amber)', info: 'var(--grey)' };

async function load() {
  const list = await api('alert.list');
  const lv = $('#fLevel').value;
  const rows = list.filter(a => !lv || a.level === lv);
  $('#rows').innerHTML = rows.map(a => `<tr>
    <td class="mono">${esc(a.created_at)}</td>
    <td><span class="tag" style="color:${levelColor[a.level] || 'var(--grey)'}"><s></s>${esc(a.level)}</span></td>
    <td><b>${esc(a.title)}</b><div class="hint">${esc(a.detail || '')}</div></td>
    <td>${esc(a.device_name || '—')} <span class="hint">${esc(a.rack_name || '')}</span></td>
    <td>${a.acked ? '已确认' : `<button class="btn sm" data-ack="${a.id}">确认</button>`}</td>
  </tr>`).join('') || '<tr><td colspan="5" class="empty">暂无告警</td></tr>';
  $$('[data-ack]').forEach(b => b.onclick = async () => {
    await api('alert.ack', { query: { id: b.dataset.ack } });
    toast('已确认', 'ok');
    load();
  });
}
$('#btnRefresh').onclick = load;
$('#fLevel').onchange = load;
load();
})();
