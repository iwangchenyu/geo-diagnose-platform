<?php
/**
 * SQLite 数据库层（含客户/用户体系）
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib.php';

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    initSchema($pdo);
    return $pdo;
}

function initSchema($pdo) {
    // 客户/用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id TEXT PRIMARY KEY,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'customer',
        company TEXT DEFAULT '',
        phone TEXT DEFAULT '',
        quota INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0,
        enabled INTEGER NOT NULL DEFAULT 1,
        last_login TEXT,
        created_at TEXT NOT NULL
    )");

    // 平台设置（可配置项）
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audits (
        id TEXT PRIMARY KEY,
        client_id TEXT,
        url TEXT NOT NULL,
        domain TEXT NOT NULL,
        score INTEGER NOT NULL DEFAULT 0,
        result TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS visibility (
        id TEXT PRIMARY KEY,
        client_id TEXT,
        brand TEXT NOT NULL,
        industry TEXT NOT NULL,
        question TEXT NOT NULL,
        platforms TEXT NOT NULL,
        mode TEXT NOT NULL,
        score REAL NOT NULL DEFAULT 0,
        result TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");
    // 迁移：为旧表补字段（必须在建索引之前）
    foreach (['audits', 'visibility'] as $t) {
        $cols = $pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('client_id', $cols)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN client_id TEXT");
        }
        if (!in_array('share_token', $cols)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN share_token TEXT DEFAULT ''");
        }
        if (!in_array('share_expires', $cols)) {
            // NULL = 永不过期（历史报告保持可访问）
            $pdo->exec("ALTER TABLE $t ADD COLUMN share_expires TEXT DEFAULT NULL");
        }
    }

    // 客户白标报告品牌配置字段
    $clientCols = $pdo->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $brandCols = [
        'report_brand'   => "TEXT DEFAULT ''",
        'report_logo'    => "TEXT DEFAULT ''",
        'report_color'   => "TEXT DEFAULT '#2563EB'",
        'report_tagline' => "TEXT DEFAULT ''",
        'report_contact' => "TEXT DEFAULT ''",
        'report_footer'  => "TEXT DEFAULT ''",
    ];
    foreach ($brandCols as $col => $def) {
        if (!in_array($col, $clientCols)) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
        }
    }

    // 为历史报告补齐分享令牌
    foreach (['audits', 'visibility'] as $t) {
        $ids = $pdo->query("SELECT id FROM $t WHERE share_token IS NULL OR share_token=''")->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) {
            $st = $pdo->prepare("UPDATE $t SET share_token=? WHERE id=?");
            foreach ($ids as $id) {
                $st->execute([uid('S'), $id]);
            }
        }
    }

    // 邀请裂变字段（GrowMind 式邀请注册奖励）
    $inviteCols = [
        'invite_code'   => "TEXT DEFAULT ''",
        'invited_by'    => "TEXT DEFAULT ''",
        'invite_earned' => "INTEGER NOT NULL DEFAULT 0",
    ];
    foreach ($inviteCols as $col => $def) {
        if (!in_array($col, $clientCols)) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
        }
    }
    // 为老客户补齐邀请码
    $rows = $pdo->query("SELECT id FROM clients WHERE invite_code IS NULL OR invite_code=''")->fetchAll(PDO::FETCH_COLUMN);
    if ($rows) {
        $st = $pdo->prepare("UPDATE clients SET invite_code=? WHERE id=?");
        foreach ($rows as $cid) {
            $st->execute([inviteCode(), $cid]);
        }
    }
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_clients_invite_code ON clients(invite_code)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audits_created ON audits(created_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visibility_created ON visibility(created_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audits_client ON audits(client_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visibility_client ON visibility(client_id)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_audits_share_token ON audits(share_token)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_visibility_share_token ON visibility(share_token)");

    // 额度流水（注册赠送 / 邀请奖励 / 兑换 / 诊断消耗 / 管理员调整）
    $pdo->exec("CREATE TABLE IF NOT EXISTS credits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id TEXT NOT NULL,
        amount INTEGER NOT NULL,
        balance_after INTEGER NOT NULL,
        kind TEXT NOT NULL,
        remark TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_credits_client ON credits(client_id, id DESC)");

    // 兑换码（额度包，无需商户号的付费闭环）
    $pdo->exec("CREATE TABLE IF NOT EXISTS redeem_codes (
        code TEXT PRIMARY KEY,
        amount INTEGER NOT NULL,
        batch TEXT NOT NULL DEFAULT '',
        used_by TEXT,
        used_at TEXT,
        created_at TEXT NOT NULL
    )");

    // 操作与登录日志
    $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event TEXT NOT NULL,
        detail TEXT NOT NULL DEFAULT '',
        client_id TEXT,
        ip TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_created ON logs(id DESC)");

    // 一次性语义迁移：旧版 quota=0 表示「不限」，改为 -1 表示不限，0 表示无额度
    if (getSetting('quota_semantics_v2', '0') !== '1') {
        $pdo->exec("UPDATE clients SET quota=-1 WHERE quota=0");
        setSetting('quota_semantics_v2', '1');
    }
}

// ==================== 客户/用户 CRUD ====================

function getClientById($id) {
    $st = db()->prepare("SELECT * FROM clients WHERE id=?");
    $st->execute([$id]);
    return $st->fetch();
}

function getClientByUsername($username) {
    $st = db()->prepare("SELECT * FROM clients WHERE username=?");
    $st->execute([$username]);
    return $st->fetch();
}

function listClients() {
    return db()->query("SELECT * FROM clients ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, created_at DESC")->fetchAll();
}

function createClient($data) {
    $id = uid('C');
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $st = db()->prepare("INSERT INTO clients (id,username,password_hash,name,role,company,phone,quota,used,enabled,created_at)
        VALUES (?,?,?,?,?,?,?,?,0,1,?)");
    $st->execute([
        $id,
        trim($data['username']),
        $hash,
        trim($data['name'] ?: $data['username']),
        $data['role'] === 'admin' ? 'admin' : 'customer',
        trim($data['company'] ?? ''),
        trim($data['phone'] ?? ''),
        (int)($data['quota'] ?? 0),
        date('Y-m-d H:i:s'),
    ]);
    return $id;
}

function updateClient($id, $data) {
    $fields = []; $vals = [];
    $map = ['name','role','company','phone','quota','enabled'];
    foreach ($map as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f=?";
            $vals[] = ($f === 'quota' || $f === 'enabled') ? (int)$data[$f] : trim($data[$f]);
        }
    }
    if (!empty($data['password'])) {
        $fields[] = "password_hash=?";
        $vals[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (!$fields) return false;
    $vals[] = $id;
    $st = db()->prepare("UPDATE clients SET " . implode(',', $fields) . " WHERE id=?");
    return $st->execute($vals);
}

function deleteClient($id) {
    $pdo = db();
    // 不能删除最后一个管理员
    $c = getClientById($id);
    if ($c && $c['role'] === 'admin') {
        $n = $pdo->query("SELECT COUNT(*) FROM clients WHERE role='admin' AND enabled=1")->fetchColumn();
        if ($n <= 1) return ['ok' => false, 'message' => '不能删除最后一个管理员'];
    }
    $st = $pdo->prepare("DELETE FROM clients WHERE id=?");
    $st->execute([$id]);
    return ['ok' => true];
}

function updateLastLogin($id) {
    $st = db()->prepare("UPDATE clients SET last_login=? WHERE id=?");
    $st->execute([date('Y-m-d H:i:s'), $id]);
}

function resetUsage($id) {
    $st = db()->prepare("UPDATE clients SET used=0 WHERE id=?");
    $st->execute([$id]);
}

// ==================== 报告 CRUD（按客户隔离）====================

function saveAudit($id, $clientId, $url, $domain, $score, $result) {
    $st = db()->prepare("INSERT INTO audits (id,client_id,url,domain,score,result,share_token,share_expires,created_at) VALUES (?,?,?,?,?,?,?,?,?)");
    $st->execute([$id, $clientId, $url, $domain, $score, json_encode($result, JSON_UNESCAPED_UNICODE), uid('S'), shareExpiry(), date('Y-m-d H:i:s')]);
}

function listAudits($clientId = null, $limit = 50) {
    $pdo = db();
    if ($clientId) {
        $st = $pdo->prepare("SELECT id,client_id,url,domain,score,created_at FROM audits WHERE client_id=? ORDER BY created_at DESC LIMIT ?");
        $st->execute([$clientId, (int)$limit]);
    } else {
        $st = $pdo->prepare("SELECT a.id,a.client_id,a.url,a.domain,a.score,a.created_at,c.name as client_name FROM audits a LEFT JOIN clients c ON a.client_id=c.id ORDER BY a.created_at DESC LIMIT ?");
        $st->execute([(int)$limit]);
    }
    return $st->fetchAll();
}

function getAudit($id, $clientId = null) {
    $sql = "SELECT * FROM audits WHERE id=?";
    $params = [$id];
    if ($clientId) { $sql .= " AND client_id=?"; $params[] = $clientId; }
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    if ($row) $row['result'] = json_decode($row['result'], true);
    return $row;
}

function saveVisibility($id, $clientId, $brand, $industry, $question, $platforms, $mode, $score, $result) {
    $st = db()->prepare("INSERT INTO visibility (id,client_id,brand,industry,question,platforms,mode,score,result,share_token,share_expires,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$id, $clientId, $brand, $industry, $question, json_encode($platforms, JSON_UNESCAPED_UNICODE), $mode, $score, json_encode($result, JSON_UNESCAPED_UNICODE), uid('S'), shareExpiry(), date('Y-m-d H:i:s')]);
}

function listVisibility($clientId = null, $limit = 50) {
    $pdo = db();
    if ($clientId) {
        $st = $pdo->prepare("SELECT id,client_id,brand,industry,question,mode,score,created_at FROM visibility WHERE client_id=? ORDER BY created_at DESC LIMIT ?");
        $st->execute([$clientId, (int)$limit]);
    } else {
        $st = $pdo->prepare("SELECT v.id,v.client_id,v.brand,v.industry,v.question,v.mode,v.score,v.created_at,c.name as client_name FROM visibility v LEFT JOIN clients c ON v.client_id=c.id ORDER BY v.created_at DESC LIMIT ?");
        $st->execute([(int)$limit]);
    }
    return $st->fetchAll();
}

function getVisibility($id, $clientId = null) {
    $sql = "SELECT * FROM visibility WHERE id=?";
    $params = [$id];
    if ($clientId) { $sql .= " AND client_id=?"; $params[] = $clientId; }
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    if ($row) {
        $row['result'] = json_decode($row['result'], true);
        $row['platforms'] = json_decode($row['platforms'], true);
    }
    return $row;
}

function deleteRecord($type, $id, $clientId = null) {
    $table = ($type === 'audit') ? 'audits' : (($type === 'visibility') ? 'visibility' : null);
    if (!$table) return false;
    $sql = "DELETE FROM $table WHERE id=?";
    $params = [$id];
    if ($clientId) { $sql .= " AND client_id=?"; $params[] = $clientId; }
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount() > 0;
}

function stats($clientId = null) {
    $pdo = db();
    $where = $clientId ? " WHERE client_id=" . $pdo->quote($clientId) : "";
    return [
        'audits' => (int)$pdo->query("SELECT COUNT(*) FROM audits$where")->fetchColumn(),
        'visibility' => (int)$pdo->query("SELECT COUNT(*) FROM visibility$where")->fetchColumn(),
        'avg_audit_score' => round((float)$pdo->query("SELECT AVG(score) FROM audits$where")->fetchColumn(), 1),
        'avg_vis_score' => round((float)$pdo->query("SELECT AVG(score) FROM visibility$where")->fetchColumn(), 1),
    ];
}

/** 趋势序列：按时间升序返回两类报告得分 */
function trendSeries($clientId = null, $limit = 40) {
    $pdo = db();
    $where = $clientId ? " WHERE client_id=" . $pdo->quote($clientId) : "";
    $lim = max(5, min(200, (int)$limit));
    $a = $pdo->query("SELECT id, domain AS label, score, created_at FROM audits$where ORDER BY created_at ASC, id ASC LIMIT $lim")->fetchAll();
    $v = $pdo->query("SELECT id, brand AS label, score, created_at FROM visibility$where ORDER BY created_at ASC, id ASC LIMIT $lim")->fetchAll();
    return ['audit' => $a, 'visibility' => $v];
}

