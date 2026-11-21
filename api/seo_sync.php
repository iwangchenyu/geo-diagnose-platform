<?php
/**
 * 落地页 SEO 品牌区块同步
 *
 * 背景：落地页是静态 HTML，此前品牌名靠前端 JS 调用 public_config 运行时替换
 * title / og / JSON-LD —— 不执行 JS 的 AI 爬虫会看到旧品牌名，造成实体不一致。
 * 本文件在后台保存「平台名称 / 副标题 / ICP」后，把 head 中的品牌区块与
 * body 中的 data-brand 文本原子回写到静态 HTML，保证爬虫看到的一定是最新实体。
 */
if (!function_exists('seoLandingPath')) {
    /** 线上布局：webroot/landing/index.html；本地仓库布局：仓库根/landing/index.html */
    function seoLandingPath(): string {
        $cands = [
            dirname(__DIR__, 2) . '/landing/index.html',
            dirname(__DIR__, 1) . '/landing/index.html',
        ];
        foreach ($cands as $p) {
            if (is_file($p)) return $p;
        }
        return $cands[0];
    }
}

if (!function_exists('seoBase')) {
    /**
     * 站点对外规范地址（含尾斜杠）——全站 SEO/GEO 文件的唯一数据源。
     * 优先级：后台 settings.site_url > config.php 常量 SEO_BASE_URL > 内置默认值。
     * 后台「平台配置 → 站点地址与 SEO/GEO 文件」保存时会校验并写入 site_url。
     */
    function seoBase(): string {
        $v = trim((string)getSetting('site_url', ''));
        if ($v !== '' && preg_match('#^https?://[A-Za-z0-9\-._]+(?::\d{1,5})?/$#', $v)) {
            return $v;
        }
        return defined('SEO_BASE_URL') ? rtrim(SEO_BASE_URL, '/') . '/' : 'https://your-domain.example/';
    }
}

if (!function_exists('seoBrandBlock')) {
    /** 生成 head 中可整体替换的品牌区块（含 title/description/OG/实体 JSON-LD） */
    function seoBrandBlock(string $name, string $tagline, string $modified): string {
        $base = seoBase();
        $e = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $title   = $e . ' · 官网 GEO 诊断与品牌 AI 可见度分析平台';
        $ogTitle = $e . ' · 让品牌在 AI 回答中被看见';
        $desc    = '官网 GEO 诊断与品牌 AI 可见度分析平台：四层评分加 50+ 项深度细查，多平台采样品牌提及率与竞品格局，输出可白标报告与按行业定制的 GEO 执行方案。';
        $ogDesc  = $tagline !== '' ? htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') . '，注册即享免费额度。'
                                   : '官网 GEO 诊断与品牌 AI 可见度分析，注册即享免费额度。';
        $ld = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization', '@id' => $base . '#organization',
                    'name' => $name, 'url' => $base,
                    'logo' => ['@type' => 'ImageObject', 'url' => $base . 'apple-touch-icon.png', 'width' => 180, 'height' => 180],
                    'description' => '官网 GEO 诊断与品牌 AI 可见度分析平台',
                    'knowsAbout' => ['GEO', '生成式引擎优化', 'AI 可见度', 'llms.txt', '结构化数据', 'robots.txt'],
                    'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'url' => $base . 'diagnose/', 'availableLanguage' => 'zh-CN'],
                ],
                [
                    '@type' => 'WebSite', '@id' => $base . '#website',
                    'name' => $name, 'url' => $base, 'inLanguage' => 'zh-CN',
                    'publisher' => ['@id' => $base . '#organization'],
                    'datePublished' => '2026-11-20', 'dateModified' => $modified,
                ],
                [
                    '@type' => 'SoftwareApplication', '@id' => $base . '#app',
                    'name' => $name, 'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web', 'url' => $base,
                    'description' => '官网 GEO 诊断与品牌 AI 可见度分析平台，输出可白标的诊断报告',
                    'publisher' => ['@id' => $base . '#organization'],
                    'offers' => ['@type' => 'Offer', 'description' => '新用户注册赠送免费诊断额度'],
                ],
            ],
        ];
        $ldJson = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $h = "<!-- SEO:BRAND_START（本区块由后台「平台配置」自动同步回写，请勿手工编辑） -->\n";
        $h .= "<title>{$title}</title>\n";
        $h .= "<meta name=\"description\" content=\"{$desc}\">\n";
        $h .= "<meta name=\"robots\" content=\"index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1\">\n";
        $h .= "<meta name=\"author\" content=\"{$e}\">\n";
        $h .= "<meta name=\"publisher\" content=\"{$e}\">\n";
        $h .= "<meta name=\"copyright\" content=\"{$e}\">\n";
        $h .= "<link rel=\"canonical\" href=\"{$base}\">\n";
        $h .= "<meta property=\"og:site_name\" content=\"{$e}\">\n";
        $h .= "<meta property=\"og:locale\" content=\"zh_CN\">\n";
        $h .= "<meta property=\"og:type\" content=\"website\">\n";
        $h .= "<meta property=\"og:url\" content=\"{$base}\">\n";
        $h .= "<meta property=\"og:title\" content=\"{$ogTitle}\">\n";
        $h .= "<meta property=\"og:description\" content=\"{$ogDesc}\">\n";
        $h .= "<meta property=\"og:image\" content=\"{$base}og-image.png\">\n";
        $h .= "<meta property=\"og:image:width\" content=\"1200\">\n";
        $h .= "<meta property=\"og:image:height\" content=\"630\">\n";
        $h .= "<meta property=\"og:image:alt\" content=\"{$e} — 让品牌在 AI 回答中被看见、被理解、被引用\">\n";
        $h .= "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $h .= "<meta name=\"twitter:title\" content=\"{$ogTitle}\">\n";
        $h .= "<meta name=\"twitter:description\" content=\"{$ogDesc}\">\n";
        $h .= "<meta name=\"twitter:image\" content=\"{$base}og-image.png\">\n";
        $h .= "<script type=\"application/ld+json\">{$ldJson}</" . "script>\n";
        $h .= "<!-- SEO:BRAND_END -->";
        return $h;
    }
}

