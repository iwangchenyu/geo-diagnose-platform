<?php
/**
 * 公共工具函数
 */
require_once __DIR__ . '/config.php';

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function uid($prefix = '') {
    return $prefix . date('ymdHis') . bin2hex(random_bytes(4));
}

/**
 * 严格 URL 校验（SSRF 防护）
 * - 仅 http/https、仅 80/443/默认端口
 * - 禁止 IP 字面量中的私有/保留段
 * - 域名解析后的所有 A 记录也必须非私有（防 DNS rebinding）
 */
function safe_url($url) {
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return null;
    $scheme = strtolower($parts['scheme'] ?? 'https');
    if (!in_array($scheme, ['http', 'https'], true)) return null;
    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
    if (!in_array((int)$port, [80, 443], true)) return null;

    $host = $parts['host'];
    // 逐条校验 IP（含域名解析结果）
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        if (preg_match('#(\.local|\.localhost|\.internal|\.lan)$#i', $host)
            || in_array(strtolower($host), ['localhost', 'metadata', 'metadata.google.internal'], true)) return null;
        $resolved = @gethostbynamel($host);
        if ($resolved === false) return null; // 解析失败直接拒绝
        $ips = $resolved;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    }
    // 规范化：保留 scheme/host/port/path/query，丢弃 fragment 与 userinfo
    $norm = $scheme . '://' . $host . (($port == ($scheme === 'https' ? 443 : 80)) ? '' : ':' . $port);
    $norm .= $parts['path'] ?? '/';
    if (!empty($parts['query'])) $norm .= '?' . $parts['query'];
    return $norm;
}

function host_of($url) {
    $h = parse_url($url, PHP_URL_HOST);
    return $h ? preg_replace('#^www\.#i', '', $h) : $url;
}

/**
 * 带 UA 的 HTTP 请求，返回 [status, headers, body, time, error]
 * 说明：审计目标站常见证书异常，此处不强制校验对端证书；
 *       调用官方大模型 API 请用 http_post_json（强制校验证书）。
 * 重定向采用手动逐跳处理，每一跳都重新做 safe_url 校验（防跳转到内网）。
 */
function http_fetch($url, $opts = []) {
    $ua = $opts['ua'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36';
    $timeout = $opts['timeout'] ?? 12;
    $follow = $opts['follow'] ?? true;
    $headersOpt = $opts['headers'] ?? [];
    $maxRedirects = $follow ? 5 : 0;

    $t0 = microtime(true);
    $current = $url;
    $last = null;
    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $safe = safe_url($current);
        if (!$safe) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'time' => round((microtime(true) - $t0) * 1000), 'error' => 'URL 被安全策略拒绝（内网地址或非法端口）'];
        }
        $last = http_fetch_once($safe, $ua, $timeout, $headersOpt);
        $last['time'] = round((microtime(true) - $t0) * 1000);
        if (in_array((int)$last['status'], [301, 302, 303, 307, 308], true) && !empty($last['headers']['location'])) {
            $loc = trim($last['headers']['location']);
            if (!preg_match('#^https?://#i', $loc)) {
                $b = parse_url($safe);
                $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
                $loc = $origin . (strpos($loc, '/') === 0 ? $loc : '/' . $loc);
            }
            $current = $loc;
            continue;
        }
        break;
    }
    return $last ?: ['status' => 0, 'headers' => [], 'body' => '', 'time' => 0, 'error' => '请求失败'];
}

/** 单次 HTTP 请求（不跟随重定向） */
function http_fetch_once($url, $ua, $timeout, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $t0 = microtime(true);
    $raw = curl_exec($ch);
    $elapsed = round((microtime(true) - $t0) * 1000);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'headers' => [], 'body' => '', 'time' => $elapsed, 'error' => $err];
    }
    $headerRaw = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $headersOut = [];
    foreach (explode("\r\n", $headerRaw) as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headersOut[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headersOut, 'body' => $body, 'time' => $elapsed, 'error' => $err];
}

/**
 * NFKC 风格的品牌名归一化（去空格/标点/小写）
 * 注意：PCRE2 不支持 \uXXXX，字符类里的反斜杠也易出错，故用 str_replace 处理标点
 */
function norm_text($s) {
    $s = (string)($s ?? '');
    $s = preg_replace('/[\x{3000}\x{00A0}\s]+/u', '', $s); // 全角空格 / 不换行空格 / 空白
    if ($s === null) $s = '';
    $s = str_replace([
        '\\', '/', '·', '•', '-', '—', '_', '（', '）', '(', ')',
        '【', '】', '[', ']', '「', '」', '《', '》', ',', '.', '。',
    ], '', $s);
    return mb_strtolower($s, 'UTF-8');
}

/**
 * 从 HTML 提取纯文本（去脚本样式）
 */
function html_to_text($html) {
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
    $html = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', ' ', $html);
    $html = strip_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/\s+/u', ' ', $html);
    return trim($html);
}
