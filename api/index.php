<?php
/**
 * API 路由入口（含鉴权 + 多客户管理）
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/audit_engine.php';
require_once __DIR__ . '/visibility_engine.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/** 清洗客户报告品牌配置 */
function sanitize_brand_input($in) {
    $clean = function ($s, $len = 200) {
        return mb_substr(trim(strip_tags((string)$s)), 0, $len);
    };
    $logo = trim((string)($in['report_logo'] ?? ''));
    if ($logo !== '' && !preg_match('#^https?://#i', $logo)) $logo = '';
    $color = trim((string)($in['report_color'] ?? ''));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#2563EB';
    return [
        'report_brand'   => $clean($in['report_brand'] ?? '', 60),
        'report_logo'    => mb_substr($logo, 0, 500),
        'report_color'   => $color,
        'report_tagline' => $clean($in['report_tagline'] ?? '', 120),
        'report_contact' => $clean($in['report_contact'] ?? '', 200),
        'report_footer'  => $clean($in['report_footer'] ?? '', 200),
    ];
}

// 首次部署：仅在空库时创建随机密码管理员（密码落盘 data/initial_admin.txt）
ensureInitialAdmin();
$rl = rateLimits();

switch ($action) {
    // ==================== 认证 ====================
    case 'login':
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $ip = client_ip();
        $uname = trim((string)($in['username'] ?? ''));
        rate_guard('login', $ip, $rl['login'], 600, '登录尝试过于频繁');
        [$freeLock, $lockSec] = login_failure_check($uname, $ip);
        if (!$freeLock) json_out(['error' => '账号已临时锁定，请 ' . ceil($lockSec / 60) . ' 分钟后重试'], 429);
        $r = do_login($uname, (string)($in['password'] ?? ''));
        if (!$r['ok']) {
            login_failure_record($uname, $ip);
            log_event('login_fail', '账号 ' . $uname . ' 登录失败');
        } else {
            login_failure_clear($uname, $ip);
            log_event('login', '账号 ' . $uname . ' 登录成功', $r['user']['id'] ?? null);
        }
        json_out($r, $r['ok'] ? 200 : 401);
        break;

    case 'logout':
        json_out(do_logout());
        break;

    case 'me':
        if (!is_logged_in()) json_out(['user' => null]);
        json_out(['user' => publicUser(current_user())]);
        break;

    case 'public_config':
        require_once __DIR__ . '/seo_sync.php';
        $inv = inviteSettings();
        json_out([
            'app_name' => platformName(),
            'platform_tagline' => platformTagline(),
            'site_url' => seoBase(),
            'version' => APP_VERSION,
            'free_quota' => freeQuota(),
            'register_enabled' => registerEnabled(),
            'invite' => [
                'enabled' => $inv['enabled'] && registerEnabled(),
                'reward' => $inv['reward'],
                'invitee_bonus' => $inv['invitee_bonus'],
            ],
            'site_icp' => (string)getSetting('site_icp', ''),
        ]);
        break;

    case 'register':
        if (!registerEnabled()) json_out(['error' => 'register_closed', 'message' => '当前未开放自助注册'], 403);
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        rate_guard('register', client_ip(), $rl['register'], 3600, '注册操作过于频繁');
        // 蜜罐字段：正常用户不会填写
        if (!empty($in['website'])) json_out(['error' => '注册失败，请稍后重试'], 400);
        $username = trim((string)($in['username'] ?? ''));
        $password = (string)($in['password'] ?? '');
        $name = trim(strip_tags((string)($in['name'] ?? '')));
        $company = trim(strip_tags((string)($in['company'] ?? '')));
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) json_out(['error' => '账号需为 3-30 位字母、数字或下划线'], 400);
        if (strlen($password) < 8) json_out(['error' => '密码至少 8 位'], 400);
        if (mb_strlen($name) > 40) $name = mb_substr($name, 0, 40);
        if (mb_strlen($company) > 80) $company = mb_substr($company, 0, 80);
        if (getClientByUsername($username)) json_out(['error' => '该账号已被注册'], 400);
        $id = createClient([
            'username' => $username,
            'password' => $password,
            'name' => $name ?: $username,
            'company' => $company,
            'role' => 'customer',
            'quota' => 0,
        ]);
        // 免费额度通过流水发放（quota=0 现在正确表示「无额度」，-1 才是不限）
        if (freeQuota() > 0) {
            credit_change($id, freeQuota(), 'register', '新用户注册赠送');
        } else {
            log_event('register', '账号 ' . $username . ' 注册（无赠送额度）', $id);
        }
        // 邀请注册奖励（GrowMind 式裂变：邀请人 + 受邀人双方奖励）
        $inviteResult = null;
        $ref = trim((string)($in['ref'] ?? ''));
        if ($ref !== '') {
            $inviteResult = applyInviteReward($ref, $id);
        }
        $login = do_login($username, $password);
        json_out([
            'ok' => true,
            'id' => $id,
            'free_quota' => freeQuota(),
            'invite' => $inviteResult,
            'user' => $login['ok'] ? $login['user'] : null,
        ]);
        break;

    // ==================== 邀请有礼 ====================
    case 'invite_info':
        require_login();
        $cid = current_user()['id'];
        $cfg = inviteSettings();
        $stats = inviteStats($cid);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        json_out([
            'ok' => true,
            'invite' => [
                'code'     => $stats['code'],
                'url'      => $scheme . '://' . $host . '/?ref=' . $stats['code'],
                'invited'  => $stats['invited'],
                'earned'   => $stats['earned'],
                'enabled'  => $cfg['enabled'],
                'reward'   => $cfg['reward'],
                'invitee_bonus' => $cfg['invitee_bonus'],
                'max'      => $cfg['max'],
            ],
        ]);
        break;

    // ==================== 客户管理（管理员）====================
    case 'clients':
        require_admin();
        $list = array_map('publicUser', listClients());
        json_out(['clients' => $list]);
        break;

    case 'client_create':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        if (getClientByUsername(trim($in['username'] ?? ''))) json_out(['error' => '账号已存在'], 400);
        if (empty($in['password']) || strlen($in['password']) < 6) json_out(['error' => '密码至少 6 位'], 400);
        $id = createClient($in);
        if (array_intersect_key($in, array_flip(['report_brand','report_logo','report_color','report_tagline','report_contact','report_footer']))) {
            updateClientBrand($id, sanitize_brand_input($in));
        }
        json_out(['ok' => true, 'id' => $id, 'client' => publicUser(getClientById($id))]);
        break;

    case 'client_update':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        if (empty($in['id'])) json_out(['error' => '缺少 id'], 400);
        // 不允许把自己降级或禁用
        if ($in['id'] === current_user()['id']) {
            unset($in['role'], $in['enabled']);
        }
        updateClient($in['id'], $in);
        if (array_intersect_key($in, array_flip(['report_brand','report_logo','report_color','report_tagline','report_contact','report_footer']))) {
            updateClientBrand($in['id'], sanitize_brand_input($in));
        }
        json_out(['ok' => true, 'client' => publicUser(getClientById($in['id']))]);
        break;

    case 'client_delete':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $r = deleteClient($in['id'] ?? '');
        json_out($r, $r['ok'] ? 200 : 400);
        break;

    case 'client_reset_usage':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        resetUsage($in['id'] ?? '');
        json_out(['ok' => true]);
        break;

    // ==================== 诊断（需登录 + 配额）====================
    case 'audit':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        rate_guard('diagnose', client_ip(), $rl['diagnose'], 3600, '诊断请求过于频繁');
        @set_time_limit(120);
        $cid = scope_client_id($in['client_id'] ?? null);
        if (!quota_available($cid)) json_out(['error' => 'quota', 'message' => '诊断额度已用尽，请联系管理员或兑换额度包'], 403);
        if (empty($in['url'])) json_out(['error' => '请输入网址'], 400);
        $result = runAudit($in['url']);
        if (isset($result['error'])) json_out($result, 400);
        $id = uid('A');
        saveAudit($id, $cid, $result['url'], $result['domain'], $result['score'], $result);
        if (!is_admin()) consume_quota($cid);
        log_event('audit', '官网诊断 ' . $result['domain'] . ' 得分 ' . $result['score'], $cid);
        json_out(['ok' => true, 'id' => $id, 'result' => $result]);
        break;

    case 'visibility':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        rate_guard('diagnose', client_ip(), $rl['diagnose'], 3600, '分析请求过于频繁');
        @set_time_limit(120); // live 多平台并发采样需要更长执行窗口
        $cid = scope_client_id($in['client_id'] ?? null);
        if (!quota_available($cid)) json_out(['error' => 'quota', 'message' => '诊断额度已用尽，请联系管理员'], 403);
        $brand = $in['brand'] ?? '';
        $industry = $in['industry'] ?? '';
        $question = $in['question'] ?? '';
        $platforms = $in['platforms'] ?? ['deepseek', 'doubao', 'qwen'];
        $mode = $in['mode'] ?? 'demo';
        if ($mode === 'live') {
            $hasAny = false;
            foreach ($platforms as $p) if (hasKey($p)) $hasAny = true;
            if (!$hasAny) $mode = 'demo';
        }
        $result = runVisibility($brand, $industry, $question, $platforms, $mode);
        if (isset($result['error'])) json_out($result, 400);
        $id = uid('V');
        saveVisibility($id, $cid, $brand, $industry, $question, $platforms, $mode, $result['score'], $result);
        if (!is_admin()) consume_quota($cid);
        log_event('visibility', '可见度分析 ' . $brand . ' / ' . $industry . '（' . $mode . '）', $cid);
        json_out(['ok' => true, 'id' => $id, 'result' => $result]);
        break;

    // ==================== 报告查询（需登录 + 隔离）====================
    case 'list':
        require_login();
        $type = $_GET['type'] ?? 'audit';
        $adminAll = is_admin() && !empty($_GET['all']);
        $cid = $adminAll ? null : scope_client_id();
        json_out(['items' => $type === 'audit' ? listAudits($cid) : listVisibility($cid)]);
        break;

    case 'get':
        require_login();
        $type = $_GET['type'] ?? 'audit';
        $id = $_GET['id'] ?? '';
        // 管理员可传 client_id 访问特定客户报告，否则只能访问自己的
        $cid = is_admin() && isset($_GET['client_id']) ? $_GET['client_id'] : scope_client_id();
        $row = $type === 'audit' ? getAudit($id, $cid) : getVisibility($id, $cid);
        if (!$row) json_out(['error' => 'not found'], 404);
        json_out($row);
        break;

    case 'delete':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $cid = is_admin() && !empty($in['all']) ? null : scope_client_id();
        json_out(['ok' => deleteRecord($in['type'] ?? '', $in['id'] ?? '', $cid)]);
        break;

    case 'stats':
        require_login();
        $adminAll = is_admin() && !empty($_GET['all']);
        $cid = $adminAll ? null : scope_client_id();
        json_out(stats($cid));
        break;

    // ==================== 辅助 ====================
    case 'questions':
        require_login();
        json_out(['questions' => generateQuestions($_GET['industry'] ?? '', '')]);
        break;

    case 'platforms':
        require_login();
        $out = [];
        foreach (platformMeta() as $k => $m) $out[] = ['id'=>$k,'name'=>$m['name'],'configured'=>hasKey($k),'model'=>$m['model']];
        json_out(['platforms' => $out, 'demo_available' => true]);
        break;

    case 'keys':
        require_admin();
        $out = [];
        foreach (platformMeta() as $k => $m) $out[] = ['id'=>$k,'name'=>$m['name'],'env'=>$m['env'],'configured'=>hasKey($k)];
        json_out(['keys' => $out]);
        break;

    case 'settings_get':
        require_admin();
        require_once __DIR__ . '/seo_sync.php';
        $inv = inviteSettings();
        $live = liveSettings();
        json_out([
            'settings' => [
                'platform_name' => platformName(),
                'platform_tagline' => platformTagline(),
                'site_url' => seoBase(),
                'free_quota' => freeQuota(),
                'register_enabled' => registerEnabled(),
                'invite_enabled' => $inv['enabled'],
                'invite_reward' => $inv['reward'],
                'invitee_bonus' => $inv['invitee_bonus'],
                'invite_max' => $inv['max'],
                'share_ttl_days' => shareTtlDays(),
                'live_question_limit' => $live['question_limit'],
                'live_max_samples' => $live['max_samples'],
                'live_timeout' => $live['timeout'],
                'rl_login' => $rl['login'],
                'rl_register' => $rl['register'],
                'rl_diagnose' => $rl['diagnose'],
                'site_icp' => (string)getSetting('site_icp', ''),
            ],
        ]);
        break;

    case 'settings_update':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        if (array_key_exists('free_quota', $in)) {
            setSetting('free_quota', max(0, min(1000, (int)$in['free_quota'])));
        }
        if (array_key_exists('register_enabled', $in)) {
            setSetting('register_enabled', !empty($in['register_enabled']) ? '1' : '0');
        }
        if (array_key_exists('invite_enabled', $in)) {
            setSetting('invite_enabled', !empty($in['invite_enabled']) ? '1' : '0');
        }
        if (array_key_exists('invite_reward', $in)) {
            setSetting('invite_reward', max(0, min(100, (int)$in['invite_reward'])));
        }
        if (array_key_exists('invitee_bonus', $in)) {
            setSetting('invitee_bonus', max(0, min(100, (int)$in['invitee_bonus'])));
        }
        if (array_key_exists('invite_max', $in)) {
            setSetting('invite_max', max(0, min(10000, (int)$in['invite_max'])));
        }
        foreach (['share_ttl_days' => 3650, 'live_question_limit' => 15, 'live_max_samples' => 100,
                  'live_timeout' => 45, 'rl_login' => 100, 'rl_register' => 100, 'rl_diagnose' => 1000] as $k => $cap) {
            if (array_key_exists($k, $in)) setSetting($k, max(0, min($cap, (int)$in[$k])));
        }
        if (array_key_exists('platform_name', $in)) {
            setSetting('platform_name', mb_substr(trim(strip_tags((string)$in['platform_name'])), 0, 40));
        }
        if (array_key_exists('platform_tagline', $in)) {
            setSetting('platform_tagline', mb_substr(trim(strip_tags((string)$in['platform_tagline'])), 0, 100));
        }
        if (array_key_exists('site_icp', $in)) {
            setSetting('site_icp', mb_substr(trim(strip_tags((string)$in['site_icp'])), 0, 100));
        }
        // 站点规范地址是 robots/sitemap/llms.txt/canonical/JSON-LD 的唯一数据源
        require_once __DIR__ . '/seo_generate.php';
        if (array_key_exists('site_url', $in)) {
            $norm = siteUrlValidate((string)$in['site_url']);
            if ($norm === null) {
                json_out(['error' => '站点地址格式不正确：请填写形如 https://example.com/ 的根地址（不含路径、参数）'], 400);
            }
            setSetting('site_url', $norm);
        }
        // 保存后全量重建：落地页 head + 4 个 SEO 文本文件 + 5 个知识页
        $seoSync = syncAllSeo();
        $seoMsg  = $seoSync['ok']
            ? ('OK · ' . count($seoSync['files']) . ' 个文件 · ' . $seoSync['site_url'])
            : ('失败 · ' . implode('；', $seoSync['errors']));
        log_event('settings_update', '更新平台配置（SEO/GEO 全量同步: ' . $seoMsg . '）');
        $inv = inviteSettings(); $live = liveSettings(); $rl = rateLimits();
        json_out([
            'ok' => true,
            'seo_sync' => $seoSync,
            'settings' => [
                'platform_name' => platformName(),
                'platform_tagline' => platformTagline(),
                'site_url' => seoBase(),
                'free_quota' => freeQuota(),
                'register_enabled' => registerEnabled(),
                'invite_enabled' => $inv['enabled'],
                'invite_reward' => $inv['reward'],
                'invitee_bonus' => $inv['invitee_bonus'],
                'invite_max' => $inv['max'],
                'share_ttl_days' => shareTtlDays(),
                'live_question_limit' => $live['question_limit'],
                'live_max_samples' => $live['max_samples'],
                'live_timeout' => $live['timeout'],
                'rl_login' => $rl['login'],
                'rl_register' => $rl['register'],
                'rl_diagnose' => $rl['diagnose'],
                'site_icp' => (string)getSetting('site_icp', ''),
            ],
        ]);
        break;

    case 'seo_rebuild':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        require_once __DIR__ . '/seo_generate.php';
        $r = syncAllSeo();
        log_event('seo_rebuild', '手动重建 SEO/GEO 文件：' . ($r['ok']
            ? ('OK · ' . count($r['files']) . ' 个文件 · ' . $r['site_url'])
            : ('失败 · ' . implode('；', $r['errors']))));
        json_out(['ok' => $r['ok'], 'sync' => $r]);
        break;

    // ==================== 白标报告 / 分享 ====================
    case 'brand_get':
        require_login();
        $cid = scope_client_id($_GET['client_id'] ?? null);
        $client = getClientById($cid);
        if (!$client) json_out(['error' => '客户不存在'], 404);
        json_out(['brand' => brandDefaults($client), 'client' => ['id'=>$cid,'name'=>$client['name'],'company'=>$client['company']]]);
        break;

    case 'brand_update':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $cid = scope_client_id($in['client_id'] ?? null);
        updateClientBrand($cid, sanitize_brand_input($in));
        json_out(['ok' => true, 'brand' => brandDefaults(getClientById($cid))]);
        break;

    case 'report_share':
        require_login();
        $type = $_GET['type'] ?? 'audit';
        $id = $_GET['id'] ?? '';
        $cid = scope_client_id($_GET['client_id'] ?? null);
        $row = $type === 'audit' ? getAudit($id, $cid) : getVisibility($id, $cid);
        if (!$row) json_out(['error' => '报告不存在'], 404);
        // 轮换令牌 / 续期均只允许 POST，避免 GET 改状态被跨站触发
        $rotate = ($method === 'POST') && !empty($in['rotate']);
        $renew  = ($method === 'POST') && !empty($in['renew']);
        if ($renew) {
            $tbl = reportTable($type);
            db()->prepare("UPDATE $tbl SET share_expires=? WHERE id=?")->execute([shareExpiry(), $id]);
        }
        $token = getReportShareToken($type, $id, $rotate);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
        $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/report.php?type=' . rawurlencode($type) . '&id=' . rawurlencode($id) . '&token=' . rawurlencode($token);
        json_out([
            'ok' => true,
            'url' => $url,
            'token' => $token,
            'expires' => reportShareExpiry($type, $id),
            'ttl_days' => shareTtlDays(),
        ]);
        break;

    // ==================== 趋势 / 个人中心 / 额度流水 ====================
    case 'trend':
        require_login();
        $cid = is_admin() ? ($_GET['client_id'] ?? null) : current_user()['id'];
        json_out(['trend' => trendSeries($cid, (int)($_GET['limit'] ?? 40))]);
        break;

    case 'profile_update':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $cid = current_user()['id'];
        $data = [];
        foreach (['name', 'company', 'phone'] as $f) {
            if (array_key_exists($f, $in)) $data[$f] = mb_substr(trim(strip_tags((string)$in[$f])), 0, 80);
        }
        if (!$data) json_out(['error' => '没有可更新的字段'], 400);
        updateClient($cid, $data);
        log_event('profile_update', '更新个人资料', $cid);
        json_out(['ok' => true, 'user' => publicUser(getClientById($cid))]);
        break;

    case 'change_password':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $u = current_user();
        if (!password_verify((string)($in['old_password'] ?? ''), $u['password_hash'])) {
            json_out(['error' => '原密码不正确'], 400);
        }
        $new = (string)($in['new_password'] ?? '');
        if (strlen($new) < 8) json_out(['error' => '新密码至少 8 位'], 400);
        updateClient($u['id'], ['password' => $new]);
        log_event('change_password', '修改登录密码', $u['id']);
        json_out(['ok' => true]);
        break;

    case 'credits':
        require_login();
        $cid = is_admin() && !empty($_GET['client_id']) ? $_GET['client_id'] : current_user()['id'];
        json_out(['items' => listCredits($cid, 100)]);
        break;

    case 'redeem':
        require_login();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        rate_guard('redeem', client_ip(), 20, 3600, '兑换操作过于频繁');
        $r = redeemCode($in['code'] ?? '', current_user()['id']);
        if (!$r['ok']) json_out(['error' => $r['message']], 400);
        log_event('redeem', '兑换码 ' . $r['code'] . ' +' . $r['amount'], current_user()['id']);
        json_out(['ok' => true, 'amount' => $r['amount'], 'balance' => $r['balance']]);
        break;

    // ==================== 管理员：兑换码 / Key / 日志 ====================
    case 'redeem_codes':
        require_admin();
        json_out(['items' => listRedeemCodes()]);
        break;

    case 'redeem_create':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $amount = max(1, min(10000, (int)($in['amount'] ?? 0)));
        $count = max(1, min(100, (int)($in['count'] ?? 0)));
        $batch = mb_substr(trim(strip_tags((string)($in['batch'] ?? ''))), 0, 60);
        $codes = createRedeemCodes($amount, $count, $batch);
        log_event('redeem_create', "生成 {$count} 个 {$amount} 次额度兑换码（批次：{$batch}）");
        json_out(['ok' => true, 'codes' => $codes]);
        break;

    case 'keys_get':
        require_admin();
        $keys = readKeys();
        $out = [];
        foreach (platformMeta() as $k => $m) {
            $v = $keys[$k] ?? '';
            $out[] = ['id' => $k, 'name' => $m['name'], 'env' => $m['env'], 'configured' => $v !== '',
                      'masked' => $v !== '' ? mb_substr($v, 0, 4) . '****' . mb_substr($v, -4) : ''];
        }
        json_out(['keys' => $out]);
        break;

    case 'keys_update':
        require_admin();
        if ($method !== 'POST') json_out(['error' => 'method'], 405);
        $r = writeKeys(is_array($in['keys'] ?? null) ? $in['keys'] : [],
                       is_array($in['remove'] ?? null) ? $in['remove'] : []);
        if (!$r['ok']) json_out(['error' => $r['message']], 500);
        log_event('keys_update', '更新模型 API Key');
        json_out(['ok' => true]);
        break;

    case 'logs':
        require_admin();
        json_out(['items' => listLogs(max(10, min(500, (int)($_GET['limit'] ?? 100))))]);
        break;

    default:
        json_out(['error' => 'unknown action'], 404);
}
