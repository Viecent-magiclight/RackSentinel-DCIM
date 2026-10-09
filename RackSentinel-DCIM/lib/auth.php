<?php
/**
 * 会话与登录。
 *
 * 会话 Cookie 用 SameSite=Lax：浏览器不会把它带到跨站发起的表单 POST 上，
 * 配合下面的同源检查，后台接口不用再给每个 fetch 塞 CSRF token。
 */

const AUTH_MAX_FAILS   = 5;      // 窗口内失败多少次开始锁
const AUTH_WINDOW_SEC  = 900;    // 统计窗口
const AUTH_LOCK_SECOND = 90;     // 锁多久

function auth_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('RACKSID');
    session_start();
}

function current_user(): ?array
{
    auth_start();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = fetch_one('SELECT id, username, display_name, role FROM users WHERE id = ?', [(int) $_SESSION['uid']]);
        if (!$user) {               // 账号被删了，顺手把会话清掉
            auth_logout();
            return null;
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** 页面级拦截：未登录跳登录页，并记住原本要去的地址 */
function require_login(): array
{
    $u = current_user();
    if ($u) {
        return $u;
    }
    $next = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?next=' . urlencode($next));
    exit;
}

/** 接口级拦截：返回 401 JSON，前端据此跳登录页 */
function require_login_api(): array
{
    $u = current_user();
    if (!$u) {
        json_out(['error' => '登录已失效，请重新登录', 'need_login' => true], 401);
    }
    return $u;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * 还要等多少秒才能再试，0 表示可以试。
 *
 * 失败记录按来源 IP 存库，不存会话——存会话的话攻击者清掉 Cookie 就重新开始，
 * 等于没限速。按 IP 也不会出现「猜某个用户名把真人锁在门外」的拒绝服务。
 */
function auth_lock_remaining(): int
{
    $since = date('Y-m-d H:i:s', time() - AUTH_WINDOW_SEC);
    $rows  = fetch_all(
        'SELECT at FROM login_attempts WHERE ip = ? AND at > ? ORDER BY at DESC LIMIT ?',
        [client_ip(), $since, AUTH_MAX_FAILS]
    );
    if (count($rows) < AUTH_MAX_FAILS) {
        return 0;
    }
    $last = strtotime((string) $rows[0]['at']);
    return max(0, $last + AUTH_LOCK_SECOND - time());
}

/** 校验账号密码，返回 [成功?, 提示语] */
function auth_login(string $username, string $password): array
{
    auth_start();
    $wait = auth_lock_remaining();
    if ($wait > 0) {
        return [false, "尝试过于频繁，请 $wait 秒后再试"];
    }

    $user = fetch_one('SELECT * FROM users WHERE username = ?', [$username]);
    // 账号不存在时也要真跑一次 bcrypt，否则「立刻返回」会暴露用户名存不存在。
    // 这串是随机口令的有效 hash，永远验不过，只为凑够计算耗时
    $hash = $user['password_hash'] ?? '$2y$12$r38wFaMvMppvQyh4oEPs7.wGOPZn60ETkgWGLyzcXxP7GjZ0dVJ9S';
    if (!password_verify($password, $hash) || !$user) {
        insert_row('login_attempts', ['ip' => client_ip(), 'username' => substr($username, 0, 40), 'at' => now_str()]);
        q('DELETE FROM login_attempts WHERE at < ?', [date('Y-m-d H:i:s', time() - AUTH_WINDOW_SEC * 4)]);
        return [false, '用户名或密码不正确'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        update_row('users', (int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }
    session_regenerate_id(true);           // 防会话固定
    $_SESSION['uid'] = (int) $user['id'];
    q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    update_row('users', (int) $user['id'], ['last_login_at' => now_str()]);
    return [true, ''];
}

function auth_logout(): void
{
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string
{
    auth_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    auth_start();
    $sent = (string) ($_POST['_csrf'] ?? '');
    return $sent !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent);
}

/**
 * 接口的同源检查：浏览器在跨站请求上一定会带 Origin，
 * 对不上就拒掉。GET 不改状态，放行。
 */
function require_same_origin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;                     // 老浏览器的同源请求可能不带 Origin
    }
    $port       = parse_url($origin, PHP_URL_PORT);
    $originHost = parse_url($origin, PHP_URL_HOST) . ($port ? ':' . $port : '');
    if (strcasecmp($originHost, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
        json_out(['error' => '跨站请求被拒绝'], 403);
    }
}
