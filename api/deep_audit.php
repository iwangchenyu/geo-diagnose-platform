<?php
/**
 * 深度 GEO 诊断 v1（2026-11-20）
 * 设计原则：与四层评分完全解耦 —— 只输出可复核的细项、证据与修复建议，
 *           不改变 score / layers / weights，保证历史报告分数可比。
 * 输出：groups（6 组细项）、bot_matrix（AI 爬虫实测）、coverage（全站覆盖矩阵）、stats
 */
require_once __DIR__ . '/lib.php';

/** 统一的细项结构：s=状态 t=标题 d=说明 e=证据片段 fix=修复建议 */
function deep_item($s, $t, $d, $e = '', $fix = '') {
    return [
        's' => $s, 't' => $t, 'd' => $d,
        'e' => mb_substr(trim((string)$e), 0, 180),
        'fix' => $fix,
    ];
}

/** 从标签字符串取属性（兼容任意顺序 / 单双引号 / 无引号 / 大小写） */
function deep_attr($tag, $name) {
    $q = preg_quote($name, '/');
    if (preg_match('/\b' . $q . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m))
        return html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
    if (preg_match('/\b' . $q . '\s*=\s*([^\s"\'>]+)/i', $tag, $m))
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    return '';
}

/** 取指定 meta 的 content（keyAttr 可是 name / property） */
function deep_meta_content($html, $keyAttr, $keyVal) {
    if (!preg_match_all('/<meta\b[^>]*>/i', $html, $ms)) return '';
    foreach ($ms[0] as $t) {
        if (strcasecmp(deep_attr($t, $keyAttr), $keyVal) === 0) return deep_attr($t, 'content');
    }
    return '';
}

/** 解析页面所有 JSON-LD，返回块、类型清单、解析错误数、@graph 使用数 */
function deep_jsonld($html) {
    $blocks = []; $types = []; $errors = 0; $graphs = 0; $count = 0;
    if (preg_match_all('/<script\b[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
        $count = count($m[1]);
        foreach ($m[1] as $raw) {
            $d = json_decode(trim($raw), true);
            if (!is_array($d)) { $errors++; continue; }
            $blocks[] = $d;
            if (isset($d['@graph']) && is_array($d['@graph'])) $graphs++;
            $nodes = (isset($d['@graph']) && is_array($d['@graph'])) ? $d['@graph'] : [$d];
            foreach ($nodes as $n) {
                if (!is_array($n) || !isset($n['@type'])) continue;
                foreach ((array)$n['@type'] as $x) $types[] = (string)$x;
            }
        }
    }
    return ['count' => $count, 'blocks' => $blocks, 'types' => array_values(array_unique($types)), 'errors' => $errors, 'graphs' => $graphs];
}

/** 找出指定 @type 的所有节点 */
function deep_node_of($blocks, $type) {
    $out = [];
    foreach ($blocks as $b) {
        $nodes = (isset($b['@graph']) && is_array($b['@graph'])) ? $b['@graph'] : [$b];
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;
            $ts = array_map('strval', (array)($n['@type'] ?? []));
            if (in_array($type, $ts, true)) $out[] = $n;
        }
    }
    return $out;
}

/** 段落级可引用性统计（供细项组与内页探针复用） */
function deep_quotable($html) {
    preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $m);
    $ps = [];
    foreach ($m[1] as $p) { $t = trim(html_to_text($p)); if ($t !== '') $ps[] = $t; }
    $count = 0; $defs = 0; $num = 0; $src = 0; $lens = []; $samples = [];
    foreach ($ps as $t) {
        $len = mb_strlen($t); $lens[] = $len;
        if ($len < 40 || $len > 500) continue;
        $facts = preg_match_all('/\d/', $t);
        $hasQuote = preg_match('/[""「」]|说|指出|表示|数据显示|据统计/u', $t);
        $sent = preg_match_all('/[。！？.!?]/', $t);
        if ($facts >= 2 || $hasQuote || $sent >= 2) {
            $count++;
            if (count($samples) < 3) $samples[] = ['text' => mb_substr($t, 0, 150), 'len' => $len];
        }
        if (preg_match('/(是指|指的是|定义为|是一种|是面向|即为|称为|）是|：是|，是|，指)/u', $t) && $len >= 25) $defs++;
        if ($facts >= 2) $num++;
        if (preg_match('/(来源|作者|发布|更新|数据显示|据统计)/u', $t)) $src++;
    }
    return [
        'total' => count($ps), 'count' => $count, 'defs' => $defs, 'num' => $num, 'src' => $src,
        'ratio' => count($ps) ? (int)round($count / count($ps) * 100) : 0,
        'avg_len' => $lens ? (int)round(array_sum($lens) / count($lens)) : 0,
        'samples' => $samples,
    ];
}

