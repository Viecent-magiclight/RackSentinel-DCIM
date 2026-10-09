<?php
/**
 * 一键安装：建库 → 建表 → 灌入模拟样本数据
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/catalog.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/migrate.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/install/seed.php';

// 装完之后这个页面能清库重装，必须跟后台一样要登录；未安装时才放行
if (db_installed() && table_exists('users') && fetch_val('SELECT COUNT(*) FROM users', [], 0) > 0) {
    require_login();
}

$driver  = db_driver();
$log     = [];
$error   = null;
$done    = false;
$summary = [];

// 老库进来时顺手补上后加的列，免得用户为了一个新字段重装丢数据
if (db_installed()) {
    try {
        foreach (run_migrations() as $m) {
            $log[] = "表结构升级：新增 $m";
        }
    } catch (Throwable $e) {
        $error = '表结构升级失败：' . $e->getMessage();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    set_time_limit(300);
    try {
        // 1. MySQL 先建库
        if ($driver === 'mysql') {
            $name = cfg('mysql.database');
            db(false)->exec(
                "CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $log[] = "数据库 `{$name}` 已就绪";
        } else {
            $log[] = 'SQLite 文件库：' . cfg('sqlite.path');
        }

        // 2. 建表
        $file = __DIR__ . '/install/schema.' . $driver . '.sql';
        $pdo  = db();
        foreach (sql_statements(file_get_contents($file)) as $stmt) {
            $pdo->exec($stmt);
        }
        $log[] = '表结构创建完成（' . basename($file) . '）';

        // 3. 样本数据
        $summary = seed_database();
        $log[]   = '模拟样本数据写入完成';
        $done    = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$installed = db_installed();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>安装 · <?= htmlspecialchars(cfg('app.name')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
  body{display:grid;place-items:center;min-height:100vh;padding:40px 20px}
  .install{width:100%;max-width:760px}
  .steps{display:grid;gap:10px;margin:18px 0}
  .step{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;background:var(--panel);border:1px solid var(--line)}
  .step b{color:var(--cyan)}
  .kv{display:grid;grid-template-columns:150px 1fr;gap:8px 14px;font-size:13px}
  .kv span{color:var(--txt-dim)}
  .kv code{color:#eafcff}
  .sum{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px}
  .sum div{background:rgba(0,40,64,.4);border:1px solid var(--line);padding:12px;text-align:center}
  .sum .v{font-size:24px;color:var(--cyan);font-family:Bahnschrift,sans-serif}
  .sum .n{font-size:11px;color:var(--txt-dim);margin-top:3px}
</style>
</head>
<body>
<div class="install">
  <div class="card">
    <div class="card-t"><h3>系统安装</h3><span class="en"><?= strtoupper($driver) ?> · INSTALLER</span></div>

    <div class="kv">
      <span>应用名称</span><code><?= htmlspecialchars(cfg('app.name')) ?></code>
      <span>数据库驱动</span><code><?= $driver ?></code>
      <?php if ($driver === 'mysql'): ?>
        <span>连接地址</span><code><?= htmlspecialchars(cfg('mysql.host') . ':' . cfg('mysql.port')) ?></code>
        <span>数据库名</span><code><?= htmlspecialchars(cfg('mysql.database')) ?></code>
        <span>账号</span><code><?= htmlspecialchars(cfg('mysql.username')) ?></code>
      <?php else: ?>
        <span>数据文件</span><code><?= htmlspecialchars(cfg('sqlite.path')) ?></code>
      <?php endif; ?>
      <span>当前状态</span>
      <code style="color:<?= $installed ? '#2ef2a3' : '#ffb020' ?>">
        <?= $installed ? '已安装，可直接进入系统' : '尚未安装' ?>
      </code>
    </div>

    <?php if ($error): ?>
      <div class="step" style="border-color:var(--red);margin-top:16px">
        <b style="color:var(--red)">安装失败</b>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
      <p class="hint" style="margin-top:10px">
        若使用 MySQL，请确认 <code>config/config.php</code> 中的账号密码正确且服务已启动；
        也可以设置环境变量 <code>RACK_DB_DRIVER=sqlite</code> 先以文件库方式体验。
      </p>
    <?php endif; ?>

    <?php if ($log): ?>
      <div class="steps">
        <?php foreach ($log as $l): ?>
          <div class="step"><b>✓</b><div><?= htmlspecialchars($l) ?></div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($done): ?>
      <div class="sum">
        <?php foreach ([
          '机房' => $summary['rooms'], '平面图元' => $summary['walls'], '机柜' => $summary['racks'],
          '设备' => $summary['devices'], '传感器' => $summary['sensors'],
          '指标记录' => $summary['metrics'], '告警' => $summary['alerts'],
        ] as $k => $v): ?>
          <div><div class="v"><?= $v ?></div><div class="n"><?= $k ?></div></div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px">
        <a class="btn primary" href="index.php">进入运维大屏</a>
        <a class="btn" href="planner.php">机房平面图画板</a>
        <a class="btn" href="racks.php">机柜 U 位管理</a>
      </div>
    <?php else: ?>
      <form method="post" style="margin-top:18px;display:flex;gap:10px;align-items:center">
        <button class="btn primary" type="submit">
          <?= $installed ? '重新安装（会清空现有数据）' : '开始安装并写入样本数据' ?>
        </button>
        <?php if ($installed): ?>
          <a class="btn" href="index.php">直接进入系统</a>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
