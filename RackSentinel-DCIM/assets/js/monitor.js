(() => {
async function loadAgents() {
  const rows = await api('agent.list');
  const tb = $('#agentRows');
  if (!rows.length) {
    tb.innerHTML = '<tr><td colspan="7" class="empty">暂无代理，请创建主机 Agent 或轮询器令牌</td></tr>';
    return;
  }
  tb.innerHTML = rows.map(a => `<tr>
    <td>${esc(a.name)}</td>
    <td class="mono">${esc(a.kind)}</td>
    <td>${esc(a.device_name || '—')}</td>
    <td class="mono">${esc(a.token_prefix)}…</td>
    <td class="mono">${esc(a.last_seen_at || '—')}</td>
    <td>${a.enabled ? '<span class="tag" style="color:var(--green)"><s></s>启用</span>' : '<span class="tag" style="color:var(--grey)">停用</span>'}</td>
    <td><button class="btn sm danger" data-del="${a.id}">删除</button></td>
  </tr>`).join('');
  tb.querySelectorAll('[data-del]').forEach(b => {
    b.onclick = () => confirmBox('删除该代理？对应令牌将立即失效。', async () => {
      await api('agent.delete', { query: { id: b.dataset.del } });
      toast('已删除', 'ok');
      loadAgents();
    });
  });
}

async function pickDevice() {
  const list = await api('device.list', { query: { limit: 500 } });
  if (!list.length) { toast('请先添加设备', 'err'); return null; }
  const opts = list.map(d => `${d.id}: ${d.name} (${d.rack_name})`).join('\n');
  const id = prompt('输入要绑定的设备 ID：\n\n' + opts.slice(0, 2000));
  return id ? parseInt(id, 10) : null;
}

$('#btnAgentHost').onclick = async () => {
  const name = prompt('代理名称', 'host-agent');
  if (!name) return;
  const deviceId = await pickDevice();
  if (!deviceId) return;
  const res = await api('agent.create', { method: 'POST', body: { name, kind: 'host', device_id: deviceId } });
  alert(`请立即保存令牌（仅显示一次）：\n\n${res.token}`);
  loadAgents();
};

$('#btnAgentSnmp').onclick = async () => {
  const name = prompt('SNMP 轮询器名称', 'snmp-poller');
  if (!name) return;
  const res = await api('agent.create', { method: 'POST', body: { name, kind: 'snmp_poller', device_id: 0 } });
  alert(`SNMP 轮询器令牌：\n\n${res.token}`);
  loadAgents();
};

$('#btnAgentNotify').onclick = async () => {
  const name = prompt('通知器名称', 'alert-notifier');
  if (!name) return;
  const res = await api('agent.create', { method: 'POST', body: { name, kind: 'notifier', device_id: 0 } });
  alert(`通知器令牌：\n\n${res.token}`);
  loadAgents();
};

async function loadRcpt() {
  const rows = await api('notify.recipients');
  const tb = $('#rcptRows');
  tb.innerHTML = rows.map(r => `<tr>
    <td>${esc(r.name)}</td>
    <td class="mono">${esc(r.phone || '—')}</td>
    <td class="mono">${esc(r.sms_phone || '—')}</td>
    <td>${esc(r.min_level)}</td>
    <td>${r.enabled ? '是' : '否'}</td>
    <td><button class="btn sm danger" data-rdel="${r.id}">删除</button></td>
  </tr>`).join('') || '<tr><td colspan="6" class="empty">暂无收件人</td></tr>';
  tb.querySelectorAll('[data-rdel]').forEach(b => {
    b.onclick = () => confirmBox('删除收件人？', async () => {
      await api('notify.recipient.delete', { query: { id: b.dataset.rdel } });
      loadRcpt();
    });
  });
}

$('#btnAddRcpt').onclick = async () => {
  const name = prompt('姓名');
  if (!name) return;
  const phone = prompt('语音通知号码 (+86...)') || '';
  const sms = prompt('短信号码（可同语音）') || phone;
  await api('notify.recipient.save', { method: 'POST', body: { name, phone, sms_phone: sms, min_level: 'warning', enabled: 1 } });
  loadRcpt();
};

$('#btnTestNotify')?.addEventListener('click', async () => {
  const phone = prompt('测试号码 (+86...)');
  if (!phone) return;
  const channel = prompt('通道: both / sms / voice', 'both') || 'both';
  try {
    const r = await api('notify.test_send', { method: 'POST', body: { phone, channel } });
    toast('已提交测试发送', 'ok');
    console.log(r);
  } catch (e) { toast(e.message, 'err'); }
});

loadAgents();
loadRcpt();
})();
