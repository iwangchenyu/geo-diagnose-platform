<?php
/**
 * 官网 GEO 诊断引擎
 * 四层依赖链：Access → Orientation → Understanding → Quotability
 * 纯抓取分析，无需大模型 API
 */
require_once __DIR__ . '/lib.php';

// AI 爬虫 UA 列表（用于差分探测与 robots 检查）
const AI_BOTS = [
    'GPTBot'          => 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)',
    'OAI-SearchBot'   => 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)',
    'Google-Extended' => 'Mozilla/5.0 (compatible; Google-Extended)',
    'ClaudeBot'       => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
    'PerplexityBot'   => 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai)',
    'Bytespider'      => 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)',
    'CCBot'           => 'Mozilla/5.0 (compatible; CCBot/2.0; +https://commoncrawl.org/faq/)',
    'Bingbot'         => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
];

/**
 * 解析 robots.txt 对给定 UA 的允许状态
 */
function parseRobots($robotsTxt, $uaToken, $path = '/') {
    if (trim($robotsTxt) === '') return ['state' => 'allow', 'rule' => '无 robots.txt（默认允许）'];
    $lines = preg_split('/\r?\n/', $robotsTxt);
    $uaGroups = [];
    $curUas = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
            $curUas[] = trim($m[1]);
            continue;
        }
        if (preg_match('/^(Allow|Disallow):\s*(.*)$/i', $line, $m)) {
            foreach ($curUas as $ua) {
                $uaGroups[$ua][] = [strtolower($m[1]), trim($m[2])];
            }
            continue;
        }
        // 新段落（遇到非 user-agent/规则行如 Sitemap）
        if (!preg_match('/^(Sitemap|Crawl-delay|Host):/i', $line)) {
            $curUas = [];
        }
    }
    // 匹配：先精确，再通配符 *
    $rules = null;
    if (isset($uaGroups[$uaToken])) $rules = $uaGroups[$uaToken];
    elseif (isset($uaGroups['*'])) $rules = $uaGroups['*'];
    else return ['state' => 'allow', 'rule' => '无对应规则（默认允许）'];

    // 取最具体的路径匹配
    $decision = 'allow'; $matched = ''; $maxLen = -1;
    foreach ($rules as [$act, $p]) {
        if ($p === '') continue;
        if (strpos($path, $p) === 0) {
            if (strlen($p) > $maxLen) { $maxLen = strlen($p); $decision = $act; $matched = $p; }
        }
    }
    return [
        'state' => $decision,
        'rule' => $decision === 'disallow' ? "被规则 Disallow: $matched 拦截" : "允许（最长匹配规则: " . ($matched ?: '无') . "）",
    ];
}

/**
 * 从 sitemap.xml 抽取内页做轻量抽样（最多 N 页，短超时）
 * 检测每个内页的可访问性、正文长度、结构化数据、canonical、H1
 */
function crawlInnerPages($origin, $sitemapBody, $limit = 3) {
    $urls = [];
    if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', (string)$sitemapBody, $m)) {
        foreach ($m[1] as $u) {
            $u = trim(html_entity_decode($u, ENT_QUOTES, 'UTF-8'));
            if (!preg_match('#^https?://#i', $u)) continue;
            if (rtrim($u, '/') === rtrim($origin, '/')) continue;
            $urls[] = $u;
            if (count($urls) >= $limit) break;
        }
    }
    $out = [];
    foreach ($urls as $u) {
        $r = http_fetch($u, ['timeout' => 8]);
        $html = (string)$r['body'];
        $out[] = [
            'url' => $u,
            'status' => (int)$r['status'],
            'words' => mb_strlen(html_to_text($html)),
            'has_jsonld' => (bool)preg_match('/application\/ld\+json/i', $html),
            'has_canonical' => (bool)preg_match('/rel=["\']canonical/i', $html),
            'has_h1' => (bool)preg_match('/<h1\b/i', $html),
            'response_ms' => (int)$r['time'],
        ];
    }
    return $out;
}

/**
 * 执行完整官网 GEO 审计
 */