/** 确保默认管理员存在（首次部署） */
function ensureDefaultAdmin($username, $password, $name = '系统管理员') {
    if (getClientByUsername($username)) return false;
    $pdo = db();
    $st = $pdo->prepare("INSERT INTO clients (id,username,password_hash,name,role,company,quota,used,enabled,created_at) VALUES (?,?,?,?, 'admin','',0,0,1,?)");
    $st->execute([uid('C'), $username, password_hash($password, PASSWORD_DEFAULT), $name, date('Y-m-d H:i:s')]);
    return true;
}

/** 把无归属的旧报告归到指定客户 */
function assignOrphanRecords($clientId) {
    $pdo = db();
    $pdo->prepare("UPDATE audits SET client_id=? WHERE client_id IS NULL OR client_id=''")->execute([$clientId]);
    $pdo->prepare("UPDATE visibility SET client_id=? WHERE client_id IS NULL OR client_id=''")->execute([$clientId]);
}

// ==================== 白标报告 / 分享 ====================

function reportTable($type) {
    return $type === 'audit' ? 'audits' : ($type === 'visibility' ? 'visibility' : null);
}

/** 获取（或轮换）报告分享令牌；rotate 时同步刷新有效期 */
function getReportShareToken($type, $id, $rotate = false) {
    $table = reportTable($type);
    if (!$table || !$id) return null;
    $pdo = db();
    $st = $pdo->prepare("SELECT share_token FROM $table WHERE id=?");
    $st->execute([$id]);
    $token = $st->fetchColumn();
    if (!$token || $rotate) {
        $token = uid('S');
        $pdo->prepare("UPDATE $table SET share_token=?, share_expires=? WHERE id=?")
            ->execute([$token, shareExpiry(), $id]);
    }
    return $token;
}

