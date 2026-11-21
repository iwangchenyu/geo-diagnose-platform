<?php
/**
 * 白标诊断报告页（凭分享令牌公开访问）
 * 按报告归属客户渲染独立品牌：Logo / 品牌名 / 主题色 / 联系方式 / 页脚
 */
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/lib.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/geo_plan.php';

$type  = $_GET['type'] ?? '';
$id    = $_GET['id'] ?? '';
$token = $_GET['token'] ?? '';
$report = getSharedReport($type, $id, $token);

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function arr($v) { return is_array($v) ? $v : []; }
function rcolor($s) {
    $s = (float)$s;
    if ($s >= 85) return '#1D9E75';
    if ($s >= 70) return '#378ADD';
    if ($s >= 55) return '#D97706';
    if ($s >= 40) return '#D85A30';
    return '#DC2626';
}
function rgrade($s) {
    $s = (float)$s;
    if ($s >= 85) return '优秀';
    if ($s >= 70) return '良好';
    if ($s >= 55) return '及格';
    if ($s >= 40) return '偏弱';
    return '待优化';
}
function checkIcon($s) { return $s === 'ok' ? '✓' : ($s === 'warn' ? '!' : '×'); }
function checkName($s) { return $s === 'ok' ? '通过' : ($s === 'warn' ? '待优化' : '问题'); }

if (!$report) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>报告不存在</title></head>
    <body style="font-family:system-ui,sans-serif;padding:60px;text-align:center;color:#334155">
      <h2>报告链接无效或已失效</h2><p>链接可能已过期、被刷新或不存在，请联系报告提供方获取最新链接。</p>
    </body></html><?php
    exit;
}

