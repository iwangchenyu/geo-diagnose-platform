<?php
/**
 * 站点 SEO / GEO 资产统一生成器
 *
 * 单一数据源：settings 表
 *   - site_url        站点规范地址（根地址，含尾斜杠）—— 决定所有绝对 URL
 *   - platform_name   平台 / 产品名 —— 决定 title、OG、JSON-LD、llms.txt 里的品牌
 *   - platform_tagline 副标题
 *   - site_icp        ICP 备案号
 *
 * 覆盖范围（保存平台配置时自动触发 syncAllSeo()）：
 *   1) landing/index.html 的 <!-- SEO:BRAND_START/END --> 区块（seo_sync.php）
 *   2) seo/robots.txt、sitemap.xml、llms.txt、llms-full.txt（本文件权威模板渲染）
 *   3) landing/geo/**\/index.html 的 canonical / OG / twitter / JSON-LD / 品牌名（就地收敛）
 *
 * 设计原则：交付物仍是纯静态文件（Nginx 精确 location 直出，爬虫零 JS 依赖）；
 * 生成器只在后台保存或 CLI 时运行，原子写入、可重复执行（幂等）。
 *
 * CLI：php seo_generate.php --sync   （换域名后可在服务器一键重建）
 */

/* ============ 路径探测：兼容线上布局与本地仓库布局 ============ */
if (!function_exists('seoAssetRoot')) {
    function seoAssetRoot(): string {
        $cands = [
            dirname(__DIR__, 2) . '/landing/index.html',  // 线上：webroot/landing/index.html
            dirname(__DIR__, 1) . '/landing/index.html',  // 本地：仓库/landing/index.html
        ];
        foreach ($cands as $p) {
            if (is_file($p)) return dirname(dirname($p));
        }
        return dirname(__DIR__, 2);
    }
}

/* ============ 站点地址：校验 / 规范化 ============ */
if (!function_exists('siteUrlValidate')) {
    /**
     * 只接受「协议 + 域名/IP + 可选端口 + 可选尾斜杠」，拒绝路径、query、@、引号。
     * @return string|null 规范化后的根地址（带尾斜杠），非法返回 null
     */
    function siteUrlValidate(string $raw): ?string {
        $v = trim($raw);
        if ($v === '' || strlen($v) > 200) return null;
        if (preg_match('/[\s"\'<>@?#\\\\]/', $v)) return null;
        if (!preg_match('#^(https?)://([A-Za-z0-9\-._]+)(?::(\d{1,5}))?/?$#i', $v, $m)) return null;
        $scheme = strtolower($m[1]);
        $host   = rtrim(strtolower($m[2]), '.');
        $port   = isset($m[3]) && $m[3] !== '' ? ':' . (int)$m[3] : '';
        if ($host === '' || strpos($host, '..') !== false) return null;
        if ((int)($m[3] ?? 0) > 65535) return null;
        return $scheme . '://' . $host . $port . '/';
    }
}

if (!function_exists('seoVars')) {
    /** 模板占位符映射（调用方需已加载 config.php / db.php / seo_sync.php） */
    function seoVars(): array {
        $root  = seoBase();                       // 带尾斜杠
        $base  = rtrim($root, '/');              // 不带尾斜杠
        $host  = (string)parse_url($root, PHP_URL_HOST);
        return [
            '{{ROOT}}'   => $root,
            '{{BASE}}'   => $base,
            '{{HOST}}'   => $host,
            '{{BRAND}}'  => platformName(),
            '{{TAGLINE}}'=> platformTagline(),
            '{{ICP}}'    => trim(strip_tags((string)getSetting('site_icp', ''))),
            '{{DATE}}'   => date('Y-m-d'),
            '{{YEAR}}'   => date('Y'),
        ];
    }
}