function runAudit($url) {
    $url = safe_url($url);
    if (!$url) return ['error' => 'URL 无效或不允许访问内网地址'];
    $host = parse_url($url, PHP_URL_HOST);
    $origin = parse_url($url, PHP_URL_SCHEME) . '://' . $host;

    $result = [
        'url' => $url,
        'domain' => host_of($url),
        'checked_at' => date('Y-m-d H:i:s'),
        'layers' => [],
        'issues' => [],
        'score' => 0,
        'grade' => '',
    ];

    // ===== 抓取主页（普通浏览器 UA）=====
    $home = http_fetch($url, ['timeout' => 15]);
    $result['meta'] = [
        'http_status' => $home['status'],
        'response_ms' => $home['time'],
        'final_url' => $home['headers']['location'] ?? $url,
        'is_https' => stripos($url, 'https://') === 0,
        'has_hsts' => isset($home['headers']['strict-transport-security']),
        'server' => $home['headers']['server'] ?? '',
    ];

    // ===== 抓取 robots.txt =====
    $robots = http_fetch($origin . '/robots.txt', ['timeout' => 8]);
    $robotsTxt = ($robots['status'] === 200) ? $robots['body'] : '';

    // ================= 第 1 层：Access 可访问性 =================
    $access = ['score' => 0, 'max' => 100, 'checks' => []];
    $pts = 0;

    // HTTP 200
    if ($home['status'] >= 200 && $home['status'] < 400) { $pts += 25; $access['checks'][] = ok('主页可正常访问', "HTTP {$home['status']}，响应 {$home['time']}ms"); }
    else { $access['checks'][] = bad('主页访问异常', "HTTP {$home['status']} — {$home['error']}"); $result['issues'][] = ['level'=>'critical','layer'=>'可访问性','text'=>'主页无法正常访问（HTTP '.$home['status'].'）','fix'=>'检查服务器状态、域名解析与 nginx 配置']; }

    // HTTPS
    if ($result['meta']['is_https']) { $pts += 10; $access['checks'][] = ok('已启用 HTTPS', '使用加密连接'); }
    else { $access['checks'][] = warn('未启用 HTTPS', '当前为 HTTP'); $result['issues'][] = ['level'=>'high','layer'=>'可访问性','text'=>'站点未使用 HTTPS','fix'=>'部署 SSL 证书，AI 与搜索引擎更倾向可信来源']; }

    // HSTS
    if ($result['meta']['has_hsts']) { $pts += 5; $access['checks'][] = ok('已配置 HSTS', '传输安全增强'); }

    // robots 对 AI 爬虫 —— 逐 bot 检查
    $botResults = [];
    foreach (AI_BOTS as $name => $ua) {
        $rule = parseRobots($robotsTxt, $name, '/');
        $botResults[$name] = $rule;
    }
    $blocked = array_filter($botResults, fn($r) => $r['state'] === 'disallow');
    $allowed = count(AI_BOTS) - count($blocked);
    if ($allowed >= count(AI_BOTS) * 0.7) { $pts += 30; $access['checks'][] = ok('robots.txt 对 AI 爬虫基本开放', "$allowed/" . count(AI_BOTS) . " 个主流 AI 爬虫未被拦截"); }
    elseif ($allowed > 0) { $pts += 15; $access['checks'][] = warn('部分 AI 爬虫被 robots 拦截', count($blocked) . " 个被拦截：" . implode('、', array_keys($blocked))); }
    else { $access['checks'][] = bad('robots.txt 拦截了几乎所有 AI 爬虫', implode('、', array_keys($blocked))); }

    // WAF/CDN 差分探测：用 GPTBot UA 再请求一次，对比状态码
    $botHome = http_fetch($url, ['timeout' => 12, 'ua' => AI_BOTS['GPTBot']]);
    if ($home['status'] < 400 && $botHome['status'] >= 400) {
        $access['checks'][] = bad('AI 爬虫被 WAF/CDN 拦截', "普通浏览器 {$home['status']}，GPTBot {$botHome['status']} —— 存在 UA 差分封锁");
        $result['issues'][] = ['level'=>'critical','layer'=>'可访问性','text'=>'WAF/CDN 对 AI 爬虫返回不同结果（浏览器 '.$home['status'].' / GPTBot '.$botHome['status'].'）','fix'=>'检查 CDN/WAF 的 Bot 管理规则，放行 GPTBot、ClaudeBot 等合规 AI 爬虫'];
    } elseif ($home['status'] < 400 && $botHome['status'] < 400) {
        $pts += 20; $access['checks'][] = ok('AI 爬虫与普通用户访问一致', '无 UA 差分封锁，WAF/CDN 放行正常');
    } else {
        $access['checks'][] = warn('差分探测受限', "GPTBot 响应 {$botHome['status']}");
    }

    // robots.txt 存在性
    if ($robots['status'] === 200) { $pts += 10; $access['checks'][] = ok('存在 robots.txt', '已正确配置'); }
    else { $access['checks'][] = warn('无 robots.txt', '建议添加以明确 AI 爬虫规则'); }

    $access['score'] = min(100, $pts);
    $result['layers']['access'] = $access;
    $result['bot_access'] = $botResults;

    // ================= 第 2 层：Orientation 导航指引 =================
    $orient = ['score' => 0, 'max' => 100, 'checks' => []];
    $pts = 0;

    // llms.txt
    $llms = http_fetch($origin . '/llms.txt', ['timeout' => 8]);
    if ($llms['status'] === 200 && stripos($llms['body'], '#') !== false) {
        $pts += 40; $orient['checks'][] = ok('存在 llms.txt', strlen($llms['body']) . ' 字节，符合 Markdown 结构');
    } elseif ($llms['status'] === 200) {
        $pts += 20; $orient['checks'][] = warn('llms.txt 结构存疑', '存在但缺少标准 Markdown 标题');
        $result['issues'][] = ['level'=>'medium','layer'=>'导航指引','text'=>'llms.txt 不符合常见 Markdown 结构','fix'=>'按 llms.txt 规范提供 H1 站点名、简介与关键链接列表'];
    } else {
        $orient['checks'][] = warn('缺少 llms.txt', 'AI 缺少快速理解站点的指引文件');
        $result['issues'][] = ['level'=>'medium','layer'=>'导航指引','text'=>'未发现 llms.txt','fix'=>'在根目录添加 llms.txt，列出站点简介、核心页面与内容优先级，帮助 LLM 快速定位可信内容'];
    }
    $result['llms_txt'] = $llms['status'] === 200 ? mb_substr($llms['body'], 0, 600) : null;

    // sitemap.xml
    $sitemap = http_fetch($origin . '/sitemap.xml', ['timeout' => 8]);
    if ($sitemap['status'] === 200) { $pts += 25; $orient['checks'][] = ok('存在 sitemap.xml', '便于爬虫发现内容'); }
    else { $orient['checks'][] = warn('缺少 sitemap.xml', '建议添加'); $result['issues'][] = ['level'=>'low','layer'=>'导航指引','text'=>'未发现 sitemap.xml','fix'=>'生成并提交 sitemap.xml，提升内容被发现的概率']; }

    // canonical
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*>/i', $home['body'])) { $pts += 20; $orient['checks'][] = ok('存在 canonical 标签', '规范化 URL 已声明'); }
    else { $orient['checks'][] = warn('缺少 canonical 标签', '可能出现重复内容'); }

    // 语言声明
    if (preg_match('/<html[^>]+lang=/i', $home['body'])) { $pts += 15; $orient['checks'][] = ok('已声明页面语言', 'lang 属性存在'); }
    else { $orient['checks'][] = warn('缺少 lang 属性', '不利于多语言理解'); }

    $orient['score'] = min(100, $pts);
    $result['layers']['orientation'] = $orient;

    // ================= 第 3 层：Understanding 可理解性 =================
    $under = ['score' => 0, 'max' => 100, 'checks' => []];
    $pts = 0;
    $html = $home['body'];

    // title
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) && mb_strlen(trim(strip_tags($m[1]))) > 0) {
        $pts += 12; $under['checks'][] = ok('存在 title', mb_substr(trim(strip_tags($m[1])), 0, 60));
    } else { $under['checks'][] = warn('缺少 title'); }

    // meta description（兼容属性任意顺序，避免误报）
    $descVal = '';
    if (preg_match_all('/<meta\b[^>]*>/i', $html, $metaTags)) {
        foreach ($metaTags[0] as $mtag) {
            if (preg_match('/name=["\']description["\']/i', $mtag) && preg_match('/content=["\'](.*?)["\']/is', $mtag, $dm)) {
                $descVal = trim($dm[1]); break;
            }
        }
    }
    if (mb_strlen($descVal) > 20) {
        $pts += 12; $under['checks'][] = ok('存在 meta description', mb_substr($descVal, 0, 60));
    } else { $under['checks'][] = warn('缺少或过短的 meta description'); }

    // JSON-LD 结构化数据
    $jsonLdCount = preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $ldMatches);
    $ldTypes = [];
    if ($jsonLdCount) {
        foreach ($ldMatches[1] as $ld) {
            $d = json_decode(trim($ld), true);
            if (!$d) continue;
            $types = isset($d['@type']) ? [$d['@type']] : (isset($d['@graph']) ? array_column($d['@graph'], '@type') : []);
            $ldTypes = array_merge($ldTypes, (array)$types);
        }
    }
    if ($jsonLdCount > 0) {
        $pts += 30; $under['checks'][] = ok('存在 JSON-LD 结构化数据', "$jsonLdCount 处，类型：" . implode('/', array_slice($ldTypes, 0, 5)));
    } else {
        $under['checks'][] = bad('缺少 JSON-LD 结构化数据', 'AI 难以结构化理解实体信息');
        $result['issues'][] = ['level'=>'high','layer'=>'可理解性','text'=>'未发现 JSON-LD 结构化数据','fix'=>'添加 Organization、WebSite、Article、FAQPage 等 Schema.org JSON-LD，帮助 AI 准确识别品牌与内容实体'];
    }
    $result['structured_data'] = $ldTypes;

    // Open Graph
    if (preg_match('/<meta[^>]+property=["\']og:/i', $html)) { $pts += 10; $under['checks'][] = ok('存在 Open Graph 标签'); }
    else { $under['checks'][] = warn('缺少 Open Graph 标签'); }

    // 语义化标题层级
    preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1s);
    preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $h2s);
    $h1n = count($h1s[1]); $h2n = count($h2s[1]);
    if ($h1n === 1 && $h2n >= 1) { $pts += 20; $under['checks'][] = ok('标题层级合理', "1 个 H1，$h2n 个 H2"); }
    elseif ($h1n >= 1) { $pts += 10; $under['checks'][] = warn('标题层级可优化', "$h1n 个 H1，$h2n 个 H2（建议每页 1 个 H1）"); }
    else { $under['checks'][] = bad('缺少 H1/H2 语义标题'); }

    // 正文篇幅
    $text = html_to_text($html);
    $wordCount = mb_strlen($text);
    if ($wordCount >= 500) { $pts += 16; $under['checks'][] = ok('正文内容充实', "约 $wordCount 字"); }
    elseif ($wordCount >= 200) { $pts += 8; $under['checks'][] = warn('正文内容偏少', "约 $wordCount 字"); }
    else { $under['checks'][] = bad('正文内容过少', "约 $wordCount 字"); }

    $under['score'] = min(100, $pts);
    $under['word_count'] = $wordCount;
    $result['layers']['understanding'] = $under;

    // ================= 第 4 层：Quotability 可引用性 =================
    $quote = ['score' => 0, 'max' => 100, 'checks' => []];
    $pts = 0;

    // 抽取段落，检测"可独立引用段落"
    preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $pMatches);
    $quotable = [];
    foreach ($pMatches[1] as $p) {
        $pt = trim(html_to_text($p));
        $len = mb_strlen($pt);
        if ($len < 40 || $len > 500) continue;
        // 含具体事实特征：数字/年份/百分比/引号
        $facts = preg_match_all('/\d/', $pt);
        $hasQuote = preg_match('/[""]|说|指出|表示|数据显示|据统计/u', $pt);
        $sentences = preg_match_all('/[。！？.!?]/', $pt);
        if ($facts >= 2 || $hasQuote || $sentences >= 2) {
            $quotable[] = ['text' => mb_substr($pt, 0, 160), 'len' => $len, 'facts' => $facts, 'sentences' => $sentences];
        }
    }
    $qCount = count($quotable);
    if ($qCount >= 5) { $pts += 35; $quote['checks'][] = ok('存在较多可独立引用段落', "检测到 $qCount 段含事实/数据的自包含段落"); }
    elseif ($qCount >= 2) { $pts += 20; $quote['checks'][] = warn('可引用段落较少', "检测到 $qCount 段"); }
    else { $quote['checks'][] = bad('缺少可独立引用段落', 'AI 倾向于整段摘取，缺少自包含的事实段落'); $result['issues'][] = ['level'=>'medium','layer'=>'可引用性','text'=>'缺少可被 AI 直接引用的自包含段落','fix'=>'将关键结论写成 40-500 字、含具体数据且可独立成义的段落，避免大量指代上文'];}
    $result['quotable_paragraphs'] = array_slice($quotable, 0, 5);

    // FAQ 结构（收紧口径：需有真实问答载体，避免页面出现 faq 字样即得分）
    $faqLd = in_array('FAQPage', $ldTypes, true);
    $faqDetails = preg_match_all('/<details\b/i', $html) >= 2;
    $faqBlock = preg_match('/id=["\']faq["\']/i', $html) && preg_match('/<(summary|dt)\b/i', $html);
    if ($faqLd || $faqDetails || $faqBlock) {
        $pts += 20; $quote['checks'][] = ok('存在 FAQ 内容', $faqLd ? '含 FAQPage 结构化数据' : '检测到问答式结构（details/summary）');
    } else { $quote['checks'][] = warn('缺少 FAQ 结构', '建议增加带 FAQPage 标记或 details/summary 的问答内容'); }

    // 作者/来源/时间标注
    $hasTime = preg_match('/(datePublished|article:published_time|datetime=|发布时间|更新时间)/i', $html);
    $hasAuthor = preg_match('/(author|作者|来源)/i', $html);
    if ($hasTime) { $pts += 20; $quote['checks'][] = ok('标注内容时间', '利于 AI 判断新鲜度'); }
    else { $quote['checks'][] = warn('缺少内容时间标注'); }
    if ($hasAuthor) { $pts += 15; $quote['checks'][] = ok('标注作者/来源', '增强可信度'); }
    else { $quote['checks'][] = warn('缺少作者/来源标注'); }

    // 表格/列表等结构化内容
    if (preg_match('/<(table|ol|ul)\b/i', $html)) { $pts += 10; $quote['checks'][] = ok('存在表格/列表', '结构化数据易被提取'); }

    $quote['score'] = min(100, $pts);
    $result['layers']['quotability'] = $quote;

    // ===== 综合评分（按依赖链加权）=====
    $weights = ['access' => 0.3, 'orientation' => 0.2, 'understanding' => 0.25, 'quotability' => 0.25];
    $total = 0;
    foreach ($weights as $k => $w) $total += $result['layers'][$k]['score'] * $w;
    $result['score'] = (int)round($total);
    $result['grade'] = grade_of($result['score']);

    // ===== 内页抽样（sitemap 驱动，最多 3 页）=====
    $result['inner_pages'] = ($sitemap['status'] === 200)
        ? crawlInnerPages($origin, $sitemap['body'] ?? '', 3)
        : [];
    if ($result['inner_pages']) {
        $weak = array_filter($result['inner_pages'], fn($p) => !$p['has_jsonld'] || $p['words'] < 200);
        if (count($weak) > 0) {
            $result['issues'][] = [
                'level' => 'medium', 'layer' => '可理解性',
                'text' => '内页抽样：' . count($weak) . '/' . count($result['inner_pages']) . ' 个内页缺少结构化数据或正文过短',
                'fix' => '为内页统一补充 JSON-LD（Article/FAQPage）与可独立引用的正文段落，避免只有首页可被 AI 引用',
            ];
        }
    }

    // 依赖链提示：底层不通，上层意义有限
    if ($result['layers']['access']['score'] < 60) {
        $result['chain_warning'] = '可访问性得分偏低（' . $result['layers']['access']['score'] . '），AI 爬虫可能无法抓取内容，上层优化效果会大打折扣。请优先解决访问层问题。';
    }

    // ===== 深度诊断（独立区块，不计入四层总分；异常不影响主流程与历史分数可比）=====
    try {
        require_once __DIR__ . '/deep_audit.php';
        $result['deep'] = runDeepAudit([
            'url' => $url,
            'origin' => $origin,
            'home' => $home,
            'robots' => $robots,
            'sitemap' => $sitemap,
            'llms' => $llms,
            'html' => $html,
            'text' => $text,
            'word_count' => $wordCount,
            'final_url' => $result['meta']['final_url'] ?? $url,
            'inner_pages' => $result['inner_pages'],
        ]);
    } catch (Throwable $e) {
        $result['deep'] = ['error' => '深度诊断未完成：' . $e->getMessage()];
    }

    return $result;
}

function ok($t, $d = '') { return ['s' => 'ok', 't' => $t, 'd' => $d]; }
function warn($t, $d = '') { return ['s' => 'warn', 't' => $t, 'd' => $d]; }
function bad($t, $d = '') { return ['s' => 'bad', 't' => $t, 'd' => $d]; }

function grade_of($score) {
    if ($score >= 85) return ['label' => '优秀', 'color' => '#1D9E75'];
    if ($score >= 70) return ['label' => '良好', 'color' => '#378ADD'];
    if ($score >= 55) return ['label' => '及格', 'color' => '#BA7517'];
    if ($score >= 40) return ['label' => '偏弱', 'color' => '#D85A30'];
    return ['label' => '待优化', 'color' => '#E24B4A'];
}
