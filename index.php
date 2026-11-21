<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';
// 工作台外壳独立渲染，自带 HTML 转义函数（report.php 内的同名函数不会在此作用域加载）
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#070A12" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#F6F8FD" media="(prefers-color-scheme: light)">
<title><?= h(platformName()) ?> · 官网体检与品牌可见度分析</title>
<script>(function(){try{var t=localStorage.getItem('geo-theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){document.documentElement.setAttribute('data-theme','light');}})();</script>
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/style.css">
</head>
<body>
<div class="ambient" aria-hidden="true"><span class="orb orb-a"></span><span class="orb orb-b"></span></div>
<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
<symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
<symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
<symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
<symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2-4.2"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
<symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></symbol>
<symbol id="i-doc" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></symbol>
<symbol id="i-gift" viewBox="0 0 24 24"><path d="M3 9h18v4H3zM5 13v8h14v-8M12 9v12"/><path d="M12 9C10 9 7 8.5 7 6.5A2.5 2.5 0 0 1 12 6a2.5 2.5 0 0 1 5 .5C17 8.5 14 9 12 9z"/></symbol>
<symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
<symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l2 2M14 9l2 2"/></symbol>
<symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 6.6M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/></symbol>
<symbol id="i-cog" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/></symbol>
<symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></symbol>
<symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M14 8l4 4-4 4M8 12h10"/></symbol>
<symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 2l1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9z"/><path d="M19 17l.7 2.3L22 20l-2.3.7L19 23l-.7-2.3L16 20l2.3-.7z"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></symbol>
<symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 3l9 5-9 5-9-5z"/><path d="M3 12l9 5 9-5M3 16l9 5 9-5"/></symbol>
</defs></svg>
<!-- 登录页 -->
<div id="loginScreen" class="login-screen">
  <div class="login-shell">
    <div class="login-story">
      <a class="logo" href="/"><span class="logo-mark"><svg class="ico"><use href="#i-spark"/></svg></span><span class="logo-text"><strong data-brand="name"><?= h(platformName()) ?></strong><small data-brand="tagline"><?= h(platformTagline()) ?></small></span></a>
      <div class="ls-body">
        <span class="kicker"><i></i>诊断工作台</span>
        <h2>让每一次 AI 回答，<br><span class="grad">都有你的位置</span></h2>
        <p>官网 GEO 四层体检 + 品牌 AI 可见度采样，把「AI 怎么读你的网站、怎么提到你的品牌」变成可执行的优化清单。</p>
        <ul class="ls-list">
          <li><span class="ls-ico"><svg class="ico"><use href="#i-globe"/></svg></span><div><strong>官网 GEO 诊断</strong><small>AI 爬虫 · 结构化数据 · 可引用段落</small></div></li>
          <li><span class="ls-ico"><svg class="ico"><use href="#i-search"/></svg></span><div><strong>品牌可见度分析</strong><small>多平台采样 · Top1/Top3 · 竞品格局</small></div></li>
          <li><span class="ls-ico"><svg class="ico"><use href="#i-doc"/></svg></span><div><strong>独立品牌报告</strong><small>白标封面 · 分享链接 · 导出 PDF</small></div></li>
        </ul>
      </div>
      <div class="ls-foot"><span class="online-dot"></span>系统运行中 · 数据安全隔离</div>
    </div>
    <div class="login-card">
      <button class="icon-btn theme-toggle login-theme" id="loginThemeToggle" aria-label="切换深浅主题" title="切换深浅主题"><svg class="ico i-sun"><use href="#i-sun"/></svg><svg class="ico i-moon"><use href="#i-moon"/></svg></button>
      <div class="login-brand"><div class="brand-mark"><svg class="ico"><use href="#i-spark"/></svg></div><div><strong data-brand="name"><?= h(platformName()) ?></strong><span>品牌可见度 · 官网体检</span></div></div>
      <h2>登录工作台</h2>
      <p class="login-sub">使用你的账号登录，没有账号可免费注册</p>
      <form id="loginForm">
        <div class="field"><label>账号</label><input type="text" id="loginUser" placeholder="用户名" autocomplete="username" required></div>
        <div class="field"><label>密码</label><input type="password" id="loginPass" placeholder="密码" autocomplete="current-password" required></div>
        <div id="loginErr" class="login-err" style="display:none"></div>
        <button type="submit" class="btn btn-primary" id="btnLogin" style="width:100%;margin-top:6px">登录</button>
      </form>
      <div class="login-foot">还没有账号？<a href="/?register=1">免费注册</a>，新用户赠送 <?= freeQuota() ?> 次诊断<br>忘记密码请联系管理员重置</div>
    </div>
  </div>
</div>

<div class="app" id="appMain" style="display:none">
  <div class="sidebar-scrim" id="sidebarScrim"></div>
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark"><svg class="ico"><use href="#i-spark"/></svg></div>
      <div class="brand-text"><strong data-brand="name"><?= h(platformName()) ?></strong><span>AI 可见度分析</span></div>
      <button class="icon-btn sidebar-close" id="sidebarClose" aria-label="关闭菜单"><svg class="ico"><use href="#i-close"/></svg></button>
    </div>
    <nav class="nav">
      <div class="nav-label">工作台</div>
      <a class="nav-item active" data-page="dashboard"><svg class="ico"><use href="#i-grid"/></svg><span>概览</span></a>
      <div class="nav-label">诊断工具</div>
      <a class="nav-item" data-page="site-audit"><svg class="ico"><use href="#i-globe"/></svg><span>官网 GEO 诊断</span></a>
      <a class="nav-item" data-page="visibility"><svg class="ico"><use href="#i-search"/></svg><span>品牌可见度分析</span></a>
      <div class="nav-label">记录与报告</div>
      <a class="nav-item" data-page="history"><svg class="ico"><use href="#i-clock"/></svg><span>历史报告</span></a>
      <a class="nav-item" data-page="trend"><svg class="ico"><use href="#i-chart"/></svg><span>趋势分析</span></a>
      <a class="nav-item" data-page="branding"><svg class="ico"><use href="#i-doc"/></svg><span>报告品牌设置</span></a>
      <div class="nav-label">我的账户</div>
      <a class="nav-item" data-page="invite"><svg class="ico"><use href="#i-gift"/></svg><span>邀请有礼</span></a>
      <a class="nav-item" data-page="profile"><svg class="ico"><use href="#i-user"/></svg><span>个人中心</span></a>
      <a class="nav-item" data-page="redeem"><svg class="ico"><use href="#i-key"/></svg><span>兑换额度</span></a>
      <a class="nav-item admin-only" data-page="clients" style="display:none"><svg class="ico"><use href="#i-users"/></svg><span>客户管理</span></a>
      <a class="nav-item admin-only" data-page="settings" style="display:none"><svg class="ico"><use href="#i-cog"/></svg><span>平台配置</span></a>
      <a class="nav-item admin-only" data-page="logs" style="display:none"><svg class="ico"><use href="#i-list"/></svg><span>操作日志</span></a>
    </nav>
    <div class="sidebar-foot">
      <div class="online-dot"></div><span>系统运行中</span>
      <div class="ver">v<?= APP_VERSION ?></div>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="icon-btn app-menu" id="appMenuBtn" aria-label="打开导航菜单" aria-expanded="false"><svg class="ico"><use href="#i-menu"/></svg></button>
        <div><span class="tb-kicker">诊断工作台</span><h1 id="pageTitle">概览</h1><p id="pageSub">官网 GEO 体检 · 品牌 AI 可见度分析 · 行业化优化方案</p></div>
      </div>
      <div class="topbar-right">
        <span class="quota-chip" id="quotaChip">额度 —</span>
        <button class="icon-btn theme-toggle" id="appThemeToggle" aria-label="切换深浅主题" title="切换深浅主题"><svg class="ico i-sun"><use href="#i-sun"/></svg><svg class="ico i-moon"><use href="#i-moon"/></svg></button>
        <span class="user-chip"><span class="avatar" id="userAvatar">—</span><span class="user-meta"><strong id="userName">—</strong><small id="userRole">—</small></span></span>
        <button class="btn btn-ghost" id="btnLogout"><svg class="ico"><use href="#i-logout"/></svg><span>退出</span></button>
      </div>
    </header>

    <div class="content">
      <!-- 概览 -->
      <section class="page active" id="page-dashboard">
        <div class="stat-grid">
          <div class="stat-card"><div class="stat-num" id="st-audits">—</div><div class="stat-label">官网诊断次数</div></div>
          <div class="stat-card"><div class="stat-num" id="st-vis">—</div><div class="stat-label">可见度分析次数</div></div>
          <div class="stat-card"><div class="stat-num" id="st-avgsite">—</div><div class="stat-label">官网平均得分</div></div>
          <div class="stat-card"><div class="stat-num" id="st-avgvis">—</div><div class="stat-label">可见度平均分</div></div>
        </div>
        <div class="hero-grid">
          <div class="hero-card hero-teal">
            <div class="hero-icon hero-icon-a"><svg class="ico"><use href="#i-globe"/></svg></div>
            <h3>官网 GEO 诊断</h3>
            <p>四层评分 + 6 组 50+ 项深度细查：AI 爬虫逐 UA 实测、llms.txt / sitemap 质量校验、结构化数据逐类型核对、实体一致性比对、段落级可引用性统计与全站覆盖矩阵，每项附证据与修复建议。</p>
            <div class="hero-tag">零成本 · 立即可用</div>
            <button class="btn btn-primary" data-goto="site-audit">开始官网体检 →</button>
          </div>
          <div class="hero-card hero-amber">
            <div class="hero-icon hero-icon-b"><svg class="ico"><use href="#i-search"/></svg></div>
            <h3>品牌可见度分析</h3>
            <p>问题词 × 平台采样，检测品牌在 AI 回答中的提及率、排名与竞品格局。支持别名归一化匹配、否定识别与引用信源分析。</p>
            <div class="hero-tag">演示模式 · 可接入真实 API</div>
            <button class="btn btn-primary" data-goto="visibility">分析品牌可见度 →</button>
          </div>
        </div>
        <div class="panel">
          <div class="panel-head"><h3>最近诊断</h3></div>
          <div id="recentList" class="recent-list"><div class="empty">加载中…</div></div>
        </div>
      </section>

      <!-- 官网 GEO 诊断 -->
      <section class="page" id="page-site-audit">
        <div class="panel form-panel">
          <div class="panel-head"><h3>新建官网诊断</h3><span class="hint">输入要体检的官网地址</span></div>
          <div class="form-row">
            <input type="text" id="auditUrl" placeholder="例如：https://www.example.com" value="">
            <button class="btn btn-primary" id="btnAudit">开始诊断</button>
          </div>
          <div class="quick-sites">
            <span>快速测试：</span>
            <a href="#" data-url="https://www.growmind.cn">GrowMind</a>
            <a href="#" data-url="https://23geo.com">23geo</a>
            <a href="#" data-url="https://www.baidu.com">百度</a>
          </div>
        </div>
        <div id="auditProgress" class="progress-box" style="display:none">
          <div class="spinner"></div><div id="auditProgressText">正在抓取并分析站点（约 10-30 秒）…</div>
        </div>
        <div id="auditResult"></div>
      </section>

      <!-- 品牌可见度 -->
      <section class="page" id="page-visibility">
        <div class="panel form-panel">
          <div class="panel-head"><h3>品牌可见度分析</h3><span class="hint">配置品牌与问题词，选择采样平台</span></div>
          <div class="form-grid">
            <div class="field"><label>品牌名称</label><input type="text" id="visBrand" placeholder="例如：王师傅装修"></div>
            <div class="field"><label>行业词 / 核心词（逗号分隔）</label><input type="text" id="visIndustry" placeholder="例如：西安旧房翻新,老房改造"></div>
          </div>
          <div class="field"><label>问题词（留空自动生成 15 个意图问句）</label><input type="text" id="visQuestion" placeholder="可选，例如：西安旧房翻新公司哪家好？"></div>
          <div class="field">
            <label>采样平台</label>
            <div id="platformChecks" class="platform-checks"></div>
          </div>
          <div class="field">
            <label>分析模式</label>
            <div class="mode-switch">
              <label class="radio"><input type="radio" name="mode" value="demo" checked><span><strong>演示模式</strong><small>基于真实报告结构，零成本查看完整分析</small></span></label>
              <label class="radio"><input type="radio" name="mode" value="live"><span><strong>真实 API 模式</strong><small>调用官方模型采样（需配置 Key）</small></span></label>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-ghost" id="btnGenQ">生成问题词预览</button>
            <button class="btn btn-primary" id="btnVis">开始分析</button>
          </div>
          <div id="qPreview" class="q-preview" style="display:none"></div>
        </div>
        <div id="visProgress" class="progress-box" style="display:none">
          <div class="spinner"></div><div id="visProgressText">正在采样分析…</div>
        </div>
        <div id="visResult"></div>
      </section>

      <!-- 历史 -->
      <section class="page" id="page-history">
        <div class="tabs">
          <button class="tab active" data-hist="audit">官网诊断记录</button>
          <button class="tab" data-hist="visibility">可见度分析记录</button>
        </div>
        <div class="panel">
          <div id="historyList" class="history-list"></div>
        </div>
      </section>

      <!-- 趋势分析 -->
      <section class="page" id="page-trend">
        <div class="panel">
          <div class="panel-head"><h3>得分趋势</h3><span class="hint">按诊断时间排列，用于周 / 月度复测对比</span></div>
          <div class="trend-legend"><span><i class="lg-a"></i>官网 GEO 得分</span><span><i class="lg-v"></i>品牌可见度（%）</span></div>
          <div id="trendChart" class="trend-chart"><div class="empty">加载中…</div></div>
          <div id="trendSummary" class="trend-summary"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>复测明细</h3></div>
          <div id="trendTable"></div>
        </div>
      </section>

      <!-- 个人中心 -->
      <section class="page" id="page-profile">
        <div class="panel form-panel">
          <div class="panel-head"><h3>账户资料</h3><span class="hint">修改后即时生效</span></div>
          <div class="form-grid">
            <div class="field"><label>名称 / 称呼</label><input type="text" id="pfName"></div>
            <div class="field"><label>公司</label><input type="text" id="pfCompany"></div>
          </div>
          <div class="field"><label>联系电话</label><input type="text" id="pfPhone" placeholder="选填"></div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveProfile">保存资料</button></div>
          <div id="profileErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel form-panel" style="margin-top:18px">
          <div class="panel-head"><h3>修改登录密码</h3><span class="hint">建议定期更换，不要使用默认密码</span></div>
          <div class="form-grid">
            <div class="field"><label>原密码</label><input type="password" id="pfOldPwd" autocomplete="current-password"></div>
            <div class="field"><label>新密码（至少 8 位）</label><input type="password" id="pfNewPwd" autocomplete="new-password"></div>
          </div>
          <div class="field"><label>确认新密码</label><input type="password" id="pfNewPwd2" autocomplete="new-password"></div>
          <div class="form-actions"><button class="btn btn-primary" id="btnChangePwd">修改密码</button></div>
          <div id="pwdErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>额度流水</h3><span class="hint">注册赠送、邀请奖励、兑换与诊断消耗</span></div>
          <div id="creditList" class="credit-list"></div>
        </div>
      </section>

      <!-- 兑换额度 -->
      <section class="page" id="page-redeem">
        <div class="panel form-panel">
          <div class="panel-head"><h3>兑换额度码</h3><span class="hint">输入管理员发放的兑换码，立即增加诊断次数</span></div>
          <div class="form-row">
            <input type="text" id="redeemCode" placeholder="例如：A1B2C3D4E5" maxlength="20">
            <button class="btn btn-primary" id="btnRedeem">兑换</button>
          </div>
          <p class="settings-note">1 次额度可用于 1 次官网 GEO 诊断或 1 次品牌可见度分析。每个兑换码只能使用一次。</p>
          <div id="redeemErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel admin-only" style="display:none;margin-top:18px">
          <div class="panel-head"><h3>生成兑换码（管理员）</h3></div>
          <div class="form-grid">
            <div class="field"><label>每张面额（次数）</label><input type="number" id="rcAmount" value="10" min="1" max="10000"></div>
            <div class="field"><label>生成数量</label><input type="number" id="rcCount" value="5" min="1" max="100"></div>
          </div>
          <div class="field"><label>批次备注</label><input type="text" id="rcBatch" placeholder="例如：2026年11月活动"></div>
          <div class="form-actions"><button class="btn btn-primary" id="btnGenCodes">生成兑换码</button></div>
          <div id="newCodes" class="code-box" style="display:none"></div>
          <div id="redeemCodeList" class="client-list" style="margin-top:14px"></div>
        </div>
      </section>

      <!-- 操作日志（管理员）-->
      <section class="page" id="page-logs">
        <div class="panel">
          <div class="panel-head"><h3>操作与登录日志</h3><button class="btn btn-ghost" id="btnRefreshLogs">刷新</button></div>
          <p class="settings-note">记录登录、注册、诊断、配置变更与兑换行为，最多展示最近 200 条。</p>
          <div id="logList" class="log-list"></div>
        </div>
      </section>

      <!-- 报告品牌设置 -->
      <section class="page" id="page-branding">
        <div class="panel form-panel">
          <div class="panel-head">
            <h3>独立品牌报告设置</h3>
            <span class="hint">配置后，该客户的所有诊断报告将自动使用此品牌</span>
          </div>
          <div class="branding-layout">
            <div class="branding-form">
              <div class="field admin-only" id="brandClientField" style="display:none">
                <label>选择客户</label>
                <select id="brandClientSelect" class="sel"></select>
              </div>
              <div class="form-grid">
                <div class="field"><label>报告品牌名</label><input type="text" id="bfBrand" placeholder="例如：某某数字营销"></div>
                <div class="field"><label>主题色</label><input type="color" id="bfColor" value="#2563EB" class="color-input"></div>
              </div>
              <div class="field"><label>Logo 地址（可选，http/https 图片）</label><input type="text" id="bfLogo" placeholder="https://example.com/logo.png"></div>
              <div class="field"><label>报告标语</label><input type="text" id="bfTagline" placeholder="例如：AI 时代品牌可见度与官网体检报告"></div>
              <div class="field"><label>联系方式</label><input type="text" id="bfContact" placeholder="电话 / 微信 / 邮箱，会显示在报告页脚"></div>
              <div class="field"><label>页脚文案</label><textarea id="bfFooter" rows="2" placeholder="例如：本报告由 XX 出品，仅供优化参考。"></textarea></div>
              <div class="form-actions">
                <button class="btn btn-ghost" id="btnResetBrand">重新载入</button>
                <button class="btn btn-primary" id="btnSaveBrand">保存品牌设置</button>
              </div>
              <div id="brandErr" class="login-err" style="display:none"></div>
            </div>
            <div class="branding-preview-wrap">
              <div class="preview-label">报告封面预览</div>
              <div class="brand-preview" id="brandPreview">
                <div class="bp-top">
                  <div class="bp-logo" id="bpLogoMark">GEO</div>
                  <div><div class="bp-name" id="bpName">品牌名称</div><div class="bp-tag" id="bpTagline">报告标语</div></div>
                </div>
                <div class="bp-rule"></div>
                <div class="bp-title" id="bpTitle">官网 GEO 诊断报告</div>
                <div class="bp-subject" id="bpSubject">报告对象 · 示例品牌</div>
                <div class="bp-score"><span id="bpScore">86</span><small>GEO 得分</small></div>
                <div class="bp-foot" id="bpContact">联系方式</div>
              </div>
              <p class="preview-note">提示：保存后，新生成或已存在的报告链接都会使用最新品牌配置。</p>
            </div>
          </div>
        </div>
      </section>

      <!-- 邀请有礼 -->
      <section class="page" id="page-invite">
        <div class="panel invite-card-panel">
          <div class="panel-head"><h3>邀请好友，双方都有奖励</h3><span class="hint" id="inviteRuleHint">每成功邀请 1 位好友注册，即可获得奖励额度</span></div>
          <div class="invite-hero">
            <div class="invite-link-box">
              <div class="invite-label">你的专属邀请链接</div>
              <div class="invite-link-row">
                <input type="text" id="inviteUrl" readonly>
                <button class="btn btn-primary" id="btnCopyInvite">复制链接</button>
              </div>
              <p class="invite-tip">好友通过此链接注册后，奖励额度自动发放到你的账户，无需手动操作。</p>
            </div>
            <div class="invite-code-box">
              <div class="invite-label">邀请码</div>
              <div class="invite-code" id="inviteCode">————</div>
              <button class="btn btn-ghost" id="btnCopyCode">复制邀请码</button>
            </div>
          </div>
          <div class="invite-stats">
            <div class="invite-stat"><div class="is-num" id="invCount">—</div><div class="is-lbl">已邀请好友</div></div>
            <div class="invite-stat"><div class="is-num" id="invEarned">—</div><div class="is-lbl">累计奖励次数</div></div>
            <div class="invite-stat"><div class="is-num" id="invReward">—</div><div class="is-lbl">每次邀请奖励</div></div>
            <div class="invite-stat"><div class="is-num" id="invMax">—</div><div class="is-lbl">奖励上限</div></div>
          </div>
          <div id="inviteClosed" class="chain-warning" style="display:none">邀请奖励当前未开启，邀请链接仍可分享，但不会发放奖励。</div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>邀请规则</h3></div>
          <ul class="check-list">
            <li><span class="check-dot chk-ok">✓</span><span class="chk-txt"><strong>好友通过你的链接注册成功，奖励立即自动到账</strong><span>奖励次数会直接加到你的剩余诊断额度中</span></span></li>
            <li><span class="check-dot chk-ok">✓</span><span class="chk-txt"><strong>好友也能获得额外注册奖励</strong><span id="inviteeBonusRule">通过邀请链接注册的好友，在免费额度之外还能获得额外奖励次数</span></span></li>
            <li><span class="check-dot chk-ok">✓</span><span class="chk-txt"><strong>每个账号的邀请奖励有上限</strong><span>达到上限后仍可邀请，但不再发放奖励，防止恶意刷取</span></span></li>
            <li><span class="check-dot chk-ok">✓</span><span class="chk-txt"><strong>奖励与诊断额度通用</strong><span>1 次奖励 = 1 次官网诊断或 1 次品牌可见度分析</span></span></li>
          </ul>
        </div>
      </section>

      <!-- 客户管理（管理员）-->
      <section class="page" id="page-clients">
        <div class="panel">
          <div class="panel-head">
            <h3>客户管理</h3>
            <button class="btn btn-primary" id="btnNewClient">+ 新建客户</button>
          </div>
          <p class="settings-note">配额填 <b>-1</b> 表示不限次数，<b>0</b> 表示无额度；客户每执行一次诊断消耗 1 次配额，管理员不限。客户只能查看自己的报告。</p>
          <div id="clientList" class="client-list"></div>
        </div>
      </section>

      <!-- 配置 -->
      <section class="page" id="page-settings">
        <div class="panel">
          <div class="panel-head"><h3>平台名称与品牌</h3></div>
          <p class="settings-note">用于落地页、工作台登录页、侧栏与浏览器标题，同时作为新客户默认报告页脚的署名（客户未填公司或名称时也作为默认报告品牌）。修改后全站立即生效，无需重新部署；客户报告页的白标品牌在「报告品牌设置」中单独配置，不受此处影响。</p>
          <div class="form-grid">
            <div class="field"><label>平台 / 产品名称</label><input type="text" id="setPlatformName" maxlength="40" placeholder="蓝蜻蜓"></div>
            <div class="field"><label>副标题（Logo 下方说明）</label><input type="text" id="setPlatformTagline" maxlength="100" placeholder="官网 GEO 体检 · 品牌可见度分析"></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSavePlatform">保存名称</button></div>
          <div id="platformErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>站点地址与 SEO / GEO 文件</h3></div>
          <p class="settings-note">这里填写站点对外规范地址（根地址，含尾斜杠）。保存后会自动重建 robots.txt、sitemap.xml、llms.txt、llms-full.txt，以及首页 head 与全部 GEO 知识页的 canonical / OG / JSON-LD / 品牌名，确保域名与实体全站一致。换域名时还需在服务器同步修改 Nginx <code>server_name</code>、SSL 证书、DNS，并把旧域名 301 跳转到新域名（这些属于基础设施层，脚本不会改动）。</p>
          <div class="form-grid">
            <div class="field"><label>站点规范地址</label><input type="text" id="setSiteUrl" maxlength="200" placeholder="https://your-domain.example/" autocomplete="off"></div>
            <div class="field"><label>最近重建状态</label><input type="text" id="setSeoStatus" value="尚未重建" readonly></div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" id="btnSaveSiteUrl">保存并重建全部文件</button>
            <button class="btn" id="btnRebuildSeo">仅按当前设置重建</button>
          </div>
          <div id="siteUrlErr" class="login-err" style="display:none"></div>
          <div id="siteUrlMsg" class="settings-note" style="display:none;margin-top:10px"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>注册与免费额度</h3></div>
          <p class="settings-note">这里设置新用户自助注册时赠送的诊断次数。0 表示不赠送；修改后仅对新注册用户生效，不影响已有客户配额。</p>
          <div class="form-grid">
            <div class="field"><label>新用户免费诊断次数</label><input type="number" id="setFreeQuota" min="0" max="1000" value="5"></div>
            <div class="field"><label>自助注册</label><select id="setRegisterEnabled" class="sel"><option value="1">开放注册</option><option value="0">关闭注册</option></select></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveSettings">保存设置</button></div>
          <div id="settingsErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>邀请有礼（裂变奖励）</h3></div>
          <p class="settings-note">邀请注册奖励规则：好友通过邀请链接注册，邀请人自动获得「每次邀请奖励」次数；受邀好友在免费额度之外获得「受邀额外奖励」。单账号累计奖励达到上限后不再发放。</p>
          <div class="form-grid">
            <div class="field"><label>邀请奖励</label><select id="setInviteEnabled" class="sel"><option value="1">开启</option><option value="0">关闭</option></select></div>
            <div class="field"><label>每次邀请奖励（次数）</label><input type="number" id="setInviteReward" min="0" max="100" value="2"></div>
          </div>
          <div class="form-grid">
            <div class="field"><label>受邀好友额外奖励（次数）</label><input type="number" id="setInviteeBonus" min="0" max="100" value="0"></div>
            <div class="field"><label>单账号奖励上限（次数）</label><input type="number" id="setInviteMax" min="0" max="10000" value="50"></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveInvite">保存邀请设置</button></div>
          <div id="inviteErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>报告链接与采样参数</h3></div>
          <p class="settings-note">报告分享链接有效期（天），0 表示永不过期；修改只影响新生成或续期的链接，历史链接不受影响。live 模式为多问题 × 多平台并发采样。</p>
          <div class="form-grid">
            <div class="field"><label>报告链接有效期（天）</label><input type="number" id="setShareTtl" min="0" max="3650" value="30"></div>
            <div class="field"><label>live 采样问题数（1-15）</label><input type="number" id="setLiveQuestions" min="1" max="15" value="5"></div>
          </div>
          <div class="form-grid">
            <div class="field"><label>单次最大样本数</label><input type="number" id="setLiveMaxSamples" min="1" max="100" value="30"></div>
            <div class="field"><label>单样本超时（秒）</label><input type="number" id="setLiveTimeout" min="5" max="45" value="15"></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveAdvanced">保存参数</button></div>
          <div id="advancedErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>安全与频控</h3></div>
          <p class="settings-note">同一 IP 在窗口期内的最大尝试次数。登录窗口 10 分钟，注册 / 诊断窗口 1 小时；连续 8 次登录失败锁定 15 分钟。</p>
          <div class="form-grid">
            <div class="field"><label>登录尝试 / 10 分钟</label><input type="number" id="setRlLogin" min="1" max="100" value="10"></div>
            <div class="field"><label>注册次数 / 小时</label><input type="number" id="setRlRegister" min="1" max="100" value="8"></div>
          </div>
          <div class="form-grid">
            <div class="field"><label>诊断次数 / 小时</label><input type="number" id="setRlDiagnose" min="1" max="1000" value="60"></div>
            <div class="field"><label>ICP 备案号</label><input type="text" id="setSiteIcp" placeholder="例如：京ICP备12345678号"></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveSecurity">保存安全设置</button></div>
          <div id="securityErr" class="login-err" style="display:none"></div>
        </div>
        <div class="panel" style="margin-top:18px">
          <div class="panel-head"><h3>模型 API Key 配置</h3></div>
          <p class="settings-note">可直接在此填写官方大模型 Key，保存到服务器 <code>data/keys.json</code>（权限 600）。留空表示不修改该项；已配置的 Key 仅显示掩码。</p>
          <div id="keyEditList" class="key-edit-list"></div>
          <div class="form-actions"><button class="btn btn-primary" id="btnSaveKeys">保存 Key</button></div>
          <div id="keysErr" class="login-err" style="display:none"></div>
        </div>
      </section>
    </div>
  </main>
</div>

<!-- 客户编辑弹窗 -->
<div id="clientModal" class="modal-overlay" style="display:none">
  <div class="modal modal-lg">
    <div class="modal-head"><h3 id="clientModalTitle">新建客户</h3><button class="modal-close" id="clientModalClose" aria-label="关闭"><svg class="ico"><use href="#i-close"/></svg></button></div>
    <div class="modal-body">
      <input type="hidden" id="cfId">
      <div class="form-grid">
        <div class="field"><label>账号（用户名）</label><input type="text" id="cfUsername" placeholder="登录账号"></div>
        <div class="field"><label>客户名称</label><input type="text" id="cfName" placeholder="显示名称"></div>
      </div>
      <div class="form-grid">
        <div class="field"><label>密码 <span id="cfPassHint"></span></label><input type="text" id="cfPassword" placeholder="至少 6 位"></div>
        <div class="field"><label>公司 / 备注</label><input type="text" id="cfCompany" placeholder="可选"></div>
      </div>
      <div class="form-grid">
        <div class="field"><label>角色</label><select id="cfRole" class="sel"><option value="customer">客户</option><option value="admin">管理员</option></select></div>
        <div class="field"><label>诊断配额（-1=不限，0=无额度）</label><input type="number" id="cfQuota" value="5" min="-1"></div>
      </div>
      <div class="field"><label>状态</label><select id="cfEnabled" class="sel"><option value="1">启用</option><option value="0">禁用</option></select></div>
      <div class="modal-section-title">独立品牌报告配置</div>
      <div class="form-grid">
        <div class="field"><label>报告品牌名</label><input type="text" id="cfReportBrand" placeholder="默认使用公司 / 客户名称"></div>
        <div class="field"><label>主题色</label><input type="color" id="cfReportColor" value="#2563EB" class="color-input"></div>
      </div>
      <div class="field"><label>Logo 地址</label><input type="text" id="cfReportLogo" placeholder="https://... （可选）"></div>
      <div class="field"><label>报告标语</label><input type="text" id="cfReportTagline" placeholder="显示在报告封面"></div>
      <div class="field"><label>联系方式</label><input type="text" id="cfReportContact" placeholder="电话 / 微信 / 邮箱"></div>
      <div class="field"><label>页脚文案</label><input type="text" id="cfReportFooter" placeholder="例如：本报告由 XX 出品"></div>
      <div id="cfErr" class="login-err" style="display:none"></div>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost" id="cfCancel">取消</button><button class="btn btn-primary" id="cfSave">保存</button></div>
  </div>
</div>

<div id="toast" class="toast"></div>
<script src="<?= htmlspecialchars($base) ?>/assets/app.js"></script>
</body>
</html>