/* ============ 四个文本 SEO 文件的权威模板 ============ */
if (!function_exists('seoTextTemplates')) {
    function seoTextTemplates(): array {
        $robots = <<<'TPL'
# {{BRAND}} · GEO 诊断平台 — {{ROOT}}
# 说明：本文件由站点自主提供，显式放行主流 AI / 生成式引擎爬虫。
# 诊断工作台、后台与 API 不供索引。
# 本文件由后台「站点地址与 SEO/GEO 文件」自动生成，请勿手工编辑。

# ---- 训练 / 检索 / 引用类 AI 爬虫：逐 UA 显式放行 ----
User-agent: GPTBot
Allow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Perplexity-User
Allow: /

User-agent: Bytespider
Allow: /

User-agent: CCBot
Allow: /

User-agent: Applebot-Extended
Allow: /

User-agent: Amazonbot
Allow: /

User-agent: Bingbot
Allow: /

User-agent: Baiduspider
Allow: /

# ---- 通用规则 ----
User-agent: *
Allow: /
Disallow: /diagnose/
Disallow: /app
Disallow: /build/
Disallow: /icons/
Disallow: /manifest.webmanifest

Sitemap: {{BASE}}/sitemap.xml
TPL;

        $sitemap = <<<'TPL'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{{ROOT}}</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{{BASE}}/geo/</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>{{BASE}}/geo/what-is-geo/</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>{{BASE}}/geo/llms-txt/</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>{{BASE}}/geo/ai-crawlers/</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>{{BASE}}/geo/brand-visibility/</loc>
    <lastmod>{{DATE}}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
TPL;

        $llms = <<<'TPL'
# {{BRAND}}

> {{BRAND}}是面向中文市场的 GEO（生成式引擎优化）诊断平台，提供两类服务：① 官网 GEO 诊断，检查 AI 爬虫能否访问、理解并引用官网内容；② 品牌 AI 可见度分析，测量品牌在主流大模型回答中的提及率、排名与竞品格局，并输出按行业定制的可执行优化方案。平台同时生成可白标、可分享、可导出 PDF 的诊断报告。官网：{{ROOT}} ；诊断工作台：{{BASE}}/diagnose/ （需登录，不供索引）。

本文件遵循 llms.txt 约定，用于帮助大语言模型快速理解本站结构与可信内容来源。内容语言为简体中文（zh-CN），最后更新 {{DATE}}。引用本站内容时请以本文件与下列知识文档为准。

## 平台能力

- [官网 GEO 诊断]({{BASE}}/#start): 四层依赖链评分——可访问性（robots.txt、AI 爬虫 UA 差分探测、WAF/CDN 拦截）、导航指引（llms.txt、sitemap.xml、canonical、语言声明）、可理解性（title、meta description、结构化数据、标题层级、正文密度）、可引用性（自包含段落、FAQ、表格与列表、时间标注、作者/来源标注）。评分之外另附 6 组 50+ 项深度细查：AI 爬虫逐 UA 真实请求（GPTBot / ClaudeBot / PerplexityBot / Bingbot 并发并比对内容差异）、robots / llms.txt / sitemap 质量校验、结构化数据逐类型核对必填字段、品牌名与 canonical / OG / lang 实体一致性、段落级可引用性统计、响应头与 CSR 模板痕迹检查、全站覆盖矩阵。每项附证据片段与修复建议；深度细查不计入四层总分。
- [品牌 AI 可见度分析]({{BASE}}/#start): 以「核心词 × 意图模板」生成问题词，在多个大模型平台采样回答，统计品牌提及率、Top1 / Top3 排名、竞品共现与高频信源；支持别名归一化与否定语境识别，失败样本不进入分母。结果按 12 个行业包生成 P0–P2 问题清单、四条工作流、90 天排期与 KPI。
- 白标报告与分享: 每个客户可配置 Logo、主题色、标语、联系方式与页脚；报告通过随机令牌免登录访问，可轮换令牌、设置有效期、续期，支持 A4 打印与 PDF 导出。
- 额度与数据隔离: 新用户注册赠送免费诊断额度，支持邀请奖励与兑换码；客户数据与额度相互隔离，管理员可按客户分配配额。

## 诊断口径（可直接引用的事实定义）

- 官网 GEO 得分由四层加权得出：可访问性、导航指引、可理解性、可引用性，满分 100。得分仅供优化参考，不代表搜索引擎或大模型官方评价。
- 深度细查与评分解耦：四层总分口径稳定、可跨报告比较；深度细项单独统计通过率（通过 / 待优化 / 问题），用于定位具体问题并提供可复核的证据与修复建议。
- 平台检查 8 类主流 AI 爬虫：GPTBot、OAI-SearchBot、Google-Extended、ClaudeBot、PerplexityBot、Bytespider、CCBot、Bingbot；深度细查会对其中 GPTBot、ClaudeBot、PerplexityBot、Bingbot 用真实 UA 并发请求首页并比对内容差异。
- 品牌提及率 = 提及该品牌的成功样本数 ÷ 成功样本总数。请求失败的样本跳过，不补零、不计入分母。
- Top1 指品牌在回答中位列首个推荐；Top3 指品牌出现在回答前三位推荐序列中。
- 内页抽样来自 sitemap.xml 的 loc 列表：四层审计抽 3 页做基础检测；深度诊断抽最多 5 页，每页检测 title、description、H1、canonical、JSON-LD 类型、时间标注、正文字数、可引用段落数与响应时间，并汇总为全站覆盖率矩阵。
- 诊断方法为纯抓取分析，不调用搜索引擎排名接口，也不获取任何非公开数据。

## 知识文档

- [什么是 GEO：生成式引擎优化]({{BASE}}/geo/what-is-geo/): GEO 的定义、与 SEO 的区别、四层依赖链、可引用内容的写法与常见误区。
- [llms.txt 是什么，怎么写]({{BASE}}/geo/llms-txt/): 文件规范、推荐结构、可复制模板、与 robots.txt / sitemap.xml 的分工，以及本站自身示例。
- [主流 AI 爬虫清单与 robots.txt 配置]({{BASE}}/geo/ai-crawlers/): 8 类 AI 爬虫 UA、用途差异、逐 UA 放行写法与自检方法。
- [品牌 AI 可见度指标口径]({{BASE}}/geo/brand-visibility/): 提及率、Top1 / Top3、竞品共现、信源分析的定义与计算方式。
- [知识文档索引]({{BASE}}/geo/): 全部公开文档目录。

## 常见问题

- 免费额度可用于哪些功能？官网 GEO 诊断与品牌 AI 可见度分析各消耗 1 次；查看历史报告、生成分享链接、导出 PDF 不额外消耗。
- 报告链接需要登录吗？不需要，凭随机令牌访问；可在工作台轮换令牌让旧链接失效。
- 演示模式与真实 API 模式有什么区别？演示模式复现真实报告结构；真实模式调用已配置的大模型接口采样，未配置 Key 时自动降级为演示模式。
- 数据是否与其他客户隔离？是的，每个客户只能查看自己的报告。

## 不供索引的区域

- /diagnose/ 为登录后的诊断工作台、后台与 API，已声明 noindex、nofollow，请勿作为公开内容引用。
- /diagnose/report.php 为客户私有报告分享页，同样不供索引。
- /app、/build/、/icons/、/manifest.webmanifest 属于同域下的其他应用资源，与本站内容无关。

## 联系与合规

- 网站：{{ROOT}}
- 平台名称：{{BRAND}}
- 内容语言：简体中文（zh-CN）
- 最后更新：{{DATE}}
TPL;

        $llmsFull = <<<'TPL'
# {{BRAND}} · 完整站点说明（llms-full.txt）

> 本文件是 llms.txt 的完整版，供大语言模型一次性读取本站的公开事实、定义、口径与文档内容。最后更新：{{DATE}}。内容语言：简体中文（zh-CN）。运营主体品牌：{{BRAND}}。官网：{{ROOT}} 。

## 1. 站点实体信息

| 字段 | 值 |
| --- | --- |
| 平台名称 | {{BRAND}} |
| 站点地址 | {{ROOT}} |
| 内容语言 | 简体中文（zh-CN） |
| 服务类别 | GEO（生成式引擎优化）诊断、品牌 AI 可见度分析 |
| 公开内容入口 | /（首页）、/geo/（知识文档）、/llms.txt、/sitemap.xml、/robots.txt |
| 非公开区域 | /diagnose/（工作台、后台、API、报告分享页，已 noindex） |
| 最后更新 | {{DATE}} |

## 2. 一句话定义

{{BRAND}}是一个检查「AI 能否读到、读懂并引用你的官网内容」以及「AI 回答里有没有你的品牌」的诊断平台，输出可白标、可分享、可打印为 PDF 的报告与按行业定制的优化方案。

## 3. 首页核心论述（可引用段落）

- 官网 GEO 体检查的是「AI 能不能读到你的内容」：爬虫放行、结构化数据、可引用段落、内页抽样。品牌可见度分析测的是「AI 回答里有没有你」：多平台采样、提及率、竞品格局，并按行业给出可执行的优化方案。
- GEO 与 SEO 的目标不同：SEO 争取的是搜索结果页的排名与点击，GEO 争取的是被大模型读取、理解并在生成回答时作为依据引用。因此 GEO 更关注内容是否自包含、是否含明确事实与数据、是否有清晰的时间与来源标注。
- 可引用内容的核心特征是「脱离上下文仍成立」：一段话自带主语、结论、条件与数据，不依赖「如上所述」「这个功能」等指代词，AI 可以整段摘用而不产生歧义。
- 诊断结果基于公开网页抓取与大语言模型采样，仅供优化参考，不构成任何商业承诺、投资建议或搜索引擎/大模型官方评价。

## 4. 官网 GEO 诊断：四层依赖链与检查项

### 第一层 · 可访问性（Access）
检查目标站点是否允许 AI 爬虫读取。包含：主页 HTTP 状态与响应时间、HTTPS 是否启用、robots.txt 是否存在、逐 UA 解析 8 类主流 AI 爬虫的放行规则、AI 爬虫与普通浏览器 UA 的差分探测（识别 WAF/CDN 对 AI 爬虫的静默拦截）。

### 第二层 · 导航指引（Orientation）
检查 AI 能否找到并定位重要内容。包含：llms.txt 是否存在且符合 Markdown 结构、sitemap.xml 是否可访问并列出内页、canonical 是否声明、页面语言是否声明。

### 第三层 · 可理解性（Understanding）
检查页面语义是否清晰。包含：title、meta description、Open Graph、JSON-LD 结构化数据、H1 唯一性与标题层级、正文文本密度。

### 第四层 · 可引用性（Quotability）
检查内容能否被 AI 整段引用。包含：自包含事实段落数量、FAQ 问答结构、表格与列表、内容时间标注（datePublished / dateModified）、作者或来源标注、内页抽样正文完整度。

评分按四层加权汇总为 100 分制，并输出 P0/P1/P2 级问题清单与修复建议。

### 深度细查（与评分解耦，不计入总分）
在四层评分之外，每次诊断还输出 6 组 50+ 项深度细查，每项附证据片段与修复建议，单独统计通过率：A 发现与指引文件——robots 指令、AI 爬虫显式放行、后台路径屏蔽、llms.txt 的 H1 / 简介 / 链接可达性 / 时效声明、sitemap URL 数与 lastmod 覆盖；B 结构化数据清单——JSON-LD 可解析性、逐类型必填字段（Organization / WebSite / Article / FAQPage）、FAQPage 与可见问答一致性、@graph 组织；C 实体一致性——title / H1 / OG / JSON-LD / 品牌名归一比对、canonical 与最终 URL、og:url、lang 与正文语言；D 内容可引用性深挖——可引用段落占比、定义句、事实密度、问答对、署名与时间取值、首段自包含、前端模板痕迹；E 技术可抓取性——charset、X-Robots-Tag、缓存、压缩、HSTS、文本密度、CSR 空壳风险、alt 覆盖、内外链、响应时间；F AI 爬虫实测——GPTBot / ClaudeBot / PerplexityBot / Bingbot 用真实 UA 并发请求首页并比对内容差异。内页抽样升级为全站覆盖矩阵：最多 5 页、每页 10 项指标，并汇总各细项覆盖率。

## 5. 品牌 AI 可见度分析：指标口径

- 问题词生成：核心词 × 意图模板（推荐、对比、口碑、价格、怎么选、哪家好等），默认生成 15 个意图问句。
- 采样：在多个大模型平台对每个问题词发起请求，保存原始回答样本用于追溯。
- 品牌提及率 = 提及该品牌的成功样本数 ÷ 成功样本总数。请求失败或超时的样本跳过，不补零、不计入分母，避免低估或虚高。
- Top1：品牌在回答中位列首个推荐。Top3：品牌出现在回答前三位推荐序列中。
- 竞品共现：统计同一回答中与品牌共同出现的其他品牌，形成竞品格局。
- 高频信源：统计回答引用的来源域名，判断应优先建设哪些渠道的内容。
- 别名归一化与否定语境识别：把品牌简称、全称、英文名归一到同一实体；识别「不推荐」「避雷」等否定语境，避免把负面提及算作正向曝光。

## 6. 行业化方案结构

可见度分析不再只给评分，而是按 12 个行业包（含本地生活、家装、教育培训、医疗健康、企业服务、金融、电商零售、法律咨询、旅游、制造业、软件 SaaS、房产）与通用包生成执行方案，包含：执行摘要、行业 AI 搜索特征、现状诊断、P0–P2 问题清单、四条工作流（技术放行 / 内容建设 / 信源建设 / 复测迭代）、90 天排期、KPI、合规红线与附录。

## 7. 报告与平台能力

- 白标：每个客户可配置 Logo、主题色、标语、联系方式与页脚，报告自动套用品牌。
- 分享：随机令牌免登录访问；可一键轮换令牌使旧链接立即失效；可设置有效期并续期。
- 导出：A4 打印友好，支持另存为 PDF；报告页支持亮/暗主题，打印时强制浅色。
- 额度：新用户注册赠送免费诊断额度，邀请好友与兑换码可续用；查看历史报告、生成分享链接、导出 PDF 不额外消耗额度。
- 隔离：客户只能查看自己的报告；管理员可按客户分配配额、维护品牌配置。
- 趋势：每次诊断保留完整结果，支持周/月复测对比品牌提及率与 GEO 得分变化。

## 8. 知识文档内容摘要

### 8.1 什么是 GEO：生成式引擎优化
GEO（Generative Engine Optimization）指让品牌与内容更容易被生成式 AI 引擎读取、理解并在回答中引用的优化方法。它与 SEO 共享技术基础（可抓取、语义清晰、结构化数据），但目标从「排名与点击」变为「被引用与被推荐」。可操作框架为四层依赖链：可访问性 → 导航指引 → 可理解性 → 可引用性。常见误区包括：只做关键词堆砌而忽略自包含段落、用 JS 动态注入关键元信息导致爬虫看到旧内容、屏蔽 CSS/JS 或 AI 爬虫 UA、缺少时间与来源标注、把营销口号当作可引用事实。

### 8.2 llms.txt 是什么，怎么写
llms.txt 是放在站点根目录的纯 Markdown 文件，用简洁的标题、简介与链接列表帮助大语言模型快速理解站点结构与可信内容来源。推荐结构：一个 H1 站点名、一段 blockquote 简介、若干 H2 分区（能力、文档、常见问题、不供索引区域、联系与合规），每条链接用 `- [标题](URL): 说明` 形式。它不替代 robots.txt（控制抓取权限）与 sitemap.xml（列出可抓取 URL），三者分工不同。进阶可提供 llms-full.txt 作为完整版。本站自身在 {{BASE}}/llms.txt 提供了可直接参考的实例。

### 8.3 主流 AI 爬虫清单与 robots.txt 配置
需要显式放行的 8 类爬虫：GPTBot（OpenAI 训练抓取）、OAI-SearchBot（OpenAI 搜索索引）、Google-Extended（Gemini / AI Overviews 用途）、ClaudeBot（Anthropic）、PerplexityBot（Perplexity 检索）、Bytespider（字节/豆包）、CCBot（Common Crawl，众多模型训练语料来源）、Bingbot（Bing / Copilot）。建议逐 UA 写 `Allow: /`，而不是只依赖 `User-agent: *` 的默认允许；同时用 `Disallow` 屏蔽后台、API 与私有目录，并在文件末尾声明 Sitemap。自检方法：用自家官网 GEO 诊断跑一次，查看「AI 爬虫 robots 规则」是否 8/8 通过。

### 8.4 品牌 AI 可见度指标口径
见第 5 节。关键在于失败样本不补零、别名归一化、否定语境识别，以及保留原始回答样本以便追溯。

## 9. 常见问题（完整）

- 免费额度可以用在哪些功能？官网 GEO 诊断和品牌可见度分析各消耗 1 次额度。查看历史报告、生成分享链接、导出 PDF 不额外消耗额度。
- 怎么邀请好友获得奖励？登录工作台进入「邀请有礼」，复制专属邀请链接或邀请码；好友通过链接注册成功后，奖励次数自动加到剩余额度中。
- 演示模式和真实 API 模式有什么区别？演示模式基于真实报告结构复现，用于快速了解分析维度；真实 API 模式调用已配置的大模型接口采样，需在服务器配置 Key，未配置时自动降级为演示模式。
- 报告链接需要登录才能看吗？不需要，凭随机令牌访问；可在工作台随时刷新令牌让旧链接失效，也可设置链接有效期并续期。
- 数据是和其他客户隔离的吗？是的，每个客户只能查看自己的报告，管理员也需要指定客户才能查看对应数据。
- GEO 与 SEO 有什么区别？SEO 优化搜索结果排名与点击，GEO 优化被生成式引擎读取、理解与引用；两者共享可抓取性与结构化数据基础，但 GEO 更强调自包含事实段落、时间与来源标注、实体一致性。
- 为什么需要 llms.txt？它给大模型一份人工整理的站点地图与可信内容入口，减少模型在大量导航与脚本中定位正文的成本。
- 诊断得分是官方评价吗？不是。得分基于公开抓取与规则计算，仅供优化参考。

## 10. 不供索引与免责

- /diagnose/、/diagnose/report.php、/app、/build/、/icons/、/manifest.webmanifest 不作为本站公开内容，请勿引用。
- 报告样例链接可能随令牌轮换而失效，请以首页当前链接为准。
- 本站诊断结果仅供优化参考，不构成商业承诺、投资建议或任何官方评价。使用诊断功能时，用户应确保对目标站点拥有相应授权。
TPL;

        return [
            'robots.txt'     => $robots . "\n",
            'sitemap.xml'   => $sitemap . "\n",
            'llms.txt'       => $llms . "\n",
            'llms-full.txt'  => $llmsFull . "\n",
        ];
    }
}

/* ============ 原子写入 ============ */
if (!function_exists('seoAtomicWrite')) {
    function seoAtomicWrite(string $path, string $content): bool {
        $dir = dirname($path);
        // 覆盖文件靠「目录可写」即可（rename 语义），不要求目标文件本身可写
        if (!is_dir($dir) || !is_writable($dir)) return false;
        $owner = @fileowner($path);   // 记录原属主，供 root CLI 执行后恢复
        $group = @filegroup($path);
        $tmp = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) return false;
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        @chmod($path, 0644);
        // CLI 常以 root 运行，rename 后新文件属主会变成 root，导致 php-fpm(www) 之后不可写；恢复之
        if ($owner !== false && $group !== false && function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($path, $owner);
            @chgrp($path, $group);
        }
        return true;
    }
}

/* ============ 渲染四个文本 SEO 文件 ============ */
if (!function_exists('renderSeoTextFiles')) {
    function renderSeoTextFiles(array $vars): array {
        $root  = seoAssetRoot();
        $dir   = $root . '/seo';
        $files = []; $errors = [];
        foreach (seoTextTemplates() as $name => $tpl) {
            $path = $dir . '/' . $name;
            $out  = strtr($tpl, $vars);
            if (seoAtomicWrite($path, $out)) {
                $files[] = 'seo/' . $name;
            } else {
                $errors[] = 'seo/' . $name . ' 写入失败（检查目录权限）';
            }
        }
        return ['files' => $files, 'errors' => $errors];
    }
}

/* ============ 渲染五个 GEO 知识页 ============ */
if (!function_exists('renderGeoPages')) {
    /**
     * 就地收敛：把 head 中所有「本站 origin」替换为当前 site_url 的 origin，
     * 把 head/body 中的旧品牌名替换为当前平台名，并同步正文里的裸域名文案。
     * 不改动文章发布时间等与配置无关的内容，正文可继续人工编辑。
     */
    function renderGeoPages(array $vars): array {
        $root    = seoAssetRoot();
        $newRoot = rtrim($vars['{{ROOT}}'], '/');
        $newHost = $vars['{{HOST}}'];
        $newBrand = $vars['{{BRAND}}'];
        $keepHosts = [
            'schema.org', 'www.schema.org', 'w3.org', 'www.w3.org',
            'purl.org', 'json-schema.org', 'sitemaps.org', 'www.sitemaps.org',
            'developers.google.com', 'www.google.com',
        ];
        $replaceOrigin = function (string $s) use ($newRoot, $keepHosts): string {
            return preg_replace_callback('#https?://[A-Za-z0-9\-._]+(?::\d{1,5})?#i', function ($m) use ($newRoot, $keepHosts) {
                $u    = $m[0];
                $host = preg_replace('#^https?://#i', '', $u);
                $host = preg_replace('#:\d+$#', '', $host);
                if (in_array(strtolower($host), $keepHosts, true)) return $u;
                return $newRoot;
            }, $s);
        };

        $pages = [
            ''                    => $root . '/landing/geo/index.html',
            'what-is-geo/'        => $root . '/landing/geo/what-is-geo/index.html',
            'llms-txt/'           => $root . '/landing/geo/llms-txt/index.html',
            'ai-crawlers/'        => $root . '/landing/geo/ai-crawlers/index.html',
            'brand-visibility/'  => $root . '/landing/geo/brand-visibility/index.html',
        ];
        $files = []; $errors = [];
        foreach ($pages as $slug => $path) {
            if (!is_file($path) || !is_writable(dirname($path))) {
                $errors[] = 'landing/geo/' . $slug . 'index.html 不存在或目录不可写'; continue;
            }
            $html = (string)file_get_contents($path);
            $pos  = stripos($html, '</head>');
            if ($pos === false) { $errors[] = 'landing/geo/' . $slug . ' 缺少 </head>'; continue; }
            $head = substr($html, 0, $pos);
            $body = substr($html, $pos);

            // 品牌锚点：优先从文件自身提取（即使 settings 记录丢失也能收敛）
            $cands = [];
            if (preg_match('/property="og:site_name" content="([^"]+)"/', $head, $mm)) $cands[] = $mm[1];
            if (preg_match('/name="author" content="([^"]+)"/', $head, $mm)) $cands[] = $mm[1];
            $cands[] = (string)getSetting('seo_last_brand', '');
            $cands[] = defined('APP_NAME') ? APP_NAME : '';
            $cands[] = '蓝蜻蜓';
            $cands = array_values(array_unique(array_filter($cands, function ($c) { return mb_strlen($c) >= 2; })));
            usort($cands, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });

            // 旧域名 host（用于正文裸域名文案）
            $oldHosts = [];
            if (preg_match('#https?://([A-Za-z0-9\-._]+)(?::\d+)?/#', $head, $hm)) $oldHosts[] = $hm[1];
            $lastBase = trim((string)getSetting('seo_last_base', ''));
            if ($lastBase !== '' && preg_match('#https?://([A-Za-z0-9\-._]+)#', $lastBase, $hm2)) $oldHosts[] = $hm2[1];
            $oldHosts[] = 'old-domain.example'; // TODO: 换域名时填写旧域名；留空由 head 自动推断
            $oldHosts = array_values(array_unique(array_filter($oldHosts)));

            $head = $replaceOrigin($head);
            $body = $replaceOrigin($body);
            foreach ($cands as $old) {
                if ($old === $newBrand) continue;
                $head = str_replace($old, $newBrand, $head);
                $body = str_replace($old, $newBrand, $body);
            }
            foreach ($oldHosts as $oh) {
                if ($oh === $newHost || $oh === '') continue;
                // 正文里的裸域名文案（如 example.com/llms.txt），绝对 URL 已由 replaceOrigin 处理
                $body = str_replace($oh . '/llms.txt', $newHost . '/llms.txt', $body);
            }

            if (seoAtomicWrite($path, $head . $body)) {
                $files[] = 'landing/geo/' . $slug . 'index.html';
            } else {
                $errors[] = 'landing/geo/' . $slug . 'index.html 写入失败';
            }
        }
        return ['files' => $files, 'errors' => $errors];
    }
}