/** 报告有效期信息（不改变令牌） */
function reportShareExpiry($type, $id) {
    $table = reportTable($type);
    if (!$table || !$id) return null;
    $st = db()->prepare("SELECT share_expires FROM $table WHERE id=?");
    $st->execute([$id]);
    return $st->fetchColumn();
}

/** 凭令牌读取公开报告（含客户品牌配置），过期链接视为无效 */
function getSharedReport($type, $id, $token) {
    $table = reportTable($type);
    if (!$table || !$id || !$token) return null;
    $st = db()->prepare("SELECT * FROM $table WHERE id=? AND share_token=?");
    $st->execute([$id, $token]);
    $row = $st->fetch();
    if (!$row) return null;
    if (!empty($row['share_expires']) && strtotime($row['share_expires']) < time()) return null;
    $row['client'] = $row['client_id'] ? getClientById($row['client_id']) : null;
    if ($type === 'audit') $row['result'] = json_decode($row['result'], true);
    else {
        $row['result'] = json_decode($row['result'], true);
        $row['platforms'] = json_decode($row['platforms'], true);
    }
    return $row;
}

/** 更新客户报告品牌配置 */
function updateClientBrand($id, $data) {
    $map = [
        'report_brand'   => 'report_brand',
        'report_logo'    => 'report_logo',
        'report_color'   => 'report_color',
        'report_tagline' => 'report_tagline',
        'report_contact' => 'report_contact',
        'report_footer'  => 'report_footer',
    ];
    $fields = []; $vals = [];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $data)) {
            $fields[] = "$col=?";
            $vals[] = trim((string)$data[$key]);
        }
    }
    if (!$fields) return false;
    $vals[] = $id;
    return db()->prepare("UPDATE clients SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
}

