<?php
/**
 * 鉴权模块：登录会话、密码、角色权限、客户隔离
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // 安全会话配置
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    // HTTPS 下启用 secure cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('GEOSESSID');
    session_start();
}

/** 当前登录用户（数组），未登录返回 null */
function current_user() {
    if (empty($_SESSION['uid'])) return null;
    return getClientById($_SESSION['uid']);
}

function is_logged_in() { return current_user() !== null; }
function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/** 要求登录，否则输出 401 并终止 */
function require_login() {
    if (!is_logged_in()) json_out(['error' => 'unauthorized', 'message' => '请先登录'], 401);
}

/** 要求管理员，否则 403 */
function require_admin() {
    require_login();
    if (!is_admin()) json_out(['error' => 'forbidden', 'message' => '需要管理员权限'], 403);
}

/** 登录：校验账号密码，成功后写 session */
function do_login($username, $password) {
    $u = getClientByUsername(trim($username));
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return ['ok' => false, 'message' => '账号或密码错误'];
    }
    if ((int)$u['enabled'] !== 1) {
        return ['ok' => false, 'message' => '账号已被禁用，请联系管理员'];
    }
    // 防会话固定攻击
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    $_SESSION['login_at'] = time();
    updateLastLogin($u['id']);
    return ['ok' => true, 'user' => publicUser($u)];
}

function do_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    return ['ok' => true];
}

/** 对外输出的用户信息（去掉密码哈希） */
function publicUser($u) {
    $brand = brandDefaults($u);
    return [
        'id' => $u['id'],
        'username' => $u['username'],
        'name' => $u['name'],
        'role' => $u['role'],
        'company' => $u['company'] ?? '',
        'phone' => $u['phone'] ?? '',
        'quota' => (int)($u['quota'] ?? 0),
        'used' => (int)($u['used'] ?? 0),
        'enabled' => (int)$u['enabled'],
        'last_login' => $u['last_login'] ?? null,
        'created_at' => $u['created_at'] ?? null,
        'brand' => $brand,
    ];
}

/** 当前客户的数据归属 ID：管理员可传 client_id 覆盖，普通客户只能用自己 */
function scope_client_id($requested = null) {
    $u = current_user();
    if (is_admin() && $requested) return $requested;
    return $u['id'];
}

/** 配额检查：-1 = 不限；0 = 无额度（管理员不消耗额度） */
function quota_available($clientId) {
    $c = getClientById($clientId);
    if (!$c) return false;
    $quota = (int)$c['quota'];
    if ($quota < 0 || $c['role'] === 'admin') return true; // -1 不限；管理员始终不限
    return (int)$c['used'] < $quota;
}

/** 消耗一次配额（记额度流水） */
function consume_quota($clientId) {
    credit_consume($clientId, 'diagnose', '执行一次诊断');
}