if (!function_exists('seoReplaceDataBrand')) {
    /** 替换 body 中 <tag data-brand="key">文本</tag> 的文本内容 */
    function seoReplaceDataBrand(string $html, string $key, string $val): string {
        $re = '/(<[^>]*data-brand="' . preg_quote($key, '/') . '"[^>]*>).*?(<\/)/s';
        return preg_replace($re, '${1}' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '${2}', $html);
    }
}

if (!function_exists('syncLandingSeo')) {
    /**
     * 将当前平台设置同步到落地页静态 HTML。原子写入（tmp + rename）。
     * @return array{ok:bool, reason?:string}
     */
    function syncLandingSeo(): array {
        $name    = platformName();
        $tagline = platformTagline();
        $icp     = trim(strip_tags((string)getSetting('site_icp', '')));
        $file    = seoLandingPath();
        if (!is_file($file) || !is_writable(dirname($file))) {
            return ['ok' => false, 'reason' => 'landing 文件不存在或目录不可写'];
        }
        $html = file_get_contents($file);
        if ($html === false || strpos($html, '<!-- SEO:BRAND_START') === false) {
            return ['ok' => false, 'reason' => '品牌区块标记缺失'];
        }
        $block = seoBrandBlock($name, $tagline, date('Y-m-d'));
        $new = preg_replace('/<!-- SEO:BRAND_START.*?<!-- SEO:BRAND_END -->/s', $block, $html, 1, $cnt);
        if ($cnt !== 1 || $new === null) {
            return ['ok' => false, 'reason' => '品牌区块替换失败'];
        }
        $new = seoReplaceDataBrand($new, 'name', $name);
        $new = seoReplaceDataBrand($new, 'tagline', $tagline);
        $ne = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $new = preg_replace('/aria-label="[^"]*首页"/u', 'aria-label="' . $ne . '首页"', $new);
        $new = preg_replace('/(<span id="siteIcp"[^>]*>).*?(<\/span>)/s', '${1}' . htmlspecialchars($icp, ENT_QUOTES, 'UTF-8') . '${2}', $new, 1);
        $owner = @fileowner($file);
        $group = @filegroup($file);
        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $new, LOCK_EX) === false) {
            return ['ok' => false, 'reason' => '临时文件写入失败'];
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return ['ok' => false, 'reason' => '原子替换失败'];
        }
        @chmod($file, 0644);
        // CLI 以 root 执行时恢复原属主，避免 php-fpm(www) 后续不可写
        if ($owner !== false && $group !== false && function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($file, $owner);
            @chgrp($file, $group);
        }
        return ['ok' => true];
    }
}
