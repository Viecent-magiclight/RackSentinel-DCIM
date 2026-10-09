<?php
/**
 * 登录页。唯一不需要鉴权的业务页面。
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/migrate.php';
require __DIR__ . '/lib/settings.php';
require __DIR__ . '/lib/auth.php';

if (!db_installed()) {
    header('Location: install.php');
    exit;
}
// 老库第一次进来时把 users / settings 表补上，否则连登录都没地方查
run_migrations();

auth_start();
$err  = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
// 只接受站内相对地址，挡掉 ?next=//evil.com 这种开放重定向
if ($next === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $next)) {
    $next = 'index.php';
}

if (is_logged_in()) {
    header('Location: ' . $next);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) {
        $err = '表单已过期，请重新提交';
    } else {
        [$ok, $msg] = auth_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
        if ($ok) {
            header('Location: ' . $next);
            exit;
        }
        $err = $msg;
    }
}

$stillDefault = (bool) fetch_val("SELECT 1 FROM users WHERE username = 'admin' LIMIT 1")
    && password_verify(DEFAULT_ADMIN_PASSWORD, (string) fetch_val("SELECT password_hash FROM users WHERE username = 'admin'", [], ''));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>登录 · <?= e(setting('app_name')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
<?php require __DIR__ . '/partials/icons.php'; ?>

<div class="login-bg"></div>
<div class="login-card">
  <div class="login-brand">
    <svg class="ico"><use href="#ico-ui-rack"/></svg>
    <div>
      <b><?= e(setting('app_name')) ?></b>
      <span><?= e(cfg('app.short')) ?></span>
    </div>
  </div>

  <form method="post" class="login-form" autocomplete="on">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <label class="fld">
      <span>用户名</span>
      <input type="text" name="username" autocomplete="username" autofocus required>
    </label>
    <label class="fld">
      <span>密码</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <?php if ($err): ?>
      <div class="login-err"><svg class="ico-sm"><use href="#ico-ui-alert"/></svg><?= e($err) ?></div>
    <?php endif; ?>

    <button class="btn primary login-go" type="submit">登 录</button>

    <?php if ($stillDefault): ?>
      <div class="hint login-tip">
        初始账号 <code>admin</code> / <code><?= e(DEFAULT_ADMIN_PASSWORD) ?></code>，
        登录后请到<b>系统设置</b>里改掉。
      </div>
    <?php endif; ?>
  </form>
</div>
</body>
</html>
