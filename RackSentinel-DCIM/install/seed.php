<?php
/**
 * 模拟样本数据：机房平面布局 + 机柜 + 各类设备 + 指标 + 告警 + 传感器
 */

function seed_database(): array
{
    $pdo = db();
    $pdo->beginTransaction();

    // ---------------- 机房 ----------------
    $roomId = insert_row('rooms', [
        'code'        => 'DC-A-F2',
        'name'        => '一号数据中心 · A 区主机房',
        'width'       => 1200,
        'depth'       => 900,
        'grid'        => 10,
        'wall_height' => 320,
        'note'        => '两排面对面机柜，中间为冷通道；西侧为 UPS 配电间',
    ]);

    // ---------------- 平面图：墙体 / 门 / 窗 / 柱 ----------------
    $walls = [
        // 外墙
        ['wall', 0, 0, 1200, 0, 24],
        ['wall', 1200, 0, 1200, 900, 24],
        ['wall', 1200, 900, 0, 900, 24],
        ['wall', 0, 900, 0, 0, 24],
        // 西侧配电间隔断（中间留门洞）
        ['wall', 300, 0, 300, 520, 20],
        ['door', 300, 520, 300, 620, 14],
        ['wall', 300, 620, 300, 900, 20],
        // 主机房入口（南墙开门）
        ['door', 520, 900, 640, 900, 14],
        // 北侧观察窗
        ['window', 700, 0, 920, 0, 14],
        // 东北角弱电间
        ['glass', 1000, 0, 1000, 240, 12],
        ['glass', 1000, 240, 1200, 240, 12],
        ['door', 1000, 150, 1000, 240, 14],
        // 结构柱
        ['pillar', 380, 560, 380, 560, 50],
        ['pillar', 860, 560, 860, 560, 50],
    ];
    foreach ($walls as $i => $w) {
        insert_row('walls', [
            'room_id'    => $roomId,
            'kind'       => $w[0],
            'x1' => $w[1], 'y1' => $w[2], 'x2' => $w[3], 'y2' => $w[4],
            'thickness'  => $w[5],
            'sort_order' => $i,
        ]);
    }

    // ---------------- 传感器 ----------------
    $sensors = [
        ['温度探头 T-01', 'temp', 420, 130, 22.6, '℃'],
        ['温度探头 T-02', 'temp', 800, 130, 24.1, '℃'],
        ['温度探头 T-03', 'temp', 420, 790, 23.4, '℃'],
        ['温度探头 T-04', 'temp', 800, 790, 25.2, '℃'],
        ['湿度探头 H-01', 'humidity', 600, 330, 46.0, '%'],
        ['湿度探头 H-02', 'humidity', 600, 620, 48.5, '%'],
        ['烟感 SD-01', 'smoke', 520, 60, 0, ''],
        ['烟感 SD-02', 'smoke', 940, 700, 0, ''],
        ['漏水检测 WL-01', 'water', 150, 760, 0, ''],
        ['配电监测 PM-01', 'power', 150, 300, 86.4, 'kW'],
    ];
    foreach ($sensors as $s) {
        insert_row('sensors', [
            'room_id' => $roomId, 'name' => $s[0], 'kind' => $s[1],
            'x' => $s[2], 'y' => $s[3], 'value' => $s[4], 'unit' => $s[5],
            'status' => 'normal',
        ]);
    }

    // ---------------- 机柜 ----------------
    $rackDefs = [];
    $colX = [420, 490, 560, 630, 700, 770];
    foreach ($colX as $i => $x) {
        $rackDefs[] = ['A' . sprintf('%02d', $i + 1), $x, 220, 0,   'A 排', $i === 0 ? 'net' : 'server'];
    }
    foreach ($colX as $i => $x) {
        $rackDefs[] = ['B' . sprintf('%02d', $i + 1), $x, 450, 180, 'B 排', $i === 0 ? 'net' : 'server'];
    }
    foreach ([420, 490, 560, 630] as $i => $x) {
        $rackDefs[] = ['C' . sprintf('%02d', $i + 1), $x, 700, 0,   'C 排', 'storage'];
    }
    $rackDefs[] = ['P01', 150, 220, 90, '配电间', 'power'];
    $rackDefs[] = ['P02', 150, 420, 90, '配电间', 'power'];

    $roleLabel = [
        'net'     => '网络列头柜',
        'server'  => '计算服务器柜',
        'storage' => '存储备份柜',
        'power'   => 'UPS 配电柜',
    ];

    $rackIds = [];
    foreach ($rackDefs as $r) {
        [$name, $x, $y, $rot, $row, $role] = $r;
        $rackIds[$name] = [
            'id' => insert_row('racks', [
                'room_id'        => $roomId,
                'name'           => $name,
                'label'          => $roleLabel[$role],
                'row_label'      => $row,
                'x' => $x, 'y' => $y,
                'width' => 60, 'depth' => 110,
                'rotation'       => $rot,
                'u_total'        => 42,
                'power_capacity' => $role === 'power' ? 12000 : 6000,
                'note'           => null,
            ]),
            'role' => $role,
        ];
    }

    // ---------------- 设备 ----------------
    $vendors = [
        'server'  => [
            ['Dell', 'PowerEdge R750'], ['Dell', 'PowerEdge R650'], ['HPE', 'ProLiant DL380 Gen10'],
            ['华为', 'FusionServer 2288H V6'], ['浪潮', 'NF5280M6'], ['联想', 'ThinkSystem SR650'],
            ['新华三', 'UniServer R4900 G5'],
        ],
        'switch'  => [
            ['Cisco', 'Nexus 93180YC-EX'], ['华为', 'CE6881-48S6CQ'], ['新华三', 'S6850-56HF'],
            ['锐捷', 'RG-S6510-48VS8CQ'], ['Arista', 'DCS-7050SX3'],
        ],
        'router'  => [['Cisco', 'ASR 1001-X'], ['华为', 'NE20E-S2F'], ['新华三', 'MSR3620']],
        'firewall'=> [['深信服', 'AF-2000-FH2130'], ['华为', 'USG6650E'], ['Fortinet', 'FortiGate 600E'], ['奇安信', 'NSG4000']],
        'gateway' => [['深信服', 'AD-3000'], ['F5', 'BIG-IP i2800'], ['绿盟', 'NF-3000']],
        'loadbalancer' => [['F5', 'BIG-IP i4800'], ['A10', 'Thunder 3030S'], ['深信服', 'AD-5000']],
        'storage' => [['Dell', 'PowerVault ME5024'], ['华为', 'OceanStor 5310 V6'], ['NetApp', 'AFF A250'], ['浪潮', 'AS5500G5']],
        'tape'    => [['IBM', 'TS4300'], ['Quantum', 'Scalar i3']],
        'ups'     => [['施耐德', 'Smart-UPS SRT 6000VA'], ['山特', 'C6KS'], ['科华', 'KR-RM 10K']],
        'pdu'     => [['APC', 'AP8853'], ['施耐德', 'PDU-16A-8'], ['突破', 'TPS-1608']],
        'kvm'     => [['Aten', 'CL5800'], ['Raritan', 'DKX3-108']],
        'patch'   => [['康普', 'CAT6A-24P'], ['泛达', 'CP24BLY']],
        'console' => [['Aten', 'CL1008M']],
    ];

    $serverRoles = [
        ['WEB',   'web',   ['ubuntu', 'centos', 'rhel', 'kylin']],
        ['APP',   'app',   ['ubuntu', 'rhel', 'centos', 'uos']],
        ['DB',    'db',    ['rhel', 'centos', 'winserver', 'kylin']],
        ['K8S',   'k8s',   ['ubuntu', 'debian', 'rhel']],
        ['VIRT',  'esxi',  ['esxi', 'proxmox']],
        ['AD',    'ad',    ['winserver']],
        ['FILE',  'file',  ['winserver', 'ubuntu']],
        ['BAK',   'bak',   ['centos', 'ubuntu']],
        ['MQ',    'mq',    ['rhel', 'ubuntu']],
        ['CACHE', 'redis', ['debian', 'ubuntu']],
        ['LOG',   'log',   ['ubuntu', 'centos']],
        ['MON',   'mon',   ['debian', 'ubuntu']],
    ];
    // 网络设备的系统由厂商决定，避免出现「锐捷交换机跑华为 VRP」这种矛盾数据
    $vendorOs = [
        'Cisco' => 'ciscoios', '华为' => 'vrp', '新华三' => 'comware',
        '锐捷' => 'rgos', 'Arista' => 'eos', 'Juniper' => 'junos',
    ];

    $statusPool = array_merge(
        array_fill(0, 86, 'online'),
        array_fill(0, 7, 'warning'),
        array_fill(0, 4, 'offline'),
        array_fill(0, 3, 'maintenance')
    );

    $seq = ['web' => 0, 'app' => 0, 'db' => 0, 'k8s' => 0, 'esxi' => 0, 'ad' => 0, 'file' => 0,
            'bak' => 0, 'redis' => 0, 'mq' => 0, 'log' => 0, 'mon' => 0];
    $ipHost  = 20;
    $mgmtHost = 20;
    $deviceIds = [];
    // 跨机柜唯一的编号（核心路由、防火墙、负载均衡等全局设备）
    $gseq = ['rt' => 0, 'fw' => 0, 'gw' => 0, 'lb' => 0, 'tape' => 0];

    foreach ($rackIds as $rackName => $rack) {
        $rid  = $rack['id'];
        $role = $rack['role'];
        $u    = 1;                 // 自下而上安装
        $plan = [];                // [type, uSize]
        $rseq = [];                // 机柜内各类设备的流水号
        $nx   = function (string $k) use (&$rseq): string {
            $rseq[$k] = ($rseq[$k] ?? 0) + 1;
            return sprintf('%02d', $rseq[$k]);
        };

        if ($role === 'power') {
            $plan = [['pdu', 1], ['ups', 3], ['ups', 3], ['pdu', 1], ['ups', 3], ['blank', 2], ['pdu', 1]];
        } elseif ($role === 'net') {
            $plan = [
                ['pdu', 1], ['patch', 1], ['patch', 1],
                ['switch', 1], ['switch', 1], ['blank', 1],
                ['firewall', 2], ['firewall', 2], ['blank', 1],
                ['loadbalancer', 1], ['loadbalancer', 1], ['router', 1], ['router', 1],
                ['gateway', 1], ['blank', 1], ['kvm', 1], ['console', 1],
                ['patch', 1], ['switch', 2], ['switch', 2],
            ];
        } elseif ($role === 'storage') {
            $plan = [['pdu', 1], ['storage', 4], ['storage', 4], ['blank', 1],
                     ['storage', 2], ['tape', 4], ['blank', 1],
                     ['server', 2], ['server', 2], ['switch', 1], ['patch', 1]];
        } else {
            $plan = [['pdu', 1], ['patch', 1], ['switch', 1], ['switch', 1]];
            $n = mt_rand(9, 14);
            for ($i = 0; $i < $n; $i++) {
                $plan[] = ['server', mt_rand(0, 10) > 6 ? 2 : 1];
            }
            if (mt_rand(0, 1)) {
                $plan[] = ['blade', 7];
            }
        }

        foreach ($plan as $item) {
            [$type, $uSize] = $item;
            if ($u + $uSize - 1 > 42) {
                break;
            }
            if (mt_rand(0, 100) > 88) {   // 随机留出空 U
                $u += 1;
                if ($u + $uSize - 1 > 42) break;
            }

            $status = $type === 'blank' ? 'online' : $statusPool[array_rand($statusPool)];
            $vlist  = $vendors[$type] ?? $vendors['server'];
            [$vendor, $model] = $vlist[array_rand($vlist)];
            $osKey = 'none';
            $name = $hostname = $ip = $mgmt = null;
            $power = 0;

            switch ($type) {
                case 'server':
                case 'blade':
                    [$prefix, $slug, $osList] = $serverRoles[array_rand($serverRoles)];
                    $seq[$slug]++;
                    $idx      = sprintf('%02d', $seq[$slug]);
                    $name     = "{$prefix}-{$rackName}-{$idx}";
                    $hostname = strtolower("{$slug}{$idx}.{$rackName}.dc-a.local");
                    $osKey    = $osList[array_rand($osList)];
                    $ip       = '10.20.' . (ord($rackName[0]) - 64) . '.' . $ipHost++;
                    $mgmt     = '172.16.' . (ord($rackName[0]) - 64) . '.' . $mgmtHost++;
                    $power    = $type === 'blade' ? mt_rand(1800, 3200) : mt_rand(240, 620);
                    break;
                case 'switch':
                    $i        = $nx('sw');
                    $name     = "SW-{$rackName}-{$i}";
                    $hostname = strtolower("sw{$i}.{$rackName}.dc-a.local");
                    $osKey    = $vendorOs[$vendor] ?? 'appliance';
                    $ip       = '10.0.0.' . $ipHost++;
                    $mgmt     = '172.16.254.' . $mgmtHost++;
                    $power    = mt_rand(120, 380);
                    break;
                case 'router':
                    $i        = sprintf('%02d', ++$gseq['rt']);
                    $name     = "RT-CORE-{$i}";
                    $hostname = strtolower("rt-core{$i}.dc-a.local");
                    $osKey    = $vendorOs[$vendor] ?? 'appliance';
                    $ip       = '10.0.1.' . $ipHost++;
                    $mgmt     = '172.16.254.' . $mgmtHost++;
                    $power    = mt_rand(180, 420);
                    break;
                case 'firewall':
                    $zone     = ['INET', 'DMZ', 'CORE', 'OFFICE'][$gseq['fw'] % 4];
                    $i        = sprintf('%02d', ++$gseq['fw']);
                    $name     = "FW-{$zone}-{$i}";
                    $hostname = strtolower("fw-{$zone}{$i}.dc-a.local");
                    $osKey    = 'appliance';
                    $ip       = '10.0.2.' . $ipHost++;
                    $mgmt     = '172.16.254.' . $mgmtHost++;
                    $power    = mt_rand(220, 480);
                    break;
                case 'gateway':
                    $i        = sprintf('%02d', ++$gseq['gw']);
                    $name     = "GW-INET-{$i}";
                    $hostname = strtolower("gw-inet{$i}.dc-a.local");
                    $osKey    = 'appliance';
                    $ip       = '10.0.3.' . $ipHost++;
                    $mgmt     = '172.16.254.' . $mgmtHost++;
                    $power    = mt_rand(160, 320);
                    break;
                case 'loadbalancer':
                    $svc      = ['WEB', 'API'][$gseq['lb'] % 2];
                    $i        = sprintf('%02d', ++$gseq['lb']);
                    $name     = "LB-{$svc}-{$i}";
                    $hostname = strtolower("lb-{$svc}{$i}.dc-a.local");
                    $osKey    = 'appliance';
                    $ip       = '10.0.4.' . $ipHost++;
                    $mgmt     = '172.16.254.' . $mgmtHost++;
                    $power    = mt_rand(200, 400);
                    break;
                case 'storage':
                    $i        = $nx('san');
                    $name     = "SAN-{$rackName}-{$i}";
                    $hostname = strtolower("san{$i}.{$rackName}.dc-a.local");
                    $osKey    = 'appliance';
                    $ip       = '10.30.1.' . $ipHost++;
                    $mgmt     = '172.16.30.' . $mgmtHost++;
                    $power    = mt_rand(600, 1400);
                    break;
                case 'tape':
                    $i        = sprintf('%02d', ++$gseq['tape']);
                    $name     = "TAPE-LIB-{$i}";
                    $hostname = strtolower("tape-lib{$i}.dc-a.local");
                    $osKey    = 'appliance';
                    $ip       = '10.30.2.' . $ipHost++;
                    $power    = mt_rand(300, 700);
                    break;
                case 'ups':
                    $name     = "UPS-{$rackName}-" . $nx('ups');
                    $osKey    = 'none';
                    $mgmt     = '172.16.90.' . $mgmtHost++;
                    $power    = mt_rand(1200, 2600);
                    break;
                case 'pdu':
                    $name     = "PDU-{$rackName}-" . $nx('pdu');
                    $osKey    = 'none';
                    $mgmt     = '172.16.91.' . $mgmtHost++;
                    $power    = mt_rand(20, 60);
                    break;
                case 'kvm':
                    $name  = "KVM-{$rackName}";
                    $osKey = 'none';
                    $mgmt  = '172.16.92.' . $mgmtHost++;
                    $power = 45;
                    break;
                case 'console':
                    $name  = "CONSOLE-{$rackName}";
                    $osKey = 'none';
                    $power = 60;
                    break;
                case 'patch':
                    $name  = "PATCH-{$rackName}-" . $nx('patch');
                    $osKey = 'none';
                    break;
                case 'blank':
                    $name  = "盲板 {$uSize}U";
                    $osKey = 'none';
                    break;
            }

            $deviceIds[] = insert_row('devices', [
                'rack_id'   => $rid,
                'name'      => $name,
                'hostname'  => $hostname,
                'ip'        => $ip,
                'mgmt_ip'   => $mgmt,
                'type_key'  => $type,
                'os_key'    => $osKey,
                'u_start'   => $u,
                'u_size'    => $uSize,
                'vendor'    => $type === 'blank' ? null : $vendor,
                'model'     => $type === 'blank' ? null : $model,
                'serial'    => $type === 'blank' ? null : strtoupper(substr(md5($rackName . $u . $name), 0, 10)),
                'asset_tag' => $type === 'blank' ? null : 'ZC' . str_pad((string)(count($deviceIds) + 1001), 6, '0', STR_PAD_LEFT),
                'owner'     => ['系统运维组', '网络组', '数据库组', '云平台组', '安全组'][mt_rand(0, 4)],
                'status'    => $type === 'blank' ? 'online' : $status,
                'power_w'   => $power,
                'note'      => null,
            ]);

            $u += $uSize;
        }
    }

    // ---------------- 指标历史 ----------------
    $devices = fetch_all('SELECT id, type_key, status, power_w FROM devices');
    $st = db()->prepare(
        'INSERT INTO device_metrics (device_id, cpu, mem, disk, temp, net_in, net_out, power, recorded_at)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $cpuTypes = cpu_type_keys();
    foreach ($devices as $d) {
        if (in_array($d['type_key'], passive_type_keys(), true)) {
            continue;
        }
        $off = $d['status'] === 'offline';
        $hasCpu = in_array($d['type_key'], $cpuTypes, true);
        $base = [
            'cpu'  => $off || !$hasCpu ? 0 : mt_rand(8, 65),
            'mem'  => $off || !$hasCpu ? 0 : mt_rand(25, 78),
            'disk' => $off || !$hasCpu ? 0 : mt_rand(30, 82),
            'temp' => $off ? 0 : mt_rand(26, 44),
        ];
        for ($i = 11; $i >= 0; $i--) {
            $ts = date('Y-m-d H:i:s', time() - $i * 300);
            $jit = fn($v, $r) => $v <= 0 ? 0 : max(0, min(100, $v + mt_rand(-$r * 10, $r * 10) / 10));
            $st->execute([
                $d['id'],
                $jit($base['cpu'], 8), $jit($base['mem'], 4), $jit($base['disk'], 1),
                $off ? 0 : $base['temp'] + mt_rand(-20, 20) / 10,
                $off || !$hasCpu ? 0 : mt_rand(5, 900) / 10,
                $off || !$hasCpu ? 0 : mt_rand(5, 700) / 10,
                $off ? 0 : $d['power_w'] * mt_rand(70, 108) / 100,
                $ts,
            ]);
        }
    }

    // ---------------- 告警 ----------------
    $abnormal = fetch_all(
        "SELECT d.id, d.name, d.hostname, d.rack_id, d.status, r.name AS rack_name
         FROM devices d JOIN racks r ON r.id = d.rack_id
         WHERE d.status IN ('offline','warning','maintenance')"
    );
    $tpl = [
        'offline'     => ['critical', '%s 失去心跳', '设备 %s 已连续 3 次探测超时，请现场确认电源与链路'],
        'warning'     => ['warning',  '%s 指标越限', '设备 %s CPU / 温度持续高于阈值，建议排查负载'],
        'maintenance' => ['info',     '%s 进入维护', '设备 %s 已挂起监控告警，维护窗口进行中'],
    ];
    $n = 0;
    foreach ($abnormal as $d) {
        if ($n++ > 18) break;
        [$lvl, $title, $detail] = $tpl[$d['status']];
        insert_row('alerts', [
            'device_id'  => $d['id'],
            'rack_id'    => $d['rack_id'],
            'level'      => $lvl,
            'title'      => sprintf($title, $d['name']),
            'detail'     => sprintf($detail, $d['hostname'] ?: $d['name']) . '（机柜 ' . $d['rack_name'] . '）',
            'acked'      => 0,
            'created_at' => date('Y-m-d H:i:s', time() - mt_rand(60, 7200)),
        ]);
    }
    insert_row('alerts', [
        'device_id' => null, 'rack_id' => null, 'level' => 'warning',
        'title' => 'B 排冷通道回风温度偏高',
        'detail' => '温度探头 T-04 读数 25.2℃，已超过设定阈值 25.0℃，请检查精密空调送风',
        'acked' => 0, 'created_at' => date('Y-m-d H:i:s', time() - 900),
    ]);

    $pdo->commit();

    return [
        'rooms'   => 1,
        'walls'   => count($walls),
        'racks'   => count($rackIds),
        'devices' => count($deviceIds),
        'sensors' => count($sensors),
        'metrics' => (int) fetch_val('SELECT COUNT(*) FROM device_metrics'),
        'alerts'  => (int) fetch_val('SELECT COUNT(*) FROM alerts'),
    ];
}
