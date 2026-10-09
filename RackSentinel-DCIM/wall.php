<?php
/**
 * 大屏独立页：和 index.php 同一份画面，但不要导航栏，顶部只留一条居中标题。
 * 标题文案在 settings.php 里配置。
 */
$PAGE  = 'wall';
$TITLE = '大屏';
$BARE  = true;
require __DIR__ . '/partials/head.php';

$sub = setting('wall_subtitle');
?>
<header class="wallbar">
  <div class="wallbar-t">
    <h1><?= e(setting('wall_title')) ?></h1>
    <?php if ($sub !== ''): ?><p><?= e($sub) ?></p><?php endif; ?>
  </div>

  <!-- 默认隐藏，鼠标移到顶部才浮现，免得大屏上常年挂着两个按钮 -->
  <div class="wallbar-ops">
    <button class="btn sm" id="btnFull" title="全屏显示">
      <svg class="ico-sm"><use href="#ico-ui-fit"/></svg><span>全屏</span></button>
    <a class="btn sm" href="index.php" title="返回管理后台（Esc）">
      <svg class="ico-sm"><use href="#ico-ui-close"/></svg>退出大屏</a>
  </div>
  <div class="wallbar-clock num" id="navclock">--:--:--</div>
</header>

<?php require __DIR__ . '/partials/dashboard.php'; ?>

<script src="assets/js/common.js"></script>
<script src="assets/js/scene3d.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
startClock();

const full = () => document.fullscreenElement
  ? document.exitFullscreen()
  : document.documentElement.requestFullscreen().catch(() => toast('浏览器拒绝了全屏请求', 'err'));
document.getElementById('btnFull').onclick = full;

document.addEventListener('keydown', ev => {
  if (ev.key === 'F11') { ev.preventDefault(); full(); }
  // 全屏时 Esc 由浏览器收走用于退出全屏，这里只处理窗口模式下的返回
  if (ev.key === 'Escape' && !document.fullscreenElement) location.href = 'index.php';
});
</script>
</body>
</html>
