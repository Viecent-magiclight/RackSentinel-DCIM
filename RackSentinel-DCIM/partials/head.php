<?php
/**
 * 页面公共头部
 * 用法： $PAGE = 'planner'; $TITLE = '平面图画板'; require __DIR__ . '/partials/head.php';
 *
 * 置 $BARE = true 可以不渲染导航栏（大屏独立页用）。
 */
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/catalog.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/migrate.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/auth.php';

if (!db_installed()) {
    header('Location: install.php');
    exit;
}
$ME = require_login();

$PAGE  = $PAGE  ?? 'index';
$TITLE = $TITLE ?? '首页';
$BARE  = $BARE  ?? false;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($TITLE) ?> · <?= e(setting('app_name')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body<?= $BARE ? ' class="bare"' : '' ?>>
<?php require __DIR__ . '/icons.php'; ?>
<script>
const META     = <?= catalog_json() ?>;
const POLL_MS  = <?= max(1000, (int) setting('poll_ms')) ?>;
</script>
<?php if (!$BARE) { render_nav($PAGE, $ME); } ?>
