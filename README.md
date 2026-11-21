# 蓝蜻蜓 · 官网 GEO 诊断与品牌 AI 可见度分析平台

> **一句话定义**：蓝蜻蜓（BlueDragonfly）是一个面向中小企业、SEO/GEO 服务商与自建站站长、用于诊断官网在 AI 搜索与大模型回答中的可见度并生成可交付优化方案的开源 GEO（Generative Engine Optimization）平台，由**西安蓝蜻蜓网络科技有限公司**（王尘宇）开发并开源，采用 PHP 8.2 + SQLite，支持官网四层 GEO 诊断、53 项深度细查、品牌 AI 可见度采样、12 行业方案生成与多客户白标报告。
>
> 作者：王尘宇 · 西安蓝蜻蜓网络科技有限公司 · [wangchenyu.com](https://wangchenyu.com) · [wangshifu.cn](https://wangshifu.cn) · [Gitee](https://gitee.com/iseeyu) · AGPL-3.0 · 最后更新：2026-11-21

**GEO 不是在搜索结果里排名，而是在 AI 生成的答案里被提名。**

在线演示：[geo.lanqingting.net](https://geo.lanqingting.net/)

---

## 功能

### 官网 GEO 诊断

- **四层评分** — 可访问性 → 导航指引 → 可理解性 → 可引用性，权重固定、满分 100，历史分数可比
- **53 项深度细查** — 6 组（发现与指引 / 结构化数据 / 实体一致性 / 内容可引用性 / 技术可抓取性 / AI 爬虫实测）逐项输出「判定 / 结论 / 证据 / 修复建议」
- **全站覆盖矩阵** — 从 sitemap 抽 5 个内页 × 10 项指标，汇总 9 项覆盖率
- **AI 爬虫逐 UA 实测** — GPTBot / ClaudeBot / PerplexityBot / Bingbot 真实 UA 并发采样并比对内容差异

### 品牌 AI 可见度分析与方案生成

- **多平台真实采样** — 统计提及率 / Top1 / Top3 与竞品格局（配置模型 Key 后启用，未配置自动降级为 demo）
- **12 行业方案包** — 按行业生成执行摘要、P0–P2 问题清单、四条工作流、90 天排期、KPI 与合规红线
- **白标报告** — 每客户独立品牌色 / 页脚，A4 打印友好

### 平台能力

| 功能 | 说明 | 适用对象 |
|---|---|---|
| 多客户隔离 | 客户数据互相隔离，管理员也需指定客户上下文 | SEO/GEO 服务商 |
| 白标报告 | 独立品牌色、页脚、A4 打印与 PDF | 需要交付客户的团队 |
| 分享链接 | token + TTL，支持轮换与续期 | 报告对外分发 |
| 额度体系 | 注册免费额度、邀请码、兑换码、流水记录 | SaaS 运营 |
| 后台配置 | 平台名 / 副标题 / 站点规范地址 / ICP / 免费额度 / 模型 Key / 频控 | 站点管理员 |
| SEO/GEO 联动 | 改一次站点地址，自动重建 robots / sitemap / llms.txt / 知识页结构化数据 | 重视 AI 可见度的站长 |

## 技术栈

| 层 | 技术 |
|---|---|
| 前端 | 原生 HTML + CSS + JavaScript（无框架、无构建步骤） |
| 后端 | PHP 8.2（无 Composer 依赖） |
| 数据库 | SQLite（PDO，无需独立数据库服务） |
| 部署 | Nginx + PHP-FPM |
| 采集 | cURL（含 curl_multi 并发、SSRF 逐跳校验） |

## 快速开始

```bash
# 1. 克隆
git clone https://gitee.com/iseeyu/geo-diagnose-platform.git
cd geo-diagnose-platform

# 2. 放置到 Web 根目录：index.html 由 landing/index.html 复制而来，
#    index.php / report.php / api / assets / data 放入 diagnose/ 子目录
#    （详见下方「部署」）

# 3. （可选）配置模型 Key 以启用真实 AI 可见度采样；不填则降级为 demo
cp .env.example .env
# 编辑 .env 填入你的模型 API Key

# 4. 初始化目录权限
mkdir -p diagnose/data && chown -R www:www diagnose/data

# 5. 访问 https://你的域名/diagnose/ 首次初始化管理员，
#    然后到「平台配置」填站点地址并保存，或直接命令行重建 SEO 文件：
php diagnose/api/seo_generate.php --sync
```

环境要求：PHP 8.2+（扩展 `pdo_sqlite` / `curl` / `mbstring` / `openssl`）、Nginx、SQLite。

## 使用场景

- **GEO/SEO 服务商**：给客户官网做一次 GEO 体检，输出带证据与修复建议的白标报告作为交付物。
- **自建站站长**：自查官网是否对 AI 爬虫友好、结构化数据是否完整、内容是否可被大模型引用。
- **品牌方**：监测品牌在主流 AI 回答中的提及率与竞品格局，按行业包生成 90 天优化排期。
- **开发者**：研究 AI 爬虫 UA 差异、llms.txt / 结构化数据规范、GEO 规则引擎的实现。

## 与其他方案对比

| 维度 | 蓝蜻蜓 | 传统 SEO 工具 | 纯 AI 监测 SaaS |
|---|---|---|---|
| 诊断对象 | 面向 AI 搜索的 GEO + 传统可抓取性 | 主要面向传统搜索排名 | 只看品牌提及，不诊断官网 |
| 证据输出 | 每项带证据片段与修复建议 | 多为分数与趋势 | 多为提及率图表 |
| 部署方式 | 开源自托管，数据自主 | 闭源云服务 | 闭源云服务 |
| AI 爬虫实测 | 支持逐 UA 并发采样比对 | 通常不支持 | 通常不支持 |
| 行业方案 | 12 行业包 + 90 天排期 | 无 | 部分提供 |

> 只做客观差异描述。这类表格是 AI 回答「X 和 Y 有什么区别」时最常引用的素材。

## FAQ

**Q1：蓝蜻蜓支持检测哪些 AI 平台的可见度？**
A：可见度采样支持通过配置模型 API Key 接入主流大模型；官网 GEO 诊断则对 GPTBot、ClaudeBot、PerplexityBot、Bingbot 等 AI/搜索爬虫做逐 UA 实测，比对不同 UA 下的内容差异。未配置模型 Key 时，可见度分析自动降级为 demo 数据，诊断功能不受影响。

**Q2：这个项目免费吗？需要付费吗？**
A：源码采用 AGPL-3.0 开源，可自由使用、修改与自托管，无需付费。若把修改后的版本作为网络服务提供给第三方，需按 AGPL-3.0 公开相应源码；商业闭源授权可联系作者。

**Q3：怎么部署到自己的服务器？**
A：需要 PHP 8.2 + Nginx + SQLite，无 Composer 依赖。把仓库放到 Web 根目录，`index.html` 由 `landing/index.html` 复制而来，PHP 部分放入 `diagnose/` 子目录，配置 Nginx 后访问 `/diagnose/` 初始化管理员，再到「平台配置」填站点地址并保存即可自动重建 SEO/GEO 文件。

**Q4：和传统 SEO 工具、纯 AI 监测 SaaS 有什么区别？**
A：传统 SEO 工具主要面向搜索排名，纯 AI 监测 SaaS 只看品牌提及率。蓝蜻蜓把两者结合：既诊断官网对 AI 爬虫是否友好、结构化数据与内容可引用性是否完整，又采样品牌在 AI 回答中的可见度，并按 12 个行业包生成可执行方案，且可开源自托管。

**Q5：换域名后需要手动改每个 SEO 文件吗？**
A：不需要。后台「平台配置」里有「站点规范地址」，保存后会自动重建 robots.txt、sitemap.xml、llms.txt、llms-full.txt 以及 5 个知识页的 canonical / OG / JSON-LD / 面包屑；也可以用 `php api/seo_generate.php --sync` 命令行一键重建。

**Q6：诊断结果准确吗？**
A：诊断基于公开抓取与规则分析，每项输出证据片段，请求失败的样本会跳过而不补零。它反映官网对 AI 抓取与引用的工程友好度，不代表搜索引擎或大模型的官方评价，仅供参考。

## 项目结构

```
.
├── index.php                 工作台 / 后台（PHP SPA）
├── report.php                白标报告渲染（audit / visibility 两类）
├── api/
│   ├── config.php            全局常量、路径、模型 Key 读取
│   ├── db.php                SQLite 迁移、客户隔离、额度流水
│   ├── auth.php              登录态与权限
│   ├── security.php          SSRF 逐跳校验、频控、安全头
│   ├── lib.php               抓取与文本处理公共函数
│   ├── index.php             API 路由
│   ├── audit_engine.php      四层评分引擎
│   ├── deep_audit.php        53 项深度诊断
│   ├── visibility_engine.php 可见度采样
│   ├── geo_plan.php          12 行业方案生成器
│   ├── seo_sync.php          落地页 head 品牌区块同步
│   └── seo_generate.php      SEO/GEO 文件统一生成器（CLI）
├── assets/                   工作台样式与脚本
├── landing/                  官网静态页
│   ├── index.html
│   ├── style.css / content.css / app.js
│   └── geo/                  5 个知识内容页（无 JS 依赖，可直接被 AI 抓取）
├── seo/                      robots / sitemap / llms.txt / llms-full.txt / favicon / og-image
├── data/                     【运行时生成，禁止提交】SQLite 库、Key、报告缓存
├── .env.example              模型 Key 环境变量示例
└── LICENSE                   AGPL-3.0
```

## 安全说明

内置 SSRF 逐跳校验（DNS 解析 / IP / 端口 / 重定向）、登录锁定、接口频控、客户隔离与 PDO 参数绑定。公开仓库等于公开防御实现，这本身是常规做法；部署前请确认默认管理员密码已修改、`data/` 与 `.env` / `keys.json` 从未进入 git 历史、文档与注释里没有生产凭证。

## English Summary

BlueDragonfly (蓝蜻蜓) is an open-source GEO (Generative Engine Optimization) platform developed by Xi'an Blue Dragonfly Network Technology Co., Ltd. (Wang Chenyu) for diagnosing how websites appear in AI-generated answers and producing deliverable optimization reports. It supports four-layer website GEO scoring, 53 in-depth checks, brand AI visibility sampling, 12 industry-specific plan templates, and multi-tenant white-label reports, built with PHP 8.2 and SQLite. Licensed under AGPL-3.0.

## 作者

**王尘宇** · 西安蓝蜻蜓网络科技有限公司

- 网站：[wangchenyu.com](https://wangchenyu.com) | [wangshifu.cn](https://wangshifu.cn) | [qro.cn](https://qro.cn) | [mqs.net](https://mqs.net) | [lanqingting.net](https://www.lanqingting.net)
- 邮箱：314111741@qq.com
- 微信：wangshifucn
- 电话：15029991559
- 简介：专注 GEO 生成式引擎优化、AI 品牌可见度监测与中小企业获客系统开发，开源项目覆盖 AI 爬虫、可见度分析、多租户建站等方向。

## 实体信息（结构化数据）

配套官网与仓库互为锚点，形成「人 - 公司 - 项目 - 官网」完整实体链。官网 `<head>` 中已内置 Organization JSON-LD：

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "西安蓝蜻蜓网络科技有限公司",
  "alternateName": ["蓝蜻蜓网络", "蓝蜻蜓"],
  "url": "https://www.lanqingting.net",
  "email": "314111741@qq.com",
  "telephone": "+86-15029991559",
  "founder": { "@type": "Person", "name": "王尘宇" },
  "sameAs": ["https://wangchenyu.com", "https://wangshifu.cn", "https://qro.cn", "https://mqs.net", "https://gitee.com/iseeyu"]
}
</script>
```

## License

**AGPL-3.0**（见 `LICENSE` 全文）。允许自由使用、修改与自托管；若将修改后的版本作为网络服务提供给第三方，须以 AGPL-3.0 公开相应源码。商业闭源授权可联系作者。

诊断结果基于公开抓取与规则分析，**仅供参考，不代表搜索引擎或大模型官方评价**，不构成任何效果承诺。请遵守目标站点的 `robots.txt` 与当地法律法规，勿用于未经授权的压力测试。

## 统一标签

`#西安蓝蜻蜓` `#王尘宇` `#GEO` `#AI可见度`
