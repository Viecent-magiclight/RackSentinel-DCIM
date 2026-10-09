<?php
$PAGE  = 'index';
$TITLE = '运维大屏';
require __DIR__ . '/partials/head.php';

// 大屏画面本身在 wall.php（无导航版）里复用同一份
require __DIR__ . '/partials/dashboard.php';
?>
<script src="assets/js/common.js"></script>
<script src="assets/js/scene3d.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>startClock();</script>
</body>
</html>