$client = $report['client'] ?: [];
$brand = brandDefaults($client);
$color = preg_match('/^#[0-9a-fA-F]{6}$/', $brand['report_color']) ? $brand['report_color'] : '#2563EB';
$logo = filter_var($brand['report_logo'], FILTER_VALIDATE_URL) ? $brand['report_logo'] : '';
$result = arr($report['result']);
$isAudit = $type === 'audit';
$title = $isAudit ? '官网 GEO 诊断报告' : '品牌 AI 可见度优化方案';
$subject = $isAudit ? ($result['domain'] ?? $report['domain'] ?? '') : ($result['brand'] ?? $report['brand'] ?? '');
$score = (float)($report['score'] ?? $result['score'] ?? 0);
$scoreText = $isAudit ? (string)round($score) : round($score, 1) . '%';
$grade = '';
if (!empty($result['grade']) && is_array($result['grade'])) $grade = $result['grade']['label'] ?? '';
if (!$grade) $grade = rgrade($score);
$generatedAt = $report['created_at'] ?? date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#070A12" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#F6F8FD" media="(prefers-color-scheme: light)">
<title><?= h($brand['report_brand']) ?> · <?= h($title) ?></title>
<script>(function(){try{var t=localStorage.getItem('geo-theme');if(t!=='dark'&&t!=='light')t='light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>
:root{
  --brand:<?= h($color) ?>;
  --font:"Inter","SF Pro Display",-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;
  --r-sm:10px;--r-md:14px;--r-lg:18px;--r-xl:24px;--r-pill:999px;
}
html[data-theme="light"]{
  --bg:#F1F5FB;--panel:#FFFFFF;--surface:#F6F9FE;--line:rgba(15,23,42,.09);--line-2:rgba(15,23,42,.16);
  --ink:#0C1424;--sub:#4A5872;--faint:#8792A8;
  --shadow:0 26px 70px -34px rgba(12,20,36,.36);--inner:inset 0 1px 0 rgba(255,255,255,.9);
  color-scheme:light;
}
html[data-theme="dark"]{
  --bg:#070A12;--panel:#0E1524;--surface:#121B2D;--line:rgba(255,255,255,.10);--line-2:rgba(255,255,255,.18);
  --ink:#EDF1FF;--sub:#A6B2CC;--faint:#71809C;
  --shadow:0 30px 80px -36px rgba(0,0,0,.85);--inner:inset 0 1px 0 rgba(255,255,255,.06);
  color-scheme:dark;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--font);font-size:14px;line-height:1.72;-webkit-font-smoothing:antialiased;font-variant-numeric:tabular-nums;transition:background .35s,color .35s}
.sprite{position:absolute;width:0;height:0;overflow:hidden}
.ico{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;display:block}
::selection{background:color-mix(in srgb,var(--brand) 26%,transparent)}
:focus-visible{outline:2px solid var(--brand);outline-offset:3px;border-radius:4px}

/* toolbar */
.report-toolbar{position:sticky;top:0;z-index:10;background:rgba(9,13,24,.86);-webkit-backdrop-filter:blur(20px) saturate(170%);backdrop-filter:blur(20px) saturate(170%);color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;gap:14px;border-bottom:1px solid rgba(255,255,255,.10)}
.rt-left{display:flex;align-items:center;gap:11px;min-width:0}
.rt-mark{width:26px;height:26px;border-radius:8px;background:var(--brand);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;letter-spacing:.5px;flex-shrink:0;color:#fff}
.report-toolbar b{font-size:13px;font-weight:680;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rt-tag{font-size:9.5px;letter-spacing:.1em;color:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.22);border-radius:var(--r-pill);padding:3px 9px;white-space:nowrap}
.rt-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.rt-icon,.rt-print{border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.06);color:#fff;border-radius:9px;cursor:pointer;font-size:12px;transition:.2s;display:inline-flex;align-items:center;justify-content:center;gap:7px}
.rt-icon{width:32px;height:32px}
.rt-print{padding:7px 14px;font-weight:650}
.rt-icon:hover,.rt-print:hover{background:rgba(255,255,255,.17);border-color:rgba(255,255,255,.5)}
[data-theme="dark"] .rt-icon .i-moon{display:none}
[data-theme="light"] .rt-icon .i-sun{display:none}

/* page */
.page{max-width:920px;margin:26px auto 52px;background:var(--panel);border:1px solid var(--line);box-shadow:var(--shadow),var(--inner);border-radius:var(--r-xl);overflow:hidden;position:relative}
.page:before{content:"";position:absolute;inset:0 0 auto 0;height:3px;background:linear-gradient(90deg,var(--brand),color-mix(in srgb,var(--brand) 40%,transparent),transparent);pointer-events:none;z-index:2}
.cover{padding:40px 48px 32px;border-bottom:3px solid var(--brand);background:linear-gradient(140deg,var(--panel) 0%,var(--surface) 100%);position:relative;overflow:hidden}
.cover:after{content:"";position:absolute;right:-100px;top:-160px;width:420px;height:420px;border-radius:50%;background:color-mix(in srgb,var(--brand) 13%,transparent);filter:blur(60px);pointer-events:none}
.cover-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;position:relative;z-index:1}
.brandbox{display:flex;align-items:center;gap:13px;min-width:0}
.brandbox img{height:44px;max-width:160px;object-fit:contain}
.brandmark{width:44px;height:44px;border-radius:13px;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;letter-spacing:1px;box-shadow:0 8px 22px -10px var(--brand);flex-shrink:0}
.brandname{font-size:18px;font-weight:790;color:var(--ink);line-height:1.2;letter-spacing:-.025em}
.brandtag{font-size:11px;color:var(--faint);margin-top:4px}
.report-label{text-align:right;color:var(--faint);font-size:9.5px;letter-spacing:.2em;text-transform:uppercase;white-space:nowrap}
.report-label strong{display:block;color:var(--brand);font-size:13.5px;letter-spacing:.02em;margin-top:4px;font-weight:750}
html[data-theme="dark"] .report-label strong,html[data-theme="dark"] .metric .mv{filter:brightness(1.4)}
.cover h1{font-size:clamp(1.8rem,3.2vw,2.6rem);margin:30px 0 8px;letter-spacing:-.05em;font-weight:800;line-height:1.18;position:relative;z-index:1}
.cover .subject{font-size:15.5px;color:var(--sub);font-weight:640;position:relative;z-index:1}
.cover .meta{margin-top:18px;display:flex;flex-wrap:wrap;gap:8px;position:relative;z-index:1}
.pill{background:var(--surface);border:1px solid var(--line);color:var(--sub);border-radius:var(--r-pill);padding:4px 12px;font-size:10.5px}
.body{padding:34px 48px 44px}
.score-hero{display:flex;align-items:center;gap:26px;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:22px 26px;margin-bottom:26px;box-shadow:var(--inner)}
.ring{width:102px;height:102px;border-radius:50%;border:8px solid var(--ring);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;background:var(--panel);box-shadow:0 0 0 6px var(--surface),0 12px 32px -16px var(--ring)}
.ring .num{font-size:27px;font-weight:820;color:var(--ring);line-height:1.1;letter-spacing:-.05em}
.ring .lbl{font-size:9.5px;color:var(--faint);margin-top:3px}
.score-copy h2{margin:0 0 5px;font-size:20px;font-weight:770;letter-spacing:-.03em}
.score-copy p{margin:0;color:var(--sub);font-size:12px;line-height:1.8}
.gradepill{display:inline-block;margin-top:10px;background:var(--ring);color:#fff;border-radius:var(--r-pill);padding:3px 13px;font-size:11px;font-weight:750}
.metrics{display:grid;grid-template-columns:repeat(5,1fr);gap:11px;margin:20px 0 30px}
.metric{border:1px solid var(--line);border-radius:var(--r-md);padding:14px 10px;text-align:center;background:var(--panel);box-shadow:var(--inner);transition:.25s}
.metric:hover{transform:translateY(-2px);border-color:var(--line-2)}
.metric .mv{font-size:20px;font-weight:810;color:var(--brand);letter-spacing:-.04em}
.metric .ml{font-size:10.5px;color:var(--faint);margin-top:4px}
.sec{margin:30px 0 0;page-break-inside:avoid}
.sec h3{font-size:15px;margin:0 0 14px;padding-left:12px;border-left:3px solid var(--brand);font-weight:740;letter-spacing:-.02em;line-height:1.3}
.table{width:100%;border-collapse:collapse;font-size:12px;border-radius:var(--r-sm);overflow:hidden}
.table th,.table td{border:1px solid var(--line);padding:9px 11px;text-align:left;vertical-align:top}
.table th{background:var(--surface);color:var(--sub);font-weight:700;white-space:nowrap}
.table td{color:var(--sub)}
.table td b{color:var(--ink)}
.dot{display:inline-flex;width:18px;height:18px;border-radius:50%;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;margin-right:5px}
.dot.ok{background:#1D9E75}.dot.warn{background:#D97706}.dot.bad{background:#DC2626}
.issue{border:1px solid var(--line);border-left:4px solid var(--faint);border-radius:var(--r-sm);padding:11px 14px;margin:9px 0;background:var(--panel)}
.issue.high{border-left-color:#DC2626}.issue.medium{border-left-color:#D97706}.issue.low,.issue.critical{border-left-color:#D85A30}
.issue .it{font-weight:720;font-size:13px;line-height:1.6;color:var(--ink)}
.issue .if{color:var(--sub);font-size:12px;margin-top:4px;line-height:1.7}
.level{font-size:10px;color:var(--faint);float:right;font-weight:400}
.quote{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-sm);padding:11px 14px;font-size:12px;color:var(--sub);margin:8px 0;line-height:1.8}
.tagline{display:inline-block;background:color-mix(in srgb,var(--brand) 10%,transparent);border:1px solid color-mix(in srgb,var(--brand) 28%,transparent);color:var(--brand);border-radius:8px;padding:4px 10px;font-size:11px;margin:2px 5px 2px 0;font-weight:620}
.notice{margin-top:28px;padding:13px 16px;background:var(--surface);border:1px dashed var(--line-2);border-radius:var(--r-sm);color:var(--faint);font-size:11px;line-height:1.8}
.footer{padding:20px 48px 28px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:24px;color:var(--faint);font-size:11px;background:var(--surface)}
.footer b{color:var(--sub);font-weight:720}
.bar{height:9px;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-pill);overflow:hidden;min-width:90px}
.bar i{display:block;height:100%;background:var(--brand);border-radius:var(--r-pill)}
details{margin:8px 0;border:1px solid var(--line);border-radius:var(--r-sm);padding:10px 13px;background:var(--panel);transition:.2s}
details:hover{border-color:var(--line-2)}
details[open]{border-color:color-mix(in srgb,var(--brand) 35%,transparent)}
summary{cursor:pointer;font-size:12px;font-weight:700;color:var(--sub);list-style:none}
summary::-webkit-details-marker{display:none}
summary:before{content:"▸";color:var(--brand);margin-right:8px;display:inline-block;transition:.2s}
details[open] summary:before{transform:rotate(90deg)}
details p{font-size:12px;color:var(--sub);margin:10px 0 0;white-space:pre-wrap;word-break:break-word;line-height:1.85}

@media print{
  @page{size:A4;margin:14mm}
  html,html[data-theme="dark"],html[data-theme="light"]{
    --bg:#FFFFFF;--panel:#FFFFFF;--surface:#F7F9FC;--line:rgba(15,23,42,.12);--line-2:rgba(15,23,42,.2);
    --ink:#0C1424;--sub:#475569;--faint:#7C879B;--shadow:none;--inner:none;color-scheme:light;
  }
  body{background:#fff;font-size:11px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .report-toolbar{display:none!important}
  .page{box-shadow:none;border:none;border-radius:0;margin:0;max-width:none}
  .page:before,.cover:after{display:none}
  .cover{padding:0 0 18px;background:#fff}
  .cover h1{font-size:24px}
  .body{padding:20px 0 24px}
  .footer{padding:14px 0 0;background:#fff}
  .sec{margin-top:20px;page-break-inside:avoid}
  .score-hero,.metric,.issue,.quote,details{page-break-inside:avoid;box-shadow:none}
  .score-hero{background:#F7F9FC}
  .ring{width:78px;height:78px;border-width:6px;box-shadow:none}
  .ring .num{font-size:22px}
  .metric:hover{transform:none}
  details{page-break-inside:avoid}
}
@media(max-width:720px){
  .report-toolbar{padding:9px 14px}
  .rt-tag{display:none}
  .rt-print{padding:7px 10px;font-size:11px}
  .page{margin:14px 10px 30px;border-radius:var(--r-lg)}
  .cover,.body,.footer{padding-left:22px;padding-right:22px}
  .cover h1{font-size:1.7rem}
  .report-label{display:none}
  .metrics{grid-template-columns:repeat(2,1fr)}
  .score-hero{flex-direction:column;align-items:flex-start;gap:18px}
  .table{font-size:11px}
  .footer{flex-direction:column;gap:8px}
}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}

/* ============================================================
   Editorial override · 去 AI 生成感：纸感底色 / 衬线标题 / 无光晕
   ============================================================ */
:root{--r-sm:4px;--r-md:6px;--r-lg:8px;--r-xl:10px;--font-display:Georgia,"Songti SC","STSong","Noto Serif SC",serif}
html[data-theme="light"]{
  --bg:#F1EFE8;--panel:#FDFCF9;--surface:#F5F3EC;
  --line:rgba(26,26,22,.16);--line-2:rgba(26,26,22,.3);
  --ink:#1A1A16;--sub:#4E5048;--faint:#7E8077;
  --shadow:0 8px 24px -20px rgba(26,26,22,.5);--inner:none;
}
html[data-theme="dark"]{
  --bg:#131410;--panel:#1D1F19;--surface:#23251E;
  --line:rgba(236,233,223,.16);--line-2:rgba(236,233,223,.3);
  --ink:#EDEAE0;--sub:#B4B3A8;--faint:#8B8C81;
  --shadow:0 10px 28px -22px rgba(0,0,0,.8);--inner:none;
}
.page:before{background:var(--brand)!important;height:2px!important}
.cover{background:var(--panel)!important}
.cover:after{display:none!important}
.cover h1,.brandname{font-family:var(--font-display)!important;font-weight:700!important;letter-spacing:-.02em!important;color:var(--ink)!important}
.cover .subject{font-family:var(--font-display)!important}
.brandmark{border-radius:4px!important;box-shadow:none!important}
.rt-mark{border-radius:4px!important}
.report-toolbar{background:#161812!important;-webkit-backdrop-filter:none!important;backdrop-filter:none!important;border-bottom-color:rgba(255,255,255,.14)!important}
.score-hero,.metric,.issue,.quote,details,.ring{box-shadow:none!important}
.score-hero{background:var(--surface)!important}
.tagline{background:transparent!important;border-color:var(--line-2)!important;border-radius:3px!important}
.gradepill,.pill{border-radius:3px!important}
.pill{background:var(--surface)!important}
.ring{box-shadow:none!important}
html[data-theme="dark"] .report-label strong,html[data-theme="dark"] .metric .mv{filter:none!important}
.report-label strong{color:var(--brand)!important}

/* ---- 行业化 GEO 方案排版 ---- */
.plan-summary{border:1px solid var(--line-2);border-left:3px solid var(--brand);background:var(--surface);padding:20px 24px;margin:0 0 28px;page-break-inside:avoid}
.plan-summary .ps-kicker{font-size:10px;letter-spacing:.14em;color:var(--faint);text-transform:uppercase}
.plan-summary h2{font-family:var(--font-display);font-size:20px;font-weight:700;margin:8px 0 10px;letter-spacing:-.02em;color:var(--ink)}
.plan-summary p{font-size:12.5px;color:var(--sub);line-height:1.9;margin:0}
.plan-summary .ps-figs{display:flex;flex-wrap:wrap;gap:26px;margin-top:16px;padding-top:14px;border-top:1px solid var(--line)}
.plan-summary .ps-figs div{min-width:96px}
.plan-summary .ps-figs b{display:block;font-family:var(--font-display);font-size:22px;color:var(--ink);line-height:1.2}
.plan-summary .ps-figs span{font-size:10.5px;color:var(--faint)}
.plan-sec{margin:32px 0 0;page-break-inside:avoid}
.plan-sec-h{display:flex;align-items:baseline;gap:12px;border-bottom:1px solid var(--line-2);padding-bottom:8px;margin-bottom:14px}
.plan-sec-h .pn{font-family:var(--font-display);font-size:20px;color:var(--brand);line-height:1}
.plan-sec-h h3{font-size:15px;font-weight:720;margin:0;padding:0;border:0;letter-spacing:-.01em;color:var(--ink)}
.plan-sec-h .ps-note{font-size:10.5px;color:var(--faint);margin-left:auto}
.plan-lead{font-size:12.5px;color:var(--sub);line-height:1.9;margin:0 0 14px}
.plan-def{display:grid;grid-template-columns:repeat(2,1fr);gap:0 22px;margin:10px 0 18px}
.plan-def div{border-top:1px solid var(--line);padding:10px 0 12px}
.plan-def dt{font-size:11px;color:var(--faint);letter-spacing:.04em;margin-bottom:5px}
.plan-def dd{margin:0;font-size:12.5px;color:var(--ink);line-height:1.8}
.plan-list{margin:8px 0 0;padding-left:18px;font-size:12.5px;color:var(--sub);line-height:1.95}
.plan-list li{margin:3px 0}
.plan-list li b{color:var(--ink)}
.plan-chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 14px}
.plan-chip{font-size:11px;color:var(--sub);border:1px solid var(--line-2);border-radius:3px;padding:3px 9px;background:var(--panel)}
.plan-action{border:1px solid var(--line);border-left:3px solid var(--line-2);padding:12px 15px;margin:10px 0;background:var(--panel);page-break-inside:avoid}
.plan-action.p0{border-left-color:#9C4A3C}
.plan-action.p1{border-left-color:#96661E}
.plan-action.p2{border-left-color:#2C6E8F}
.plan-action .pa-h{display:flex;align-items:center;gap:9px;margin-bottom:7px}
.plan-action .pa-tag{font-size:10px;font-weight:700;letter-spacing:.06em;color:#fff;background:#6E7066;border-radius:2px;padding:2px 7px}
.plan-action.p0 .pa-tag{background:#9C4A3C}
.plan-action.p1 .pa-tag{background:#96661E}
.plan-action.p2 .pa-tag{background:#2C6E8F}
.plan-action .pa-t{font-size:13px;font-weight:720;color:var(--ink)}
.plan-action dl{display:grid;grid-template-columns:52px 1fr;gap:4px 10px;margin:0;font-size:12px;line-height:1.8}
.plan-action dt{color:var(--faint);font-size:11px}
.plan-action dd{margin:0;color:var(--sub)}
.plan-action dd b{color:var(--ink);font-weight:650}
.plan-note{border:1px dashed var(--line-2);background:var(--surface);padding:13px 16px;font-size:11.5px;color:var(--sub);line-height:1.9;margin:14px 0 0;page-break-inside:avoid}
.plan-note b{color:var(--ink)}
.plan-warn{border-left:3px solid #9C4A3C}
.plan-table td:first-child{white-space:nowrap;font-weight:650;color:var(--ink)}
.plan-kpi b{font-family:var(--font-display);font-size:15px;color:var(--ink)}
.plan-appendix{margin-top:36px;border-top:2px solid var(--ink);padding-top:14px}
.plan-appendix h3{font-family:var(--font-display);font-size:16px;color:var(--ink);margin:0 0 6px;padding:0;border:0}
.plan-appendix p{font-size:11px;color:var(--faint);margin:0 0 16px}
@media(max-width:720px){.plan-def{grid-template-columns:1fr}.plan-sec-h .ps-note{display:none}.plan-summary .ps-figs{gap:16px}}
@media print{
  html,html[data-theme="dark"],html[data-theme="light"]{
    --bg:#fff;--panel:#fff;--surface:#F5F3EC;--line:rgba(26,26,22,.18);--line-2:rgba(26,26,22,.32);
    --ink:#1A1A16;--sub:#4E5048;--faint:#7E8077;--shadow:none;--inner:none;color-scheme:light;
  }
  body{background:#fff}
  .plan-summary,.plan-action,.plan-sec,.plan-note{page-break-inside:avoid;box-shadow:none}
  .plan-summary{background:#F5F3EC}
}

/* ---- 深度诊断明细（v1，与四层评分解耦） ---- */
.deep-summary{display:flex;align-items:center;gap:18px;border:1px solid var(--line-2);border-left:3px solid var(--brand);background:var(--surface);padding:14px 18px;margin:20px 0 0;page-break-inside:avoid}
.deep-summary .ds-num{font-family:var(--font-display);font-size:28px;color:var(--brand);line-height:1;white-space:nowrap}
.deep-summary .ds-txt{font-size:12px;color:var(--sub);line-height:1.8}
.deep-summary .ds-txt b{color:var(--ink)}
.deep-group{margin:26px 0 0;page-break-inside:avoid}
.deep-group>h4{font-family:var(--font-display);font-size:14px;color:var(--ink);margin:0 0 4px;padding:0 0 8px;border-bottom:1px solid var(--line-2)}
.deep-group .dg-desc{font-size:11px;color:var(--faint);margin:0 0 10px}
.deep-table{width:100%;border-collapse:collapse;font-size:11.5px}
.deep-table th,.deep-table td{border:1px solid var(--line);padding:8px 10px;text-align:left;vertical-align:top}
.deep-table th{background:var(--surface);color:var(--sub);font-weight:700;white-space:nowrap}
.deep-table td{color:var(--sub)}
.deep-table td b{color:var(--ink)}
.deep-table .de{display:block;margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:10.5px;color:var(--faint);word-break:break-all;line-height:1.6}
.deep-table .df{display:block;margin-top:4px;font-size:10.5px;color:var(--sub);line-height:1.6}
.deep-badge{display:inline-block;min-width:34px;text-align:center;border-radius:3px;padding:2px 6px;font-size:10px;font-weight:700;color:#fff}
.deep-badge.ok{background:#1D9E75}.deep-badge.warn{background:#D97706}.deep-badge.bad{background:#DC2626}
.cov-bar{display:inline-block;width:66px;height:6px;background:var(--line);border-radius:3px;overflow:hidden;vertical-align:middle;margin-right:6px}
.cov-bar i{display:block;height:100%;background:var(--brand)}
/* 深度明细章节很长：整章允许分页，由每个分组自行避免跨页截断 */
.sec-deep{page-break-inside:auto}
.deep-table tr{page-break-inside:avoid}
@media(max-width:720px){.deep-summary{flex-wrap:wrap}.deep-table{font-size:11px}}
@media print{
  .deep-summary,.deep-group{page-break-inside:avoid;box-shadow:none}
  .deep-summary{background:#F5F3EC}
  .sec-deep{page-break-inside:auto!important}
  .deep-table tr{page-break-inside:avoid}
}
</style>
</head>
<body>
<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
<symbol id="i-print" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></symbol>
</defs></svg>
<div class="report-toolbar">
  <div class="rt-left">
    <span class="rt-mark">GEO</span>
    <b><?= h($brand['report_brand']) ?> · <?= h($title) ?></b>
    <span class="rt-tag">公开报告</span>
  </div>
  <div class="rt-actions">
    <button class="rt-icon" id="reportThemeToggle" aria-label="切换深浅主题" title="切换深浅主题"><svg class="ico i-sun"><use href="#i-sun"/></svg><svg class="ico i-moon"><use href="#i-moon"/></svg></button>
    <button class="rt-print" onclick="window.print()"><svg class="ico"><use href="#i-print"/></svg>打印 / 导出 PDF</button>
  </div>
</div>

<div class="page">
  <header class="cover">
    <div class="cover-top">
      <div class="brandbox">
        <?php if ($logo): ?><img src="<?= h($logo) ?>" alt="<?= h($brand['report_brand']) ?>">
        <?php else: ?><div class="brandmark"><?= h(mb_substr($brand['report_brand'], 0, 2)) ?></div><?php endif; ?>
        <div><div class="brandname"><?= h($brand['report_brand']) ?></div><div class="brandtag"><?= h($brand['report_tagline']) ?></div></div>
      </div>
      <div class="report-label">诊断报告<strong><?= h($title) ?></strong></div>
    </div>
    <h1><?= h($title) ?></h1>
    <div class="subject">报告对象：<?= h($subject) ?></div>
    <div class="meta">
      <span class="pill">报告编号 <?= h($id) ?></span>
      <span class="pill">生成时间 <?= h($generatedAt) ?></span>
      <?php if ($isAudit): ?><span class="pill">检测地址 <?= h($result['url'] ?? $report['url'] ?? '') ?></span><?php else: ?><span class="pill">行业词 <?= h($result['industry'] ?? $report['industry'] ?? '') ?></span><?php endif; ?>
      <span class="pill">模式 <?= h($report['mode'] ?? ($result['mode'] ?? '官网抓取')) ?></span>
    </div>
  </header>

  <div class="body">
    <div class="score-hero" style="--ring:<?= h(rcolor($score)) ?>">
      <div class="ring"><div class="num"><?= h($scoreText) ?></div><div class="lbl"><?= $isAudit ? 'GEO 得分' : '品牌提及率' ?></div></div>
      <div class="score-copy">
        <h2>综合评估：<?= h($grade) ?></h2>
        <p><?= $isAudit ? '依据可访问性、导航指引、可理解性、可引用性四层依赖链加权得出。' : '基于所选平台的有效采样计算，失败样本不补零、不计入分母。下方按行业特征编制可直接执行的优化方案。' ?></p>
        <span class="gradepill"><?= h($grade) ?></span>
      </div>
    </div>

    <?php
      $deep        = is_array($result['deep'] ?? null) ? $result['deep'] : [];
      $deepStats   = is_array($deep['stats'] ?? null) ? $deep['stats'] : [];
      $deepGroups  = is_array($deep['groups'] ?? null) ? $deep['groups'] : [];
      $deepCov     = is_array($deep['coverage'] ?? null) ? $deep['coverage'] : [];
      $deepCovSum  = is_array($deepCov['summary'] ?? null) ? $deepCov['summary'] : [];
      $deepPages   = is_array($deepCov['pages'] ?? null) ? $deepCov['pages'] : [];
    ?>
    <?php if ($isAudit && $deepGroups): ?>
      <div class="deep-summary">
        <div class="ds-num"><?= h($deepStats['pass_rate'] ?? 0) ?>%</div>
        <div class="ds-txt"><b>深度细项通过率</b>：共 <b><?= h($deepStats['total'] ?? 0) ?></b> 项细查（<?= h($deepStats['groups'] ?? 0) ?> 组），通过 <?= h($deepStats['ok'] ?? 0) ?> 项、待优化 <?= h($deepStats['warn'] ?? 0) ?> 项、阻断 <?= h($deepStats['bad'] ?? 0) ?> 项；耗时 <?= h($deep['elapsed_ms'] ?? 0) ?>ms。深度细项<b>不计入上方 GEO 得分</b>，仅用于定位具体问题并提供复核依据。</div>
      </div>
    <?php endif; ?>

    <?php if ($isAudit):
      $layers = arr($result['layers'] ?? []);
      $names = ['access'=>'可访问性','orientation'=>'导航指引','understanding'=>'可理解性','quotability'=>'可引用性'];
      $descs = ['access'=>'AI 能否抓取内容','orientation'=>'有无导航与规范指引','understanding'=>'内容能否被结构化理解','quotability'=>'内容能否被直接引用'];
      $meta = arr($result['meta'] ?? []);
    ?>
      <div class="metrics">
        <?php foreach ($names as $k=>$n): $ls = arr($layers[$k] ?? []); $lv = (float)($ls['score'] ?? 0); ?>
          <div class="metric"><div class="mv" style="color:<?= h(rcolor($lv)) ?>"><?= h($lv) ?></div><div class="ml"><?= h($n) ?></div></div>
        <?php endforeach; ?>
        <div class="metric"><div class="mv"><?= h($meta['word_count'] ?? ($result['layers']['understanding']['word_count'] ?? '—')) ?></div><div class="ml">正文字数</div></div>
      </div>

      <?php if (!empty($result['chain_warning'])): ?>
        <div class="issue high"><div class="it">依赖链风险提示</div><div class="if"><?= h($result['chain_warning']) ?></div></div>
      <?php endif; ?>

      <section class="sec">
        <h3>一、四层审计明细</h3>
        <?php foreach ($names as $k=>$n): $ls = arr($layers[$k] ?? []); ?>
          <table class="table" style="margin-bottom:12px">
            <tr><th colspan="3" style="background:var(--brand);color:#fff"><?= h($n) ?> · <?= h($descs[$k]) ?>　得分 <?= h($ls['score'] ?? 0) ?>/100</th></tr>
            <?php foreach (arr($ls['checks'] ?? []) as $c): $c = arr($c); $st = $c['s'] ?? 'warn'; ?>
              <tr>
                <td style="width:64px"><span class="dot <?= h($st) ?>"><?= h(checkIcon($st)) ?></span><?= h(checkName($st)) ?></td>
                <td style="width:38%"><b><?= h($c['t'] ?? '') ?></b></td>
                <td><?= h($c['d'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endforeach; ?>
      </section>

      <section class="sec">
        <h3>二、优先修复清单</h3>
        <?php $issues = arr($result['issues'] ?? []); if (!$issues): ?>
          <div class="issue"><div class="it">未发现明显阻断性问题</div></div>
        <?php else: foreach ($issues as $i): $i = arr($i); ?>
          <div class="issue <?= h($i['level'] ?? '') ?>">
            <span class="level"><?= h(['critical'=>'紧急','high'=>'高','medium'=>'中','low'=>'低'][$i['level'] ?? ''] ?? '建议') ?></span>
            <div class="it">[<?= h($i['layer'] ?? '') ?>] <?= h($i['text'] ?? '') ?></div>
            <div class="if">建议：<?= h($i['fix'] ?? '') ?></div>
          </div>
        <?php endforeach; endif; ?>
      </section>

      <?php if (!empty($result['bot_access'])): ?>
      <section class="sec">
        <h3>三、AI 爬虫 robots 规则</h3>
        <table class="table"><tr><th>爬虫</th><th>状态</th><th>匹配规则</th></tr>
        <?php foreach (arr($result['bot_access']) as $name=>$bot): $bot = arr($bot); ?>
          <tr><td><?= h($name) ?></td><td style="color:<?= ($bot['state'] ?? '') === 'disallow' ? '#DC2626' : '#1D9E75' ?>"><?= ($bot['state'] ?? '') === 'disallow' ? '拦截' : '允许' ?></td><td><?= h($bot['rule'] ?? '') ?></td></tr>
        <?php endforeach; ?></table>
      </section>
      <?php endif; ?>

      <?php if (!empty($result['quotable_paragraphs'])): ?>
      <section class="sec">
        <h3>四、可独立引用段落样本</h3>
        <?php foreach (arr($result['quotable_paragraphs']) as $p): $p = arr($p); ?>
          <div class="quote"><?= h($p['text'] ?? '') ?>…</div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <?php if ($deepPages): ?>
      <section class="sec">
        <h3>五、全站覆盖矩阵（sitemap 抽样 <?= count($deepPages) ?> 页 / 共 <?= h($deepCov['sitemap_urls'] ?? 0) ?> 条 URL）</h3>
        <table class="table deep-table">
          <tr><th>内页</th><th>状态</th><th>title</th><th>desc</th><th>H1</th><th>canonical</th><th>JSON-LD</th><th>时间</th><th>字数</th><th>可引用段</th><th>响应</th></tr>
          <?php foreach ($deepPages as $pg): $pg = arr($pg); ?>
          <tr>
            <td style="word-break:break-all"><?= h(mb_substr((string)($pg['url'] ?? ''), 0, 44)) ?></td>
            <td style="color:<?= (int)($pg['status'] ?? 0) >= 400 ? '#DC2626' : '#1D9E75' ?>"><?= h($pg['status'] ?? 0) ?></td>
            <td><?= h(mb_substr((string)($pg['title'] ?? ''), 0, 22)) ?: '—' ?></td>
            <td><?= !empty($pg['desc']) ? '有' : '<span style="color:#DC2626">缺</span>' ?></td>
            <td><?= h($pg['h1'] ?? 0) ?></td>
            <td><?= h($pg['canonical'] ?? '缺') ?></td>
            <td><?= h(mb_substr((string)($pg['jsonld'] ?? '缺'), 0, 20)) ?></td>
            <td><?= h($pg['datetime'] ?? '缺') ?></td>
            <td><?= h($pg['words'] ?? 0) ?></td>
            <td><?= h($pg['quotable'] ?? 0) ?></td>
            <td><?= h($pg['ms'] ?? 0) ?>ms</td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php if ($deepCovSum): ?>
        <table class="table" style="margin-top:12px">
          <tr><th colspan="3" style="background:var(--brand);color:#fff">全站细项覆盖率</th></tr>
          <?php foreach ($deepCovSum as $covName=>$cv): $cv = arr($cv); $pct = (int)($cv['pct'] ?? 0); ?>
          <tr>
            <td style="width:30%"><b><?= h($covName) ?></b></td>
            <td style="width:22%"><span class="cov-bar"><i style="width:<?= $pct ?>%"></i></span><?= $pct ?>%</td>
            <td><?= h($cv['ok'] ?? 0) ?> / <?= h($cv['total'] ?? 0) ?> 页达标</td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
      </section>
      <?php elseif (!empty($result['inner_pages'])): ?>
      <section class="sec">
        <h3>五、内页抽样（sitemap 驱动）</h3>
        <table class="table"><tr><th>内页</th><th>状态</th><th>正文字数</th><th>JSON-LD</th><th>canonical</th><th>H1</th><th>响应</th></tr>
        <?php foreach (arr($result['inner_pages']) as $pg): $pg = arr($pg); ?>
          <tr>
            <td style="word-break:break-all"><?= h(mb_substr((string)($pg['url'] ?? ''), 0, 70)) ?></td>
            <td style="color:<?= (int)($pg['status'] ?? 0) >= 400 ? '#DC2626' : '#1D9E75' ?>"><?= h($pg['status'] ?? 0) ?></td>
            <td><?= h($pg['words'] ?? 0) ?></td>
            <td><?= !empty($pg['has_jsonld']) ? '有' : '<span style="color:#DC2626">缺</span>' ?></td>
            <td><?= !empty($pg['has_canonical']) ? '有' : '缺' ?></td>
            <td><?= !empty($pg['has_h1']) ? '有' : '缺' ?></td>
            <td><?= h($pg['response_ms'] ?? 0) ?>ms</td>
          </tr>
        <?php endforeach; ?></table>
      </section>
      <?php endif; ?>

      <?php if ($deepGroups): ?>
      <section class="sec sec-deep">
        <h3>六、深度诊断明细（<?= h($deepStats['total'] ?? 0) ?> 项 · 含证据与修复建议）</h3>
        <?php foreach ($deepGroups as $g): $g = arr($g); ?>
          <div class="deep-group">
            <h4><?= h($g['name'] ?? '') ?></h4>
            <p class="dg-desc"><?= h($g['desc'] ?? '') ?></p>
            <table class="table deep-table">
              <tr><th style="width:52px">判定</th><th style="width:24%">检查项</th><th>结论 / 证据 / 建议</th></tr>
              <?php foreach (arr($g['items'] ?? []) as $it): $it = arr($it); $st = $it['s'] ?? 'warn'; ?>
              <tr>
                <td><span class="deep-badge <?= h($st) ?>"><?= h(checkName($st)) ?></span></td>
                <td><b><?= h($it['t'] ?? '') ?></b></td>
                <td>
                  <?= h($it['d'] ?? '') ?>
                  <?php if (!empty($it['e'])): ?><span class="de">证据：<?= h($it['e']) ?></span><?php endif; ?>
                  <?php if (!empty($it['fix']) && $st !== 'ok'): ?><span class="df">建议：<?= h($it['fix']) ?></span><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <div class="notice">诊断方法：纯抓取分析（robots 规则解析、多 AI 爬虫 UA 实测、llms.txt / sitemap 质量校验、结构化数据逐类型核对、实体一致性比对、段落级可引用性统计、响应头与内链检查、sitemap 内页微审计）。四层得分为加权评估结果，深度细项单独统计、不计入总分；所有结论仅供优化参考，不代表搜索引擎或大模型官方评价。</div>

    <?php else: ?>
      <?= geo_plan_html([
        'report' => $report,
        'result' => $result,
        'brand_name' => $brand['report_brand'],
        'id' => $id,
        'generated_at' => $generatedAt,
        'score' => $score,
        'score_text' => $scoreText,
        'grade' => $grade,
      ]) ?>
    <?php endif; ?>
  </div>

  <footer class="footer">
    <div><b><?= h($brand['report_brand']) ?></b><br><?= h($brand['report_contact'] ?? '') ?></div>
    <div style="text-align:right"><?= h($brand['report_footer'] ?? '') ?><br>报告时间：<?= h($generatedAt) ?></div>
  </footer>
</div>
<script>
(function(){
  var root=document.documentElement,btn=document.getElementById('reportThemeToggle');
  function sync(t){
    document.querySelectorAll('meta[name="theme-color"]').forEach(function(m){
      var d=(m.getAttribute('media')||'').indexOf('dark')>=0;
      if((d&&t==='dark')||(!d&&t==='light'))m.setAttribute('content',t==='dark'?'#070A12':'#F6F8FD');
    });
  }
  sync(root.getAttribute('data-theme')||'light');
  if(btn)btn.onclick=function(){
    var t=root.getAttribute('data-theme')==='dark'?'light':'dark';
    root.setAttribute('data-theme',t);
    try{localStorage.setItem('geo-theme',t);}catch(e){}
    sync(t);
  };
})();
</script>
</body>
</html>