/* ============ 编排：全量同步 ============ */
if (!function_exists('syncAllSeo')) {
    /**
     * 后台保存平台配置 / 换域名时调用。
     * @return array{ok:bool, files:string[], errors:string[], site_url:string, brand:string}
     */
    function syncAllSeo(): array {
        require_once __DIR__ . '/seo_sync.php';
        $vars   = seoVars();
        $files  = []; $errors = [];

        // 1) 落地页 head 品牌区块 + data-brand + ICP
        $landing = syncLandingSeo();
        if (!empty($landing['ok'])) $files[] = 'landing/index.html';
        else $errors[] = 'landing/index.html：' . ($landing['reason'] ?? '同步失败');

        // 2) 四个文本 SEO 文件
        $r = renderSeoTextFiles($vars);
        $files = array_merge($files, $r['files']); $errors = array_merge($errors, $r['errors']);

        // 3) 五个知识页
        $r2 = renderGeoPages($vars);
        $files = array_merge($files, $r2['files']); $errors = array_merge($errors, $r2['errors']);

        // 4) 记录本次值，供下次收敛作为锚点
        setSetting('seo_last_base',  $vars['{{ROOT}}']);
        setSetting('seo_last_brand', $vars['{{BRAND}}']);

        return [
            'ok'       => count($errors) === 0,
            'files'    => $files,
            'errors'   => $errors,
            'site_url' => $vars['{{ROOT}}'],
            'brand'    => $vars['{{BRAND}}'],
        ];
    }
}

/* ============ CLI ============ */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/db.php';
    }
    require_once __DIR__ . '/seo_sync.php';
    $r = syncAllSeo();
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit($r['ok'] ? 0 : 1);
}
