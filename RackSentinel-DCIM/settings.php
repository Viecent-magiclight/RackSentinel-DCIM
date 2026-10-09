<?php
$PAGE  = 'settings';
$TITLE = '系统设置';
require __DIR__ . '/partials/head.php';

$ok  = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) {
        $err = '表单已过期，请刷新页面后重试';
    } elseif (($_POST['form'] ?? '') === 'site') {
        $vals = [];
        foreach (setting_schema() as $k => $_) {
            $vals[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        if ($vals['app_name'] === '' || $vals['wall_title'] === '') {
            $err = '平台名称和大屏标题不能为空';
        } else {
            $vals['poll_ms'] = (string) max(1000, (int) $vals['poll_ms']);
            settings_save($vals);
            header('Location: settings.php?saved=1');
            exit;
        }
    } elseif (($_POST['form'] ?? '') === 'pwd') {
        $cur = (string) ($_POST['cur'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [(int) $ME['id']]);
        if (!password_verify($cur, (string) $row['password_hash'])) {
            $err = '当前密码不正确';
        } elseif (strlen($new) < 6) {
            $err = '新密码至少 6 位';
        } elseif ($new !== (string) ($_POST['new2'] ?? '')) {
            $err = '两次输入的新密码不一致';
        } else {
            update_row('users', (int) $ME['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            $ok = '密码已更新';
        }
    }
}
if (param('saved')) {
    $ok = '设置已保存';
}

$cur = settings_all();
?>
<div class="setwrap">
  <div class="card">
    <div class="card-t"><h3>站点与大屏</h3><span class="en">BRANDING</span></div>

    <?php if ($ok): ?><div class="flash ok"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>

    <form method="post" class="setform">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="site">
      <?php foreach (setting_schema() as $k => [$label, $default, $note]): ?>
        <label class="fld">
          <span><?= e($label) ?></span>
          <input type="<?= $k === 'poll_ms' ? 'number' : 'text' ?>" name="<?= e($k) ?>"
                 value="<?= e($cur[$k] ?? $default) ?>" <?= $k === 'poll_ms' ? 'min="1000" step="500"' : '' ?>>
          <em class="hint"><?= e($note) ?></em>
        </label>
      <?php endforeach; ?>
      <div class="setrow">
        <button class="btn primary" type="submit">
          <svg class="ico"><use href="#ico-ui-save"/></svg>保存设置</button>
        <a class="btn" href="wall.php" target="_blank">
          <svg class="ico"><use href="#ico-ui-cube"/></svg>预览大屏</a>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-t"><h3>账号安全</h3><span class="en">ACCOUNT</span></div>
    <div class="hint" style="margin-bottom:12px">
      当前登录：<b style="color:#eafcff"><?= e($ME['display_name'] ?: $ME['username']) ?></b>
      <span class="num">(<?= e($ME['username']) ?>)</span>
    </div>
    <form method="post" class="setform">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="pwd">
      <label class="fld"><span>当前密码</span>
        <input type="password" name="cur" autocomplete="current-password" required></label>
      <label class="fld"><span>新密码</span>
        <input type="password" name="new" autocomplete="new-password" minlength="6" required></label>
      <label class="fld"><span>确认新密码</span>
        <input type="password" name="new2" autocomplete="new-password" minlength="6" required></label>
      <div class="setrow">
        <button class="btn primary" type="submit">
          <svg class="ico"><use href="#ico-ui-save"/></svg>修改密码</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script>startClock();</script>
</body>
</html>
