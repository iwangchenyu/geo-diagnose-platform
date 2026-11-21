<?php
/**
 * 品牌可见度分析引擎
 *  - demo：基于真实 23geo 报告结构的演示模式（零成本）
 *  - live：多问题 × 多平台并发采样矩阵，单样本超时跳过、失败不补零
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** 问题词生成：核心词 × 意图模板（借鉴 GrowMind 逻辑） */
function generateQuestions($industry, $brand) {
    $coreWords = array_filter(array_map('trim', explode(',', str_replace('，', ',', $industry))));
    if (empty($coreWords)) $coreWords = [$industry];
    $templates = [
        '{w}哪家性价比高？',
        '{w}选哪家靠谱？有什么推荐？',
        '{w}公司推荐哪些？',
    ];
    $questions = [];
    foreach ($coreWords as $w) {
        foreach ($templates as $t) {
            $questions[] = str_replace('{w}', $w, $t);
        }
    }
    return $questions;
}

/** live 采样参数（后台可调） */
function liveSettings() {
    $s = getSettings([
        'live_question_limit' => '5',
        'live_max_samples'    => '30',
        'live_timeout'        => '15',
    ]);
    return [
        'question_limit' => max(1, min(15, (int)$s['live_question_limit'])),
        'max_samples'    => max(1, min(100, (int)$s['live_max_samples'])),
        'timeout'        => max(5, min(45, (int)$s['live_timeout'])),
    ];
}

