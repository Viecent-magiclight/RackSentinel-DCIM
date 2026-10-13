<?php
/**
 * 调用本机 racksentinel-snmp -probe 进行连通性测试。
 */
function snmp_probe_binary(): ?string
{
    $candidates = [
        dirname(__DIR__) . '/agents/snmp/dist/racksentinel-snmp-linux-amd64',
        dirname(__DIR__) . '/agents/snmp/dist/racksentinel-snmp',
    ];
    foreach ($candidates as $p) {
        if (is_executable($p)) {
            return $p;
        }
    }
    $which = trim((string) shell_exec('command -v racksentinel-snmp 2>/dev/null'));
    return $which !== '' ? $which : null;
}

function snmp_probe_device(array $dev): array
{
    $host = $dev['snmp_host'] ?: $dev['mgmt_ip'] ?: $dev['ip'] ?? '';
    if ($host === '') {
        return ['ok' => false, 'error' => '缺少 SNMP 主机地址'];
    }
    $bin = snmp_probe_binary();
    if (!$bin) {
        return ['ok' => false, 'error' => '未找到 racksentinel-snmp 可执行文件，请先构建 agents/snmp'];
    }
    $port = (int) ($dev['snmp_port'] ?? 161);
    $ver  = $dev['snmp_version'] ?? '2c';
    $comm = $dev['snmp_community'] ?? 'public';
    $cmd  = escapeshellarg($bin) . ' -probe'
        . ' -host ' . escapeshellarg($host)
        . ' -port ' . (int) $port
        . ' -version ' . escapeshellarg($ver)
        . ' -community ' . escapeshellarg($comm)
        . ' 2>&1';
    $raw = shell_exec($cmd);
    $j   = json_decode((string) $raw, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => trim((string) $raw) ?: 'probe failed'];
    }
    return $j;
}
