<?php
/**
 * 全局配置
 * 平台全局配置（产品名称在后台「平台配置」中设置）
 */

// 关闭错误外泄
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');

define('APP_NAME', '蓝蜻蜓');
define('APP_VERSION', '1.0.0');

// 新用户自助注册赠送的诊断次数（0 表示不赠送）
define('FREE_QUOTA', 5);
// 是否开放自助注册
define('ALLOW_REGISTER', true);

// 路径
define('DATA_DIR', dirname(__DIR__) . '/data');
define('DB_PATH', DATA_DIR . '/geo.db');
define('REPORT_DIR', DATA_DIR . '/reports');
define('CACHE_DIR', DATA_DIR . '/cache');

// 确保目录存在且可写
foreach ([DATA_DIR, REPORT_DIR, CACHE_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

/**
 * 读取模型 API Key 配置
 * 优先读取 config.json（用户可编辑），其次环境变量
 */
function getApiKeys() {
    static $keys = null;
    if ($keys !== null) return $keys;

    $cfgFile = DATA_DIR . '/keys.json';
    $keys = [];

    if (file_exists($cfgFile)) {
        $json = json_decode(file_get_contents($cfgFile), true);
        if (is_array($json)) $keys = $json;
    }

    // 环境变量兜底
    $envMap = [
        'deepseek' => 'DEEPSEEK_API_KEY',
        'doubao'   => 'ARK_API_KEY',
        'qwen'     => 'DASHSCOPE_API_KEY',
        'wenxin'   => 'QIANFAN_API_KEY',
        'yuanbao'  => 'HUNYUAN_API_KEY',
    ];
    foreach ($envMap as $p => $env) {
        if (empty($keys[$p]) && getenv($env)) {
            $keys[$p] = getenv($env);
        }
    }
    return $keys;
}

/**
 * 判断某平台是否已配置 Key
 */
function hasKey($platform) {
    $keys = getApiKeys();
    return !empty($keys[$platform]);
}

/**
 * 支持的 AI 平台元数据
 */
function platformMeta() {
    return [
        'deepseek' => ['name' => 'DeepSeek', 'env' => 'DEEPSEEK_API_KEY', 'model' => 'deepseek-chat', 'base' => 'https://api.deepseek.com'],
        'doubao'   => ['name' => '豆包',     'env' => 'ARK_API_KEY',      'model' => 'doubao-seed-1-6', 'base' => 'https://ark.cn-beijing.volces.com/api/v3'],
        'qwen'     => ['name' => '千问',     'env' => 'DASHSCOPE_API_KEY','model' => 'qwen-plus', 'base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1'],
        'wenxin'   => ['name' => '文心',     'env' => 'QIANFAN_API_KEY',  'model' => 'ernie-4.0-8k', 'base' => 'https://qianfan.baidubce.com/v2'],
        'yuanbao'  => ['name' => '元宝',     'env' => 'HUNYUAN_API_KEY',  'model' => 'hunyuan', 'base' => 'https://hunyuan.tencentcloudapi.com'],
    ];
}