/** 并发抓取（每个 URL 过 safe_url，单轮 curl_multi，总耗时≈单个超时） */
function deep_fetch_multi($targets, $timeout = 8) {
    $out = []; $mh = curl_multi_init(); $chs = [];
    foreach ($targets as $k => $t) {
        $safe = safe_url($t['url']);
        if (!$safe) { $out[$k] = ['status' => 0, 'headers' => [], 'body' => '', 'time' => 0, 'error' => 'URL 被安全策略拒绝']; continue; }
        $ch = curl_init($safe);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $t['ua'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 Safari/537.36',
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true, CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        curl_multi_add_handle($mh, $ch); $chs[$k] = $ch;
    }
    $running = null;
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running > 0) @curl_multi_select($mh, 0.15);
    } while ($running > 0 && $st === CURLM_OK);
    foreach ($chs as $k => $ch) {
        $raw = (string)curl_multi_getcontent($ch);
        $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ms = (int)round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        $hdr = [];
        foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) {
            if (strpos($line, ':') !== false) { [$a, $b] = explode(':', $line, 2); $hdr[strtolower(trim($a))] = trim($b); }
        }
        $out[$k] = ['status' => $status, 'headers' => $hdr, 'body' => substr($raw, $hs), 'time' => $ms, 'error' => curl_error($ch)];
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** 单页微审计探针 */
function deep_page_probe($url, $r) {
    $html = (string)($r['body'] ?? '');
    $text = html_to_text($html);
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) $title = mb_substr(trim(html_to_text($m[1])), 0, 70);
    $desc = mb_substr(deep_meta_content($html, 'name', 'description'), 0, 70);
    $h1n = preg_match_all('/<h1\b/i', $html);
    $canon = '';
    if (preg_match('/<link\b[^>]*rel\s*=\s*["\']canonical["\'][^>]*>/i', $html, $m)) $canon = deep_attr($m[0], 'href');
    $ld = deep_jsonld($html);
    $dt = '';
    if (preg_match('/(?:datePublished|article:published_time|datetime=)["\':\s]*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $html, $m)) $dt = $m[1];
    $q = deep_quotable($html);
    return [
        'url' => $url, 'status' => (int)($r['status'] ?? 0), 'title' => $title, 'desc' => $desc,
        'h1' => $h1n, 'canonical' => $canon ? '有' : '缺',
        'jsonld' => $ld['types'] ? implode('/', array_slice($ld['types'], 0, 3)) : '缺',
        'datetime' => $dt ?: '缺', 'words' => mb_strlen($text), 'quotable' => $q['count'],
        'ms' => (int)($r['time'] ?? 0),
    ];
}

/** 品牌主名归一（去分隔符后缀） */
function deep_brand_main($s) {
    $s = trim((string)$s);
    $s = preg_split('/[·|\-—–:：\s]{1,}/u', $s, 2)[0] ?? $s;
    return norm_text($s);
}

/**
 * 执行深度诊断
 * $ctx: url, origin, home, robots, sitemap, llms, html, text, word_count, final_url, inner_pages
 */
function runDeepAudit($ctx) {
    $t0 = microtime(true);
    $url = (string)$ctx['url'];
    $origin = (string)$ctx['origin'];
    $home = (array)$ctx['home'];
    $html = (string)$ctx['html'];
    $text = (string)$ctx['text'];
    $hdr = (array)($home['headers'] ?? []);
    $finalUrl = (string)($ctx['final_url'] ?? $url);
    $robotsBody = (string)($ctx['robots']['body'] ?? '');
    $robotsStatus = (int)($ctx['robots']['status'] ?? 0);
    $sitemapBody = (string)($ctx['sitemap']['body'] ?? '');
    $sitemapStatus = (int)($ctx['sitemap']['status'] ?? 0);
    $llmsBody = (string)($ctx['llms']['body'] ?? '');
    $llmsStatus = (int)($ctx['llms']['status'] ?? 0);

    $ld = deep_jsonld($html);
    $q = deep_quotable($html);
    // 正文 <p> 中残留的前端模板 / JS 拼接痕迹：CSR 站点常见问题，AI 抓到的是未渲染模板
    $templateTraces = 0;
    if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $pm2)) {
        foreach ($pm2[1] as $p2) {
            $t2 = trim(html_to_text($p2));
            if (preg_match('/\'\+|"\+|\{\{|\}\}|data\.[A-Za-z_]+\s*\|\||!C\(|v-if|v-for|ng-bind|\$\{/', $t2)) $templateTraces++;
        }
    }
    $groups = [];

    // ---------- 并发目标：AI 爬虫实测 + llms 同源链接 + 内页 ----------
    $botUas = [
        'GPTBot' => 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)',
        'ClaudeBot' => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        'PerplexityBot' => 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai)',
        'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    ];
    $browserUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 Safari/537.36';
    $targets = [];
    foreach ($botUas as $n => $ua) $targets['bot:' . $n] = ['url' => $url, 'ua' => $ua];

    // llms.txt 中的同源链接（最多 3 条）
    $llmsLinks = [];
    if (preg_match_all('/^\s*[-*]\s*\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/m', $llmsBody, $lm)) {
        foreach ($lm[2] as $i => $u) {
            if (count($llmsLinks) >= 3) break;
            if (strpos($u, parse_url($origin, PHP_URL_HOST)) !== false) $llmsLinks[] = $u;
        }
    }
    foreach ($llmsLinks as $i => $u) $targets['link:' . $i] = ['url' => $u, 'ua' => $browserUa];

    // sitemap 内页（最多 5 条，排除首页）
    $innerUrls = [];
    if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $sitemapBody, $sm)) {
        foreach ($sm[1] as $u) {
            $u = trim(html_entity_decode($u, ENT_QUOTES, 'UTF-8'));
            if (!preg_match('#^https?://#i', $u)) continue;
            if (rtrim($u, '/') === rtrim($origin, '/') || rtrim($u, '/') === rtrim($url, '/')) continue;
            $innerUrls[] = $u;
            if (count($innerUrls) >= 5) break;
        }
    }
    foreach ($innerUrls as $i => $u) $targets['page:' . $i] = ['url' => $u, 'ua' => $browserUa];
    $fetched = $targets ? deep_fetch_multi($targets, 8) : [];

    // ================= A 组：发现与指引文件 =================
    $a = [];
    $a[] = deep_item($robotsStatus === 200 ? 'ok' : 'warn', 'robots.txt 可访问',
        $robotsStatus === 200 ? 'HTTP 200，站点自主提供' : '未取到 robots.txt（HTTP ' . $robotsStatus . '），依赖默认允许规则',
        mb_substr(str_replace("\n", ' ⏎ ', $robotsBody), 0, 150),
        '在站点根目录提供 robots.txt，显式声明 AI 爬虫规则与 Sitemap 地址');
    $hasSitemapDir = (bool)preg_match('/^Sitemap:/im', $robotsBody);
    $a[] = deep_item($hasSitemapDir ? 'ok' : 'warn', 'robots 声明 Sitemap 指令',
        $hasSitemapDir ? '已声明 Sitemap 地址' : '缺少 Sitemap 指令，爬虫需自行猜测站点地图',
        $hasSitemapDir ? trim(preg_replace('/^.*?(Sitemap:[^\r\n]+).*$/is', '$1', $robotsBody)) : '(无 Sitemap 行)',
        '在 robots.txt 末尾添加：Sitemap: ' . rtrim($origin, '/') . '/sitemap.xml');
    preg_match_all('/^User-agent:\s*(\S+)/im', $robotsBody, $um);
    $aiNames = ['GPTBot','OAI-SearchBot','ChatGPT-User','Google-Extended','ClaudeBot','anthropic-ai','PerplexityBot','Perplexity-User','Bytespider','CCBot','Applebot-Extended','Amazonbot','Bingbot','Baiduspider'];
    $explicit = array_values(array_intersect(array_map('strtolower', $um[1] ?? []), array_map('strtolower', $aiNames)));
    $a[] = deep_item(count($explicit) >= 4 ? 'ok' : 'warn', '显式放行 AI 爬虫的 UA 段',
        '识别到 ' . count($explicit) . ' 个主流 AI 爬虫 UA 段' . ($explicit ? '：' . implode('、', array_slice($explicit, 0, 6)) : ''),
        implode(' / ', array_slice($um[1] ?? [], 0, 8)),
        '逐 UA 写 User-agent + Allow: /，覆盖 GPTBot、ClaudeBot、PerplexityBot、Google-Extended 等');
    $shielded = (bool)preg_match('#Disallow:\s*/(diagnose|admin|api|wp-admin|login|manage)#i', $robotsBody);
    $a[] = deep_item($shielded ? 'ok' : 'warn', '后台/API 路径已屏蔽',
        $shielded ? '检测到后台或接口路径的 Disallow 规则' : '未显式屏蔽后台、登录或接口路径',
        implode(' ⏎ ', array_slice(preg_grep('/^Disallow:/im', preg_split('/\r?\n/', $robotsBody)) ?: [], 0, 4)),
        '添加 Disallow: /diagnose/、Disallow: /api/ 等，避免后台被索引');
    $a[] = deep_item($llmsStatus === 200 ? 'ok' : 'warn', 'llms.txt 可访问',
        $llmsStatus === 200 ? 'HTTP 200，' . strlen($llmsBody) . ' 字节' : '缺少 llms.txt（HTTP ' . $llmsStatus . '）',
        mb_substr($llmsBody, 0, 120),
        '按 llmstxt.org 规范提供 /llms.txt：H1 站点名、> 简介、分组链接列表');
    $llmsH1 = preg_match('/^#\s+(\S.*)$/m', $llmsBody, $hm);
    $a[] = deep_item($llmsH1 ? 'ok' : 'warn', 'llms.txt 含 H1 站点名',
        $llmsH1 ? '首标题：' . mb_substr(trim($hm[1]), 0, 40) : '缺少 Markdown H1 站点名',
        $llmsH1 ? trim($hm[0]) : '(无 H1)',
        '首行写 `# 品牌名`，让模型先建立实体名称');
    $llmsQuote = preg_match('/^>\s+(\S.*)$/m', $llmsBody, $qm);
    $a[] = deep_item($llmsQuote ? 'ok' : 'warn', 'llms.txt 含站点简介',
        $llmsQuote ? '存在 blockquote 简介段' : '缺少 > 开头的简介，模型难以一句话理解站点',
        $llmsQuote ? mb_substr(trim($qm[1]), 0, 120) : '(无简介段)',
        '在 H1 下添加 `> 一句话说明站点做什么、服务谁、关键口径`');
    $a[] = deep_item(count($llmsLinks) >= 2 ? 'ok' : 'warn', 'llms.txt 链接列表与可达性',
        '提取到 ' . count($llmsLinks) . ' 条同源链接' . ($llmsLinks ? '，实测 ' . count(array_filter($llmsLinks, function ($u, $i) use ($fetched) { return (int)($fetched['link:' . $i]['status'] ?? 0) === 200; }, ARRAY_FILTER_USE_BOTH)) . ' 条返回 200' : ''),
        implode(' ⏎ ', array_slice($llmsLinks, 0, 3)),
        '用 `- [标题](绝对URL)` 列出核心文档，链接必须是可 200 访问的绝对地址');
    $llmsFresh = (bool)preg_match('/(最后更新|更新时间|updated|dateModified|20\d\d-\d\d-\d\d)/i', $llmsBody);
    $a[] = deep_item($llmsFresh ? 'ok' : 'warn', 'llms.txt 声明内容时效',
        $llmsFresh ? '含更新时间标注' : '未声明更新时间，模型无法判断资料新鲜度',
        ($llmsFresh && preg_match('/(最后更新[^\n]{0,40}|更新时间[^\n]{0,40})/u', $llmsBody, $fm)) ? trim($fm[1]) : '(无时间标注)',
        '在简介或文末注明「最后更新：YYYY-MM-DD」');
    preg_match_all('/<loc>/i', $sitemapBody, $locm);
    $urlCount = count($locm[0] ?? []);
    $a[] = deep_item($sitemapStatus === 200 && $urlCount > 0 ? 'ok' : 'warn', 'sitemap.xml 的 URL 数量',
        $sitemapStatus === 200 ? "共 $urlCount 条 URL" : '缺少可用的 sitemap.xml',
        mb_substr($sitemapBody, 0, 120),
        '把首页与全部知识/内容页列入 sitemap，新增页面时同步更新');
    preg_match_all('/<lastmod>/i', $sitemapBody, $lmm);
    $lmCount = count($lmm[0] ?? []);
    $a[] = deep_item($urlCount > 0 && $lmCount >= $urlCount ? 'ok' : 'warn', 'sitemap 的 lastmod 覆盖',
        "$lmCount / $urlCount 条 URL 带 lastmod",
        $lmCount ? trim(preg_replace('/^.*?(<lastmod>[^<]+<\/lastmod>).*$/is', '$1', $sitemapBody)) : '(无 lastmod)',
        '为每条 <url> 补 <lastmod>YYYY-MM-DD</lastmod>，便于爬虫判断更新优先级');
    $groups[] = ['key' => 'discovery', 'name' => 'A · 发现与指引文件', 'desc' => 'robots / llms.txt / sitemap 的质量，而非仅判断存在', 'items' => $a];

    // ================= B 组：结构化数据清单 =================
    $b = [];
    $b[] = deep_item($ld['count'] > 0 && $ld['errors'] === 0 ? 'ok' : ($ld['count'] > 0 ? 'warn' : 'bad'), 'JSON-LD 块与可解析性',
        "共 {$ld['count']} 块，解析失败 {$ld['errors']} 块",
        '类型：' . ($ld['types'] ? implode(' / ', array_slice($ld['types'], 0, 8)) : '(无)'),
        '确保每块 ld+json 都是合法 JSON，避免尾随逗号与未转义引号');
    $b[] = deep_item(count($ld['types']) >= 3 ? 'ok' : 'warn', '结构化数据类型覆盖',
        '覆盖 ' . count($ld['types']) . ' 种 Schema.org 类型',
        implode(' / ', array_slice($ld['types'], 0, 10)),
        '至少覆盖 Organization、WebSite，内容页补 Article/FAQPage/BreadcrumbList');
    $org = deep_node_of($ld['blocks'], 'Organization');
    $orgOk = $org && !empty($org[0]['name']) && !empty($org[0]['url']);
    $b[] = deep_item($org ? ($orgOk ? 'ok' : 'warn') : 'warn', 'Organization 必填字段',
        $org ? ($orgOk ? 'name / url 齐备' : '缺少 name 或 url') : '未提供 Organization（AI 难以建立品牌实体）',
        $org ? json_encode(array_intersect_key($org[0], array_flip(['name','url','logo'])), JSON_UNESCAPED_UNICODE) : '(无 Organization)',
        '添加 {"@type":"Organization","name":"品牌名","url":"https://域名/","logo":"…"}');
    $web = deep_node_of($ld['blocks'], 'WebSite');
    $webOk = $web && !empty($web[0]['name']) && !empty($web[0]['url']);
    $b[] = deep_item($web ? ($webOk ? 'ok' : 'warn') : 'warn', 'WebSite 必填字段',
        $web ? ($webOk ? 'name / url 齐备' : '缺少 name 或 url') : '未提供 WebSite',
        $web ? json_encode(array_intersect_key($web[0], array_flip(['name','url'])), JSON_UNESCAPED_UNICODE) : '(无 WebSite)',
        '添加 WebSite 节点，可带 SearchAction potentialAction');
    $articles = array_merge(deep_node_of($ld['blocks'], 'Article'), deep_node_of($ld['blocks'], 'BlogPosting'), deep_node_of($ld['blocks'], 'TechArticle'));
    $artOk = $articles && !empty($articles[0]['headline']) && !empty($articles[0]['datePublished']);
    $b[] = deep_item($articles ? ($artOk ? 'ok' : 'warn') : 'warn', 'Article 时效字段',
        $articles ? ($artOk ? 'headline / datePublished 齐备' : '文章节点缺 headline 或 datePublished') : '首页无 Article（内容页应单独标注）',
        $articles ? json_encode(array_intersect_key($articles[0], array_flip(['headline','datePublished','author'])), JSON_UNESCAPED_UNICODE) : '(无 Article)',
        '内容页补 Article：headline、datePublished、dateModified、author、publisher');
    $faqNodes = deep_node_of($ld['blocks'], 'FAQPage');
    $faqQn = 0;
    foreach ($faqNodes as $f) $faqQn += count((array)($f['mainEntity'] ?? []));
    $b[] = deep_item($faqQn >= 2 ? 'ok' : 'warn', 'FAQPage 问答条数',
        $faqNodes ? "mainEntity 共 $faqQn 条问答" : '未提供 FAQPage 结构化问答',
        $faqNodes ? json_encode(array_slice((array)($faqNodes[0]['mainEntity'] ?? []), 0, 1), JSON_UNESCAPED_UNICODE) : '(无 FAQPage)',
        '把 FAQ 写成 FAQPage/mainEntity，每条含 Question.name 与 Answer.text');
    $visibleQ = preg_match_all('/<details\b/i', $html) + preg_match_all('/<summary\b/i', $html);
    $faqConsistent = ($faqQn > 0 && $visibleQ > 0) || ($faqQn === 0 && $visibleQ === 0);
    $b[] = deep_item($faqConsistent ? 'ok' : 'warn', 'FAQ 标记与可见内容一致',
        $faqConsistent ? '结构化问答与页面可见问答相符' : '存在标记但页面无可见问答（或反之），可能被判为隐藏内容',
        "FAQPage 问答案 $faqQn 条 / 页面 details+summary $visibleQ 个",
        'JSON-LD 中的问答必须与页面真实可见的问答一一对应');
    $extra = array_values(array_intersect($ld['types'], ['BreadcrumbList','HowTo','Product','SoftwareApplication','Person']));
    $b[] = deep_item(count($extra) >= 2 ? 'ok' : 'warn', '补充类型（面包屑/操作指南等）',
        $extra ? '含 ' . implode(' / ', $extra) : '未使用 BreadcrumbList / HowTo 等增强类型',
        implode(' / ', $extra),
        '内容页加 BreadcrumbList，教程页加 HowTo，提升 AI 对页面关系的理解');
    $b[] = deep_item($ld['graphs'] > 0 ? 'ok' : 'warn', '@graph 组织多实体',
        $ld['graphs'] > 0 ? "使用 @graph 组织 {$ld['graphs']} 组实体" : '多个实体未用 @graph 归组',
        $ld['graphs'] ? '(已使用 @graph)' : '(未使用)',
        '多个实体用 @graph 一次声明，并用 @id 互相引用，避免实体分裂');
    $groups[] = ['key' => 'schema', 'name' => 'B · 结构化数据清单', 'desc' => '逐类型核对必填字段与一致性', 'items' => $b];

    // ================= C 组：实体一致性（GEO 关键） =================
    $c = [];
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) $title = trim(html_to_text($tm[1]));
    $ogTitle = deep_meta_content($html, 'property', 'og:title');
    $ogSite = deep_meta_content($html, 'property', 'og:site_name');
    $ogUrl = deep_meta_content($html, 'property', 'og:url');
    $ldName = '';
    foreach (['Organization','WebSite','SoftwareApplication'] as $ty) {
        $ns = deep_node_of($ld['blocks'], $ty);
        if ($ns && !empty($ns[0]['name'])) { $ldName = (string)$ns[0]['name']; break; }
    }
    $canon = '';
    if (preg_match('/<link\b[^>]*rel\s*=\s*["\']canonical["\'][^>]*>/i', $html, $cm)) $canon = deep_attr($cm[0], 'href');
    $h1s = [];
    if (preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1m)) foreach ($h1m[1] as $h) $h1s[] = trim(html_to_text($h));
    $titleMain = deep_brand_main($title);
    $h1Main = $h1s ? deep_brand_main($h1s[0]) : '';
    $c[] = deep_item($titleMain && $h1Main && (str_contains($titleMain, $h1Main) || str_contains($h1Main, $titleMain)) ? 'ok' : 'warn',
        'title 与 H1 主题一致',
        $h1s ? 'H1：' . mb_substr($h1s[0], 0, 40) : '页面缺少 H1',
        'title=' . mb_substr($title, 0, 60) . ' ｜ H1=' . mb_substr($h1s[0] ?? '(无)', 0, 60),
        '让 H1 与 title 表达同一主题，避免标题与正文主体错位');
    $c[] = deep_item($ogTitle && deep_brand_main($ogTitle) === $titleMain ? 'ok' : 'warn', 'og:title 与 title 一致',
        $ogTitle ? '已声明 og:title' : '缺少 og:title',
        $ogTitle ? 'og:title=' . mb_substr($ogTitle, 0, 60) : '(无 og:title)',
        'og:title 与 <title> 保持同一文案，避免社交/AI 抓到不同标题');
    $nameSources = array_filter(['title' => $titleMain, 'og:site_name' => deep_brand_main($ogSite), 'JSON-LD name' => deep_brand_main($ldName)]);
    $mainVals = array_unique(array_values($nameSources));
    $c[] = deep_item(count($mainVals) <= 1 ? 'ok' : 'warn', '品牌名多来源一致性',
        count($mainVals) <= 1 ? '各来源品牌名归一后一致' : '检测到 ' . count($mainVals) . ' 个不同的品牌名写法',
        'title=' . mb_substr($title, 0, 40) . ' ｜ og:site_name=' . mb_substr($ogSite, 0, 30) . ' ｜ ld+json name=' . mb_substr($ldName, 0, 30),
        'title / OG / JSON-LD / 页脚统一使用同一个品牌名；不要只靠 JS 运行时替换（爬虫不执行 JS）');
    $sameUrl = function ($a, $b) {
        $norm = function ($u) {
            $p = parse_url((string)$u);
            $host = preg_replace('#^www\.#i', '', (string)($p['host'] ?? ''));
            return $host . rtrim((string)($p['path'] ?? '/'), '/');
        };
        return $norm($a) !== '' && $norm($a) === $norm($b);
    };
    $c[] = deep_item($canon ? ($sameUrl($canon, $finalUrl) ? 'ok' : 'warn') : 'warn', 'canonical 与访问 URL 一致',
        $canon ? ($sameUrl($canon, $finalUrl) ? '规范化地址一致' : 'canonical 指向与当前 URL 不一致') : '缺少 canonical',
        'canonical=' . mb_substr($canon, 0, 70) . ' ｜ 访问=' . mb_substr($finalUrl, 0, 70),
        'canonical 必须是当前页面的绝对规范地址，且与 301 后的最终 URL 一致');
    $c[] = deep_item($ogUrl && $canon && $sameUrl($ogUrl, $canon) ? 'ok' : 'warn', 'og:url 与 canonical 一致',
        $ogUrl ? ($canon && $sameUrl($ogUrl, $canon) ? '两者一致' : 'og:url 与 canonical 不一致') : '缺少 og:url',
        'og:url=' . mb_substr($ogUrl, 0, 60) . ' ｜ canonical=' . mb_substr($canon, 0, 60),
        'og:url 与 canonical 填同一个绝对 URL');
    $lang = '';
    if (preg_match('/<html\b[^>]*lang\s*=\s*["\']([^"\']+)["\']/i', $html, $lgm)) $lang = trim($lgm[1]);
    preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text, $hanm);
    $hanRatio = mb_strlen($text) > 0 ? count($hanm[0]) / mb_strlen($text) : 0;
    $langOk = $lang && (($hanRatio > 0.1 && stripos($lang, 'zh') !== false) || ($hanRatio <= 0.1 && stripos($lang, 'zh') === false));
    $c[] = deep_item($langOk ? 'ok' : 'warn', 'lang 声明与正文语言相符',
        $lang ? 'lang=' . $lang . '，正文汉字占比 ' . round($hanRatio * 100) . '%' : '缺少 html lang 声明',
        'lang=' . $lang . ' ｜ 汉字占比=' . round($hanRatio * 100) . '%',
        '中文站点声明 lang="zh-CN"，避免 AI 误判内容语言');
    $c[] = deep_item(count($h1s) === 1 ? 'ok' : 'warn', 'H1 唯一性',
        '检测到 ' . count($h1s) . ' 个 H1',
        implode(' ｜ ', array_map(fn($x) => mb_substr($x, 0, 40), array_slice($h1s, 0, 3))),
        '每页只保留 1 个 H1，其余标题降级为 H2');
    $groups[] = ['key' => 'entity', 'name' => 'C · 实体一致性', 'desc' => '品牌名、标题、canonical、OG、语言是否指向同一事实', 'items' => $c];

    // ================= D 组：内容可引用性深挖 =================
    $d = [];
    $d[] = deep_item($q['ratio'] >= 20 ? 'ok' : 'warn', '可独立引用段落占比',
        "{$q['count']} / {$q['total']} 段（{$q['ratio']}%），平均段长 {$q['avg_len']} 字",
        '样本：' . mb_substr($q['samples'][0]['text'] ?? '(无)', 0, 90),
        '把结论写成 40–500 字、含数据且可独立成义的段落，避免大量「如上所述」');
    $d[] = deep_item($q['defs'] >= 2 ? 'ok' : 'warn', '定义型句子数量',
        "检测到 {$q['defs']} 段「X 是/指/定义为…」式定义句",
        $q['defs'] ? '(存在定义句)' : '(未检测到)',
        '用「GEO 是指…」「本平台面向…」等定义句开头，最易被 AI 直接摘引');
    $d[] = deep_item($q['num'] >= 3 ? 'ok' : 'warn', '含具体数字的段落数',
        "{$q['num']} 段含 2 个以上数字/年份/百分比",
        '(基于正文段落统计)',
        '在结论里给出可核验的数字（比例、年份、条数），提升被引用概率');
    $d[] = deep_item($q['src'] >= 2 ? 'ok' : 'warn', '含来源/时间/署名的段落数',
        "{$q['src']} 段带来源、作者、发布或更新字样",
        '(基于正文段落统计)',
        '关键结论标注数据来源与更新时间，增强 E-E-A-T 可信度');
    $qaPairs = (int)(preg_match_all('/<details\b/i', $html) + $faqQn);
    $d[] = deep_item($qaPairs >= 2 ? 'ok' : 'warn', 'FAQ 问答对数量',
        "页面可见问答 + 结构化问答共 $qaPairs 对",
        'details=' . preg_match_all('/<details\b/i', $html) . ' ｜ FAQPage mainEntity=' . $faqQn,
        '至少 3 组「问—答」并同步标记 FAQPage');
    $listN = preg_match_all('/<(table|ol|ul)\b/i', $html);
    $d[] = deep_item($listN >= 2 ? 'ok' : 'warn', '列表/表格结构化内容',
        "共 $listN 处表格或列表",
        '(基于标签统计)',
        '把对比、步骤、指标整理成表格或有序列表，便于 AI 提取');
    $timeVal = '';
    if (preg_match('/(datePublished|article:published_time|datetime=|发布时间|更新时间)[^0-9]{0,20}(20\d\d[-年.\/]\d{1,2}[-月.\/]\d{1,2})/iu', $html, $tvm)) $timeVal = $tvm[2];
    $d[] = deep_item($timeVal ? 'ok' : 'warn', '时间标注的具体取值',
        $timeVal ? '抓取到时间值：' . $timeVal : '只出现时间相关字样，未找到可读日期',
        $timeVal ?: '(无具体日期)',
        '用 <time datetime="YYYY-MM-DD"> 或 datePublished 写明真实日期');
    $authorVal = '';
    if (preg_match('/(author|作者|来源)[：:\s"\'<]{0,12}([\x{4e00}-\x{9fff}A-Za-z·]{2,20})/u', $html, $avm)) $authorVal = trim($avm[2]);
    $d[] = deep_item($authorVal ? 'ok' : 'warn', '作者/来源标注的具体取值',
        $authorVal ? '抓取到：' . $authorVal : '未找到明确的作者或来源名称',
        $authorVal ?: '(无明确署名)',
        '用 rel="author" 或 JSON-LD author 写明主体名称，不要只写「来源：网络」');
    $first = '';
    if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $fpm)) $first = trim(html_to_text($fpm[1]));
    $selfContained = mb_strlen($first) >= 40 && !preg_match('/(如上|上述|前文|这些|其[中次]|该[项页])/u', $first);
    $d[] = deep_item($selfContained ? 'ok' : 'warn', '首段自包含性',
        $selfContained ? '首段可独立理解' : '首段过短或依赖上文指代词',
        mb_substr($first, 0, 100),
        '首段直接给出「谁、做什么、关键结论」，不要以指代词开头');
    $d[] = deep_item($templateTraces === 0 ? 'ok' : 'bad', '正文中的前端模板痕迹',
        $templateTraces ? '检测到 ' . $templateTraces . ' 段 <p> 含未渲染的模板 / JS 拼接代码' : '正文段落均为已渲染的纯文本',
        $templateTraces ? "存在 '+ / \"+ / {{ }} / data.x 等拼接痕迹（共 $templateTraces 段）" : '(无模板痕迹)',
        'AI 爬虫多数不执行 JS，关键结论必须服务端渲染为纯文本，不要把模板字符串留在 <p> 里');
    $groups[] = ['key' => 'content', 'name' => 'D · 内容可引用性深挖', 'desc' => '段落级统计：定义句、事实密度、问答对、署名与时间', 'items' => $d];

    // ================= E 组：技术可抓取性 =================
    $e = [];
    $ct = (string)($hdr['content-type'] ?? '');
    $e[] = deep_item(stripos($ct, 'charset') !== false || stripos($html, 'charset=') !== false ? 'ok' : 'warn', '字符编码声明',
        $ct ?: '(响应头未返回 Content-Type)',
        'Content-Type: ' . $ct . ' ｜ HTML meta: ' . (preg_match('/<meta[^>]+charset\s*=\s*["\']([^"\']+)/i', $html, $chm) ? $chm[1] : '(无)'),
        '响应头或 <meta charset> 至少一处显式声明 UTF-8，避免中文被按 GBK 解码');
    $xr = (string)($hdr['x-robots-tag'] ?? '');
    $e[] = deep_item($xr && stripos($xr, 'noindex') === false ? 'ok' : ($xr ? 'bad' : 'warn'), 'X-Robots-Tag 响应头',
        $xr ?: '未设置（默认可索引）',
        'X-Robots-Tag: ' . ($xr ?: '(无)'),
        '首页可声明 index, follow, max-snippet:-1；后台页面必须 noindex');
    $cc = (string)($hdr['cache-control'] ?? '');
    $e[] = deep_item($cc ? 'ok' : 'warn', 'Cache-Control 策略',
        $cc ?: '未设置缓存头',
        'Cache-Control: ' . ($cc ?: '(无)'),
        '静态页设置 public, max-age；含动态诊断数据的页面用 no-cache');
    $enc = (string)($hdr['content-encoding'] ?? '');
    $e[] = deep_item($enc ? 'ok' : 'warn', '传输压缩',
        $enc ?: '未启用压缩',
        'Content-Encoding: ' . ($enc ?: '(无)'),
        '开启 gzip/br，降低 AI 爬虫抓取成本');
    $hsts = (string)($hdr['strict-transport-security'] ?? '');
    $e[] = deep_item($hsts ? 'ok' : 'warn', 'HSTS 安全传输',
        $hsts ?: '未设置 HSTS',
        'Strict-Transport-Security: ' . ($hsts ?: '(无)'),
        '全站 HTTPS 后添加 HSTS 响应头');
    $htmlBytes = strlen($html);
    $density = $htmlBytes > 0 ? (int)round(mb_strlen($text) / $htmlBytes * 100) : 0;
    $e[] = deep_item($density >= 8 ? 'ok' : 'warn', 'HTML 体积与文本密度',
        "HTML {$htmlBytes} 字节，正文 " . mb_strlen($text) . " 字，文本密度 {$density}%",
        '正文 ' . mb_substr($text, 0, 80),
        '文本密度过低说明大量脚本/样式，考虑服务端渲染关键内容');
    $scriptN = preg_match_all('/<script\b/i', $html);
    $csrRisk = (mb_strlen($text) < 200 && $scriptN >= 3) || $templateTraces > 0;
    $e[] = deep_item($csrRisk ? 'bad' : 'ok', 'CSR 空壳风险',
        $csrRisk ? ($templateTraces > 0 ? '正文含 ' . $templateTraces . ' 段未渲染模板痕迹，疑似 CSR/前端渲染' : '正文极短但脚本较多，疑似依赖 JS 渲染')
                 : '正文 ' . mb_strlen($text) . ' 字，脚本 ' . $scriptN . ' 个，服务端可见内容充足',
        'script=' . $scriptN . ' ｜ text=' . mb_strlen($text) . ' ｜ template_traces=' . $templateTraces,
        'AI 爬虫多数不执行 JS，关键结论必须直接出现在 HTML 源码中');
    preg_match_all('/<img\b[^>]*>/i', $html, $im);
    $imgN = count($im[0] ?? []); $altN = 0;
    foreach (($im[0] ?? []) as $tag) if (deep_attr($tag, 'alt') !== '') $altN++;
    $e[] = deep_item($imgN === 0 || $altN / max(1, $imgN) >= 0.8 ? 'ok' : 'warn', '图片 alt 覆盖率',
        "$altN / $imgN 张图片带 alt",
        '(基于 img 标签统计)',
        '为信息型图片补 alt，帮助 AI 理解图片语义');
    preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $am);
    $inner = 0; $outer = 0;
    foreach (($am[1] ?? []) as $h) {
        if (preg_match('#^https?://#i', $h)) { if (strpos($h, parse_url($origin, PHP_URL_HOST)) !== false) $inner++; else $outer++; }
        else $inner++;
    }
    $e[] = deep_item($inner >= 5 ? 'ok' : 'warn', '站内链接密度',
        "站内 $inner 条 / 站外 $outer 条",
        '(基于 a[href] 统计)',
        '正文内链到核心知识页，帮助爬虫发现与建立内容关联');
    $resp = (int)($home['time'] ?? 0);
    $e[] = deep_item($resp > 0 && $resp < 800 ? 'ok' : 'warn', '首页响应时间',
        $resp . ' ms',
        'HTTP ' . (int)($home['status'] ?? 0) . ' ｜ ' . $resp . 'ms',
        '首屏 TTFB 控制在 800ms 内，超时会显著降低爬虫抓取深度');
    $e[] = deep_item((int)($home['status'] ?? 0) === 200 ? 'ok' : 'warn', 'HTTP 状态与重定向',
        'HTTP ' . (int)($home['status'] ?? 0) . '，最终地址 ' . mb_substr($finalUrl, 0, 60),
        'Location: ' . (string)($hdr['location'] ?? '(无)'),
        '首页应直接 200，避免多级跳转损耗爬虫预算');
    $groups[] = ['key' => 'tech', 'name' => 'E · 技术可抓取性', 'desc' => '响应头、编码、压缩、CSR 风险、内链与性能', 'items' => $e];

    // ================= F 组：AI 爬虫实测矩阵 =================
    $f = []; $botMatrix = [];
    $baseSize = strlen((string)($home['body'] ?? ''));
    $baseStatus = (int)($home['status'] ?? 0);
    foreach ($botUas as $n => $ua) {
        $r = $fetched['bot:' . $n] ?? ['status' => 0, 'body' => '', 'time' => 0];
        $st = (int)($r['status'] ?? 0); $sz = strlen((string)($r['body'] ?? ''));
        $diff = $baseSize > 0 ? (int)round(abs($sz - $baseSize) / $baseSize * 100) : 0;
        $blocked = ($baseStatus < 400 && $st >= 400);
        $verdict = $st === 0 ? '请求失败' : ($blocked ? '疑似拦截' : ($diff > 30 ? '内容差异较大' : '与普通访问一致'));
        $botMatrix[] = ['name' => $n, 'status' => $st, 'size' => $sz, 'diff' => $diff, 'ms' => (int)($r['time'] ?? 0), 'verdict' => $verdict];
        $f[] = deep_item($st >= 200 && $st < 400 && !$blocked ? 'ok' : 'warn', "$n 实测可达",
            'HTTP ' . $st . '，返回 ' . $sz . ' 字节，与普通访问差异 ' . $diff . '%，' . $verdict,
            'UA=' . mb_substr($ua, 0, 70),
            $blocked ? '检查 WAF/CDN 的 Bot 管理规则，放行该合规 AI 爬虫' : '保持当前放行策略');
    }
    $consistent = count(array_filter($botMatrix, fn($x) => $x['status'] >= 200 && $x['status'] < 400));
    $f[] = deep_item($consistent === count($botMatrix) ? 'ok' : 'warn', 'AI 爬虫访问一致率',
        "$consistent / " . count($botMatrix) . " 个爬虫返回 2xx/3xx",
        implode(' ｜ ', array_map(fn($x) => $x['name'] . ':' . $x['status'], $botMatrix)),
        '对全部主流 AI 爬虫保持与浏览器一致的响应，不要按 UA 差分区分内容');
    $groups[] = ['key' => 'bots', 'name' => 'F · AI 爬虫实测可达性', 'desc' => '不只是解析 robots，逐个 UA 真实请求并比对内容差异', 'items' => $f];

    // ================= 全站覆盖矩阵 =================
    $pages = [];
    foreach ($innerUrls as $i => $u) $pages[] = deep_page_probe($u, $fetched['page:' . $i] ?? ['status' => 0, 'body' => '', 'time' => 0]);
    $cov = [];
    if ($pages) {
        $n = count($pages);
        $cov = [
            '可访问(200)' => count(array_filter($pages, fn($p) => $p['status'] === 200)),
            '有 title' => count(array_filter($pages, fn($p) => $p['title'] !== '')),
            '有 description' => count(array_filter($pages, fn($p) => $p['desc'] !== '')),
            '恰好 1 个 H1' => count(array_filter($pages, fn($p) => $p['h1'] === 1)),
            '有 canonical' => count(array_filter($pages, fn($p) => $p['canonical'] === '有')),
            '有 JSON-LD' => count(array_filter($pages, fn($p) => $p['jsonld'] !== '缺')),
            '有时间标注' => count(array_filter($pages, fn($p) => $p['datetime'] !== '缺')),
            '正文≥200字' => count(array_filter($pages, fn($p) => $p['words'] >= 200)),
            '有可引用段落' => count(array_filter($pages, fn($p) => $p['quotable'] >= 2)),
        ];
        foreach ($cov as $k => $v) $cov[$k] = ['ok' => $v, 'total' => $n, 'pct' => (int)round($v / $n * 100)];
    }

    // ================= 汇总统计 =================
    $all = [];
    foreach ($groups as $g) foreach ($g['items'] as $it) $all[] = $it;
    $ok = count(array_filter($all, fn($x) => $x['s'] === 'ok'));
    $warn = count(array_filter($all, fn($x) => $x['s'] === 'warn'));
    $bad = count(array_filter($all, fn($x) => $x['s'] === 'bad'));

    return [
        'version' => '1.0',
        'generated_at' => date('Y-m-d H:i:s'),
        'groups' => $groups,
        'bot_matrix' => $botMatrix,
        'coverage' => ['pages' => $pages, 'summary' => $cov, 'sitemap_urls' => $urlCount],
        'stats' => [
            'total' => count($all), 'ok' => $ok, 'warn' => $warn, 'bad' => $bad,
            'pass_rate' => count($all) ? (int)round($ok / count($all) * 100) : 0,
            'groups' => count($groups),
        ],
        'elapsed_ms' => (int)round((microtime(true) - $t0) * 1000),
    ];
}