/** 构造 OpenAI 兼容请求 */
function buildSampleRequest($question, $platform, $timeout) {
    $keys = getApiKeys();
    $meta = platformMeta()[$platform] ?? null;
    if (!$meta || empty($keys[$platform])) return null;
    $prompt = "请基于你掌握的信息回答以下问题。如果涉及具体公司或品牌，请按推荐顺序列出，并使用「1. 2. 3.」这样的有序编号。\n\n问题：" . $question;
    return [
        'url'  => rtrim($meta['base'], '/') . '/chat/completions',
        'body' => json_encode([
            'model' => $meta['model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 800,
        ], JSON_UNESCAPED_UNICODE),
        'headers' => ['Authorization: Bearer ' . $keys[$platform]],
        'timeout' => $timeout,
    ];
}

/**
 * 并发 POST（curl_multi），每个请求独立超时；整体有硬截止时间。
 * 返回与 $requests 同索引的 [status, body, error]
 */
function http_post_json_multi($requests) {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $i => $req) {
        if (empty($req['url'])) { $handles[$i] = null; continue; }
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $req['body'],
            CURLOPT_TIMEOUT => $req['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
            // 官方 API：强制校验证书，防止中间人窃取 Key
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $req['headers'] ?? []),
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    $deadline = microtime(true) + 60;
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            $sel = curl_multi_select($mh, 1.0);
            if ($sel === -1) usleep(100000); // 避免忙等
        }
    } while ($running > 0 && $status === CURLM_OK && microtime(true) < $deadline);

    $out = [];
    foreach ($handles as $i => $ch) {
        if ($ch === null) { $out[$i] = ['status' => 0, 'body' => '', 'error' => '未配置 Key']; continue; }
        $out[$i] = [
            'status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body'   => (string)curl_multi_getcontent($ch),
            'error'  => curl_error($ch),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** 单样本（兼容旧调用） */
function http_post_json($url, $body, $headers = []) {
    $r = http_post_json_multi([['url' => $url, 'body' => $body, 'headers' => $headers, 'timeout' => 45]]);
    return $r[0];
}

/** 品牌命中判定：别名归一化 + 否定语境 */
function detectHit($text, $brand) {
    $nt = norm_text($text);
    $nb = norm_text($brand);
    if ($nb === '') return ['hit' => false, 'count' => 0, 'negative' => false, 'aliases' => []];
    $count = substr_count($nt, $nb);
    $aliases = [$brand];
    // 精确名未命中时，用「核心别名」兜底：连续剥掉公司类后缀与常见尾词
    // 例：王师傅装修工程有限公司 → 王师傅装修，可命中「王师傅装修队」
    if ($count === 0) {
        $core = preg_replace('/(有限责任|股份有限|有限|工程|装饰|装潢|科技|网络|信息|集团|公司|工作室|中心|队|店|厂)+$/u', '', $brand);
        if ($core && $core !== $brand && mb_strlen($core) >= 3) {
            $nc = norm_text($core);
            $c2 = substr_count($nt, $nc);
            if ($c2 > 0) { $count += $c2; $aliases[] = $core; }
        }
    }
    $negative = false;
    if ($count > 0) {
        foreach (['不推荐', '不建议', '谨慎', '避雷', ' avoid', '差评', '投诉', '问题'] as $neg) {
            $pos = mb_strpos($text, $neg);
            if ($pos !== false) {
                $near = mb_substr($text, max(0, $pos - 60), 160);
                if (mb_strpos($near, $brand) !== false) { $negative = true; break; }
            }
        }
    }
    return ['hit' => $count > 0, 'count' => $count, 'negative' => $negative, 'aliases' => $aliases];
}

/**
 * 品牌在 AI 回答有序推荐列表中的排名（Top1/Top3 口径）
 * 识别「1. / 1、 / 1) / ① / 第一名 / - 」等编号，返回 1-based 名次；无法判定返回 null
 */
function brandRank($text, $brand, $aliases = []) {
    $names = array_filter(array_unique(array_merge([$brand], $aliases ?: [])));
    $normNames = array_map('norm_text', $names);
    $lines = preg_split('/\r?\n/u', (string)$text);
    $rank = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $isItem = preg_match('/^(?:\d{1,2}\s*[\.、\)）]|[①-⑳]|第[一二三四五六七八九十]+\s*[名位个]|[-*•]\s+)\s*(.+)$/u', $line, $m);
        if (!$isItem) continue;
        $rank++;
        $nt = norm_text($m[1]);
        foreach ($normNames as $n) {
            if ($n !== '' && mb_strpos($nt, $n, 0, 'UTF-8') !== false) return $rank;
        }
    }
    return null;
}

/** 演示模式数据（源自真实 23geo 报告 + 穿透分析发现） */
function demoResult($brand, $industry, $question, $platforms) {
    $pname = platformMeta();
    $platformList = [];
    $rates = ['deepseek' => 33.3, 'qwen' => 33.3, 'doubao' => 0, 'wenxin' => 0, 'yuanbao' => 0];
    foreach ($platforms as $p) {
        $meta = $pname[$p] ?? ['name' => $p];
        $platformList[] = [
            'id' => $p, 'name' => $meta['name'],
            'mention_rate' => $rates[$p] ?? 0,
            'samples' => 3, 'hits' => (int)round(($rates[$p] ?? 0) / 100 * 3),
        ];
    }
    $activeRates = array_map(fn($p) => $p['mention_rate'], $platformList);
    $avgRate = $activeRates ? round(array_sum($activeRates) / count($activeRates), 1) : 0;

    $competitors = [
        ['name' => '绿庭装饰', 'mentions' => 32], ['name' => '兴唐装饰', 'mentions' => 26],
        ['name' => '鑫友装饰', 'mentions' => 17], ['name' => '城市人家', 'mentions' => 11],
        ['name' => '峰光无限', 'mentions' => 10],
    ];
    $sources = [
        ['domain' => 'k.sina.cn', 'count' => 4], ['domain' => 'xaxtzs.com（兴唐自建站）', 'count' => 3],
        ['domain' => 'xingtangzx.com（兴唐自建站）', 'count' => 2], ['domain' => 'taroostudio.com（绿庭）', 'count' => 2],
        ['domain' => 'xa.gov.cn', 'count' => 2], ['domain' => 'zhihu.com', 'count' => 2],
        ['domain' => 'to8to.com（土巴兔）', 'count' => 2],
    ];
    return [
        'mode' => 'demo', 'brand' => $brand, 'industry' => $industry, 'question' => $question,
        'score' => $avgRate,
        'metrics' => [
            'mention_rate' => $avgRate, 'top1_rate' => 0, 'top3_rate' => 0,
            'total_samples' => count($platforms) * 3, 'valid_samples' => count($platforms) * 3,
        ],
        'platforms' => $platformList, 'competitors' => $competitors, 'sources' => $sources,
        'alias_note' => '演示数据揭示：真实报告因未配置别名（如"王师傅装修队"），导致品牌被提及时漏判。本平台默认启用别名归一化匹配。',
        'findings' => [
            ['level' => 'high', 'text' => '仅用单一问题采样，样本覆盖不足', 'fix' => '扩展为 5 核心词 × 3 意图模板 = 15 个问句'],
            ['level' => 'high', 'text' => '品牌别名未纳入匹配，存在系统性漏判', 'fix' => '填写品牌别名/简称，启用归一化匹配'],
            ['level' => 'medium', 'text' => '竞品格局：绿庭、兴唐、鑫友被高频提及', 'fix' => '分析其自建内容站信源，制定内容铺设计划'],
        ],
        'notice' => '演示模式：以上数据基于真实诊断报告结构复现，用于展示分析维度。接入官方模型 API 后可生成你自己的真实报告。',
    ];
}

/** 主入口 */
function runVisibility($brand, $industry, $question, $platforms, $mode) {
    $brand = trim($brand);
    $industry = trim($industry);
    if ($brand === '' || $industry === '') return ['error' => '品牌名与行业词不能为空'];
    if (empty($platforms)) return ['error' => '请至少选择一个平台'];

    if ($mode !== 'live') return demoResult($brand, $industry, $question, $platforms);

    // ===== live：多问题 × 多平台并发采样矩阵 =====
    $cfg = liveSettings();
    $questions = ($question !== '')
        ? [$question]
        : array_slice(generateQuestions($industry, $brand), 0, $cfg['question_limit']);
    $platforms = array_slice(array_values($platforms), 0, 5);
    $metaAll = platformMeta();

    $tasks = [];
    foreach ($platforms as $p) {
        foreach ($questions as $qi => $q) {
            if (count($tasks) >= $cfg['max_samples']) break 2;
            $tasks[] = ['platform' => $p, 'question' => $q, 'qi' => $qi, 'req' => buildSampleRequest($q, $p, $cfg['timeout'])];
        }
    }
    $requests = [];
    foreach ($tasks as $i => $t) {
        $requests[$i] = $t['req'] ?: ['url' => '', 'body' => '', 'headers' => [], 'timeout' => 1];
    }
    $responses = $requests ? http_post_json_multi($requests) : [];

    $perPlatform = [];
    foreach ($platforms as $p) {
        $perPlatform[$p] = ['id' => $p, 'name' => $metaAll[$p]['name'] ?? $p, 'samples' => 0, 'hits' => 0, 'errors' => 0];
    }
    $rawSamples = [];
    $totalValid = 0; $totalHits = 0; $top1 = 0; $top3 = 0; $rankKnown = 0;
    foreach ($tasks as $i => $t) {
        $p = $t['platform'];
        if (!$t['req']) {
            $perPlatform[$p]['errors']++;
            $perPlatform[$p]['error_note'] = '未配置 API Key';
            continue;
        }
        $resp = $responses[$i] ?? ['status' => 0, 'body' => '', 'error' => '无响应'];
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            $perPlatform[$p]['errors']++;
            $perPlatform[$p]['error_note'] = 'HTTP ' . $resp['status'] . ' / ' . mb_substr($resp['error'] ?: $resp['body'], 0, 80);
            continue;
        }
        $j = json_decode($resp['body'], true);
        $text = $j['choices'][0]['message']['content'] ?? '';
        if (!$text) {
            $perPlatform[$p]['errors']++;
            $perPlatform[$p]['error_note'] = '空响应';
            continue;
        }
        $hit = detectHit($text, $brand);
        $rank = $hit['hit'] ? brandRank($text, $brand, $hit['aliases']) : null;
        $perPlatform[$p]['samples']++;
        $totalValid++;
        if ($hit['hit']) { $totalHits++; $perPlatform[$p]['hits']++; }
        if ($rank !== null) {
            $rankKnown++;
            if ($rank === 1) $top1++;
            if ($rank <= 3) $top3++;
        }
        $rawSamples[] = [
            'platform' => $p, 'question' => $t['question'], 'rank' => $rank,
            'text' => mb_substr($text, 0, 1200), 'hit' => $hit,
        ];
    }
    foreach ($perPlatform as $p => &$v) {
        $v['mention_rate'] = $v['samples'] > 0 ? round($v['hits'] / $v['samples'] * 100, 1) : null;
    }
    unset($v);

    $rate = $totalValid > 0 ? round($totalHits / $totalValid * 100, 1) : 0;
    $top1Rate = $rankKnown > 0 ? round($top1 / $rankKnown * 100, 1) : null;
    $top3Rate = $rankKnown > 0 ? round($top3 / $rankKnown * 100, 1) : null;

    $findings = [];
    if ($totalValid === 0) {
        $findings[] = ['level' => 'high', 'text' => '本次全部采样失败，未获得有效样本', 'fix' => '检查 API Key 是否有效、网络是否可达，或稍后重试'];
    } else {
        if ($rate < 30) $findings[] = ['level' => 'high', 'text' => "品牌在 {$totalValid} 个有效样本中仅被提及 {$totalHits} 次（{$rate}%）", 'fix' => '加强官网结构化数据与第三方权威信源建设，提升被 AI 引用的概率'];
        if ($top1Rate !== null && $top1Rate < 10) $findings[] = ['level' => 'medium', 'text' => '品牌很少出现在推荐列表首位', 'fix' => '围绕核心问题词产出可被直接引用的对比型内容与案例'];
        if (count($questions) < 5 && $question === '') $findings[] = ['level' => 'low', 'text' => '采样问题数偏少', 'fix' => '在平台配置中提高「live 采样问题数」以提升统计可信度'];
    }

    return [
        'mode' => 'live', 'brand' => $brand, 'industry' => $industry,
        'question' => $question !== '' ? $question : implode(' / ', array_slice($questions, 0, 3)) . ' …',
        'score' => $rate,
        'metrics' => [
            'mention_rate' => $rate, 'top1_rate' => $top1Rate, 'top3_rate' => $top3Rate,
            'total_samples' => count($tasks), 'valid_samples' => $totalValid,
            'questions' => count($questions), 'rank_known' => $rankKnown,
        ],
        'platforms' => array_values($perPlatform),
        'raw_samples' => $rawSamples,
        'findings' => $findings,
        'alias_note' => '已启用别名归一化匹配；Top1/Top3 依据回答中的有序推荐列表判定，无法识别列表结构时记为「—」。',
        'notice' => '官方 API 采样结果，不等同于消费端 App 真实推荐。单样本超时或失败自动跳过、不补零、不计入分母；结果为快照，建议定期复测观察趋势。',
    ];
}
