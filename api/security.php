<?php
/**
 * 安全模块：请求频控、登录失败锁定、客户端 IP、审计日志
 */
require_once __DIR__ . '/db.php';

/** 客户端 IP：只信任 REMOTE_ADDR，避免伪造 X-Forwarded-For */
function client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * 频控：在 $window 秒内同一 $bucket+$identity 最多 $limit 次
 * 返回 [allowed(bool), retry_after(int)]
 */
function rate_hit($bucket, $identity, $limit, $window) {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        k TEXT PRIMARY KEY,
        hits INTEGER NOT NULL DEFAULT 0,
        ts INTEGER NOT NULL
    )");
    // 低频清理过期窗口
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM rate_limits WHERE ts < " . (time() - 86400));
    }
    $key = $bucket . '|' . $identity;
    $now = time();
    $st = $pdo->prepare("SELECT hits, ts FROM rate_limits WHERE k=?");
    $st->execute([$key]);
    $row = $st->fetch();
    if (!$row || ($now - (int)$row['ts']) >= $window) {
        $pdo->prepare("INSERT INTO rate_limits (k,hits,ts) VALUES (?,1,?) ON CONFLICT(k) DO UPDATE SET hits=1, ts=excluded.ts")
            ->execute([$key, $now]);
        return [true, 0];
    }
    if ((int)$row['hits'] >= $limit) {
        return [false, max(1, $window - ($now - (int)$row['ts']))];
    }
    $pdo->prepare("UPDATE rate_limits SET hits=hits+1 WHERE k=?")->execute([$key]);
    return [true, 0];
}

/** 频控守卫：超限直接输出 429 */
function rate_guard($bucket, $identity, $limit, $window, $message = '请求过于频繁，请稍后再试') {
    [$ok, $retry] = rate_hit($bucket, $identity, $limit, $window);
    if (!$ok) {
        header('Retry-After: ' . $retry);
        json_out(['error' => 'rate_limited', 'message' => $message . "（{$retry} 秒后重试）"], 429);
    }
}

/** 频控配置（后台可调） */
function rateLimits() {
    $s = getSettings([
        'rl_login'    => '10',   // 每 IP 每 10 分钟登录尝试次数
        'rl_register' => '8',    // 每 IP 每 1 小时注册次数
        'rl_diagnose' => '60',   // 每 IP 每 1 小时诊断次数
    ]);
    return [
        'login'    => max(1, (int)$s['rl_login']),
        'register' => max(1, (int)$s['rl_register']),
        'diagnose' => max(1, (int)$s['rl_diagnose']),
    ];
}

/** 登录失败锁定：同一账号+IP 连续失败 N 次锁定 M 秒 */
function login_failure_check($username, $ip) {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_failures (
        k TEXT PRIMARY KEY,
        fails INTEGER NOT NULL DEFAULT 0,
        locked_until INTEGER NOT NULL DEFAULT 0,
        updated INTEGER NOT NULL
    )");
    $key = strtolower($username) . '|' . $ip;
    $st = $pdo->prepare("SELECT fails, locked_until FROM login_failures WHERE k=?");
    $st->execute([$key]);
    $row = $st->fetch();
    if ($row && (int)$row['locked_until'] > time()) {
        return [false, (int)$row['locked_until'] - time()];
    }
    return [true, 0];
}

function login_failure_record($username, $ip) {
    $pdo = db();
    $key = strtolower($username) . '|' . $ip;
    $now = time();
    $pdo->prepare("INSERT INTO login_failures (k,fails,locked_until,updated) VALUES (?,1,0,?)
        ON CONFLICT(k) DO UPDATE SET fails = CASE WHEN (excluded.updated - login_failures.updated) > 900 THEN 1 ELSE login_failures.fails+1 END,
        locked_until = CASE WHEN login_failures.fails+1 >= 8 THEN excluded.updated + 900 ELSE 0 END,
        updated = excluded.updated")->execute([$key, $now]);
}

function login_failure_clear($username, $ip) {
    db()->prepare("DELETE FROM login_failures WHERE k=?")
        ->execute([strtolower($username) . '|' . $ip]);
}

/** 写审计/操作日志 */
function log_event($event, $detail = '', $clientId = null) {
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event TEXT NOT NULL,
            detail TEXT NOT NULL DEFAULT '',
            client_id TEXT,
            ip TEXT,
            created_at TEXT NOT NULL
        )");
        $st = $pdo->prepare("INSERT INTO logs (event,detail,client_id,ip,created_at) VALUES (?,?,?,?,?)");
        $st->execute([$event, mb_substr((string)$detail, 0, 500), $clientId, client_ip(), date('Y-m-d H:i:s')]);
    } catch (Throwable $e) { /* 日志失败不影响主流程 */ }
}

function listLogs($limit = 100) {
    return db()->query("SELECT * FROM logs ORDER BY id DESC LIMIT " . (int)$limit)->fetchAll();
}