/** 报告品牌默认值 */
function brandDefaults($client = null) {
    $client = $client ?: [];
    $fallbackName = !empty($client['company']) ? $client['company']
        : (!empty($client['name']) ? $client['name'] : platformName());
    $val = function($key, $default) use ($client) {
        return (isset($client[$key]) && trim((string)$client[$key]) !== '') ? $client[$key] : $default;
    };
    return [
        'report_brand'   => $val('report_brand', $fallbackName),
        'report_logo'    => $val('report_logo', ''),
        'report_color'   => $val('report_color', '#2563EB'),
        'report_tagline' => $val('report_tagline', 'AI 可见度与官网 GEO 诊断报告'),
        'report_contact' => $val('report_contact', $client['phone'] ?? ''),
        'report_footer'  => $val('report_footer', '本报告由 ' . platformName() . ' 生成，仅供优化参考。'),
    ];
}

// ==================== 平台设置 ====================

/** 读取单个设置项，未设置时返回默认值 */
function getSetting($key, $default = null) {
    $st = db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

/** 写入单个设置项 */
function setSetting($key, $value) {
    $st = db()->prepare("INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    return $st->execute([$key, (string)$value]);
}

/** 读取多个设置项 */
function getSettings($defaults = []) {
    $out = [];
    foreach ($defaults as $k => $v) $out[$k] = getSetting($k, $v);
    return $out;
}

/** 平台 / 产品名称（后台「平台配置」可修改，未设置时取 config.php 常量） */
function platformName() {
    $v = trim(strip_tags((string)getSetting('platform_name', APP_NAME)));
    return $v !== '' ? $v : APP_NAME;
}

/** 平台副标题（Logo 下方说明、登录页辅助文案） */
function platformTagline() {
    $default = '官网 GEO 体检 · 品牌可见度分析';
    $v = trim(strip_tags((string)getSetting('platform_tagline', $default)));
    return $v !== '' ? $v : $default;
}

/** 新用户注册免费额度（默认取 config.php 常量） */
function freeQuota() {
    $v = getSetting('free_quota', FREE_QUOTA);
    return max(0, (int)$v);
}

/** 是否开放自助注册 */
function registerEnabled() {
    $v = getSetting('register_enabled', ALLOW_REGISTER ? '1' : '0');
    return in_array((string)$v, ['1', 'true', 'yes', 'on'], true);
}

// ==================== 邀请裂变（邀请注册送额度）====================

/** 生成 6 位随机邀请码（大写字母+数字，去易混淆字符） */
function inviteCode($seed = '') {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $st = db()->prepare("SELECT COUNT(*) FROM clients WHERE invite_code=?");
        $st->execute([$code]);
    } while ((int)$st->fetchColumn() > 0);
    return $code;
}

/** 邀请规则配置 */
function inviteSettings() {
    $s = getSettings([
        'invite_enabled' => '1',
        'invite_reward'  => '2',   // 每邀请 1 人，邀请人获得的诊断次数
        'invitee_bonus'  => '0',   // 通过邀请注册的新用户额外获得的次数（0=不加）
        'invite_max'     => '50',  // 单个用户通过邀请最多可获得的奖励次数（防刷）
    ]);
    return [
        'enabled'       => in_array((string)$s['invite_enabled'], ['1','true','yes','on'], true),
        'reward'        => max(0, (int)$s['invite_reward']),
        'invitee_bonus' => max(0, (int)$s['invitee_bonus']),
        'max'           => max(0, (int)$s['invite_max']),
    ];
}

/** 获取（或惰性生成）客户邀请码 */
function getInviteCode($clientId) {
    $c = getClientById($clientId);
    if (!$c) return null;
    if (!empty($c['invite_code'])) return $c['invite_code'];
    $code = inviteCode();
    db()->prepare("UPDATE clients SET invite_code=? WHERE id=?")->execute([$code, $clientId]);
    return $code;
}

/** 邀请统计 */
function inviteStats($clientId) {
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE invited_by=? AND role!='admin'");
    $st->execute([$clientId]);
    $invited = (int)$st->fetchColumn();
    $c = getClientById($clientId);
    return [
        'invited'  => $invited,
        'earned'   => (int)($c['invite_earned'] ?? 0),
        'code'     => getInviteCode($clientId),
    ];
}

/**
 * 邀请奖励发放：新用户注册成功后调用
 * 返回 ['ok'=>bool, 'message'=>string, 'inviter_id'=>?, 'reward'=>int, 'bonus'=>int]
 */
function applyInviteReward($refCode, $newClientId) {
    $cfg = inviteSettings();
    if (!$cfg['enabled'] || $cfg['reward'] <= 0) {
        return ['ok' => false, 'message' => '邀请奖励未开启'];
    }
    $refCode = strtoupper(trim((string)$refCode));
    if (!preg_match('/^[A-Z0-9]{4,12}$/', $refCode)) {
        return ['ok' => false, 'message' => '邀请码格式无效'];
    }
    $st = db()->prepare("SELECT * FROM clients WHERE invite_code=? AND enabled=1");
    $st->execute([$refCode]);
    $inviter = $st->fetch();
    if (!$inviter) return ['ok' => false, 'message' => '邀请码无效'];
    if ($inviter['id'] === $newClientId) return ['ok' => false, 'message' => '不能邀请自己'];

    $reward = min($cfg['reward'], max(0, $cfg['max'] - (int)$inviter['invite_earned']));
    if ($reward > 0) {
        // 邀请人：额度 + 流水
        credit_change($inviter['id'], $reward, 'invite', '邀请用户 ' . $newClientId . ' 注册');
        db()->prepare("UPDATE clients SET invite_earned = invite_earned + ? WHERE id=?")
            ->execute([$reward, $inviter['id']]);
    }
    // 新用户：记录归属 + 可选的额外奖励（同样记流水）
    $bonus = $cfg['invitee_bonus'];
    db()->prepare("UPDATE clients SET invited_by=? WHERE id=?")->execute([$inviter['id'], $newClientId]);
    if ($bonus > 0) credit_change($newClientId, $bonus, 'invite_bonus', '受邀注册额外奖励');
    return [
        'ok' => true,
        'message' => $reward > 0 ? "邀请成功，{$inviter['name']} 获得 {$reward} 次奖励" : '邀请成功（奖励已达上限）',
        'inviter_id' => $inviter['id'],
        'reward' => $reward,
        'bonus' => $bonus,
    ];
}

// ==================== 分享令牌有效期 ====================

/** 新报告链接有效期（天）；0 = 永不过期 */
function shareTtlDays() {
    return max(0, min(3650, (int)getSetting('share_ttl_days', 30)));
}

/** 生成到期时间，0 天时返回 NULL（永不过期） */
function shareExpiry() {
    $days = shareTtlDays();
    return $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
}

// ==================== 初始管理员（消除硬编码弱密码）====================

/**
 * 仅在「整个系统没有任何用户」时创建管理员，密码随机生成并落盘到
 * data/initial_admin.txt（600 权限）。已有任何用户时绝不改动现有账号。
 */
function ensureInitialAdmin() {
    $pdo = db();
    if ((int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn() > 0) return false;
    $pwd = 'Ga!' . bin2hex(random_bytes(7)) . '9Zq';
    $st = $pdo->prepare("INSERT INTO clients (id,username,password_hash,name,role,company,quota,used,enabled,created_at)
        VALUES (?,?,?,?,'admin','',0,0,1,?)");
    $st->execute([uid('C'), 'admin', password_hash($pwd, PASSWORD_DEFAULT), '系统管理员', date('Y-m-d H:i:s')]);
    $file = DATA_DIR . '/initial_admin.txt';
    @file_put_contents($file, platformName() . " · 初始管理员\n账号：admin\n密码：{$pwd}\n生成时间：" . date('Y-m-d H:i:s') . "\n请登录后立即在「个人中心」修改密码，并删除本文件。\n", LOCK_EX);
    @chmod($file, 0600);
    return true;
}

// ==================== 额度流水 ====================

/** 变更额度并记流水（amount 可正可负） */
function credit_change($clientId, $amount, $kind, $remark = '') {
    $pdo = db();
    $amount = (int)$amount;
    $cur = getClientById($clientId);
    $unlimited = $cur && (int)$cur['quota'] < 0; // -1 = 不限额度，不参与增减
    if (!$unlimited) {
        $pdo->prepare("UPDATE clients SET quota = MAX(0, quota + ?) WHERE id=?")->execute([$amount, $clientId]);
    }
    $bal = $unlimited ? -1
        : (int)$pdo->query("SELECT quota FROM clients WHERE id=" . $pdo->quote($clientId))->fetchColumn();
    $st = $pdo->prepare("INSERT INTO credits (client_id,amount,balance_after,kind,remark,created_at) VALUES (?,?,?,?,?,?)");
    $st->execute([$clientId, $amount, $bal, $kind, mb_substr((string)$remark, 0, 200), date('Y-m-d H:i:s')]);
    return $bal;
}

function listCredits($clientId, $limit = 50) {
    $st = db()->prepare("SELECT * FROM credits WHERE client_id=? ORDER BY id DESC LIMIT ?");
    $st->execute([$clientId, (int)$limit]);
    return $st->fetchAll();
}

/** 消耗一次额度（used+1）并记流水，balance_after 为剩余可用次数 */
function credit_consume($clientId, $kind = 'diagnose', $remark = '') {
    $pdo = db();
    $pdo->prepare("UPDATE clients SET used = used + 1 WHERE id=?")->execute([$clientId]);
    $c = getClientById($clientId);
    $quota = (int)($c['quota'] ?? 0);
    $balance = $quota < 0 ? -1 : max(0, $quota - (int)($c['used'] ?? 0));
    $st = $pdo->prepare("INSERT INTO credits (client_id,amount,balance_after,kind,remark,created_at) VALUES (?,?,?,?,?,?)");
    $st->execute([$clientId, -1, $balance, $kind, mb_substr((string)$remark, 0, 200), date('Y-m-d H:i:s')]);
    return $balance;
}

// ==================== 兑换码 / 额度包 ====================

function createRedeemCodes($amount, $count, $batch = '') {
    $pdo = db();
    $out = [];
    $st = $pdo->prepare("INSERT INTO redeem_codes (code,amount,batch,created_at) VALUES (?,?,?,?)");
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(bin2hex(random_bytes(5))); // 10 位
        $st->execute([$code, (int)$amount, $batch, date('Y-m-d H:i:s')]);
        $out[] = $code;
    }
    return $out;
}

function listRedeemCodes($limit = 200) {
    return db()->query("SELECT r.*, c.name AS used_name FROM redeem_codes r LEFT JOIN clients c ON r.used_by=c.id ORDER BY r.created_at DESC, r.code DESC LIMIT " . (int)$limit)->fetchAll();
}

/** 兑换（原子：先占用再发放） */
function redeemCode($code, $clientId) {
    $pdo = db();
    $code = strtoupper(trim((string)$code));
    $st = $pdo->prepare("SELECT * FROM redeem_codes WHERE code=?");
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) return ['ok' => false, 'message' => '兑换码无效'];
    if ($row['used_by']) return ['ok' => false, 'message' => '该兑换码已被使用'];
    $upd = $pdo->prepare("UPDATE redeem_codes SET used_by=?, used_at=? WHERE code=? AND (used_by IS NULL OR used_by='')");
    $upd->execute([$clientId, date('Y-m-d H:i:s'), $code]);
    if ($upd->rowCount() === 0) return ['ok' => false, 'message' => '该兑换码已被使用'];
    $balance = credit_change($clientId, (int)$row['amount'], 'redeem', "兑换码 {$code}");
    return ['ok' => true, 'amount' => (int)$row['amount'], 'balance' => $balance, 'code' => $code];
}

// ==================== 模型 API Key（后台可编辑）====================

function keysFile() { return DATA_DIR . '/keys.json'; }

function readKeys() {
    $f = keysFile();
    if (!file_exists($f)) return [];
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function writeKeys($keys, $remove = []) {
    $allowed = array_keys(platformMeta());
    $out = readKeys(); // 已配置的 Key 默认保留
    foreach ((array)$remove as $k) {
        if (in_array($k, $allowed, true)) unset($out[$k]);
    }
    foreach ($allowed as $k) {
        $v = trim((string)($keys[$k] ?? ''));
        if ($v !== '') $out[$k] = $v;
    }
    $f = keysFile();
    if (file_put_contents($f, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return ['ok' => false, 'message' => '写入失败，请检查 data 目录权限'];
    }
    @chmod($f, 0600);
    return ['ok' => true, 'keys' => $out];
}
