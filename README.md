# 蓝蜻蜓 · GEO 诊断与品牌 AI 可见度分析平台

一个基于 **PHP 8.2 + SQLite** 的 GEO（Generative Engine Optimization）诊断平台。零外部服务依赖（诊断部分纯抓取），包含官网 GEO 诊断、品牌 AI 可见度分析、多客户隔离、白标报告、分享链接、注册额度与邀请体系。

> **发布副本说明**：本项目已从生产环境完整脱敏。域名统一替换为 `your-domain.example`，样例报告凭证替换为 `REPLACE_WITH_REPORT_ID` / `REPLACE_WITH_SHARE_TOKEN`。部署前请按下方清单替换。

---

## ⚠️ 发布前必读

### 三档发布策略

| 档位 | 做法 | 适合 |
|---|---|---|
| **A（保守）** | 只公开 `seo/`、`landing/geo/` 知识页与诊断规则清单作为方法论；核心业务代码留私有仓库 | 商业运营、担心竞品复制 |
| **B（当前副本）** | 全量脱敏源码 + AGPL-3.0 公开；任何人可自托管，改后做 SaaS 须开源衍生代码 | 想换社区背书 / 贡献 |
| **C（展示）** | 仓库私有，只公开截图、报告样例与技术文章 | 只做作品集 / 客户信任 |

> 想退回 A 档：把 `api/db.php`、`api/index.php`、`api/visibility_engine.php`、`api/geo_plan.php` 从公开仓库移除即可，前端与 SEO 文件不依赖它们运行。

### 发布前检查清单

- [ ] 全量替换 `your-domain.example` 为你的真实域名（`grep -rl your-domain.example .` 可定位）
- [ ] 处理 `landing/index.html` 中的 `REPLACE_WITH_REPORT_ID` / `REPLACE_WITH_SHARE_TOKEN`：换成真实公开样例，或删掉这两个示例链接
- [ ] 品牌名「蓝蜻蜓」是否改成你的品牌：后台改完后必须跑一次 `php api/seo_generate.php --sync` 重建静态 SEO 文件
- [ ] 确认 `data/` 从未被 `git add`（已写入 `.gitignore`，含 `geo.db`、`keys.json`、`initial_admin.txt`）
- [ ] 如实填写 ICP 备案号（后台「平台配置」保存时会自动回写落地页 head）
- [ ] 用 `gitleaks detect` 或 GitHub Secret Scanning 自查一遍再设公开
- [ ] Gitee 先建**私有**仓自查 → 再提交公开审核；README 用「站点可访问性自检 / AI 爬虫可抓取性审计」等中性措辞，避免敏感词误判

---

## 功能

### 1. 官网 GEO 诊断（`api/audit_engine.php` + `api/deep_audit.php`）

**四层评分**（权重固定，历史分数可比）：可访问性 → 导航指引 → 可理解性 → 可引用性，满分 100。

**深度诊断**（与总分解耦，单独统计通过率）：6 组 53 项细查，每项输出 `判定 / 结论 / 证据片段 / 修复建议`：

- A 发现与指引：robots 指令与 AI 爬虫放行、后台屏蔽、llms.txt 质量、sitemap lastmod
- B 结构化数据清单：Organization / WebSite / Article / FAQPage 逐类型必填字段核对
- C 实体一致性：品牌名多来源归一、canonical / og:url / lang 与正文语言一致性
- D 内容可引用性：定义句、事实密度、问答对、署名与时间、首段自包含、前端模板痕迹检测
- E 技术可抓取性：charset、X-Robots-Tag、压缩、HSTS、CSR 空壳、alt 覆盖、响应时间
- F AI 爬虫实测：GPTBot / ClaudeBot / PerplexityBot / Bingbot 真实 UA 并发请求并比对内容差异

**全站覆盖矩阵**：从 sitemap 抽 5 个内页 × 10 项指标，汇总 9 项覆盖率。

> 口径：请求失败的样本跳过、不补零、不计入分母；纯规则打分不代表搜索引擎或大模型官方评价。

### 2. 品牌 AI 可见度分析（`api/visibility_engine.php` + `api/geo_plan.php`）

多平台真实采样，统计提及率 / Top1 / Top3 与竞品格局；按 **12 个行业包** + 通用五层必检清单生成可直接交付客户的《品牌 AI 可见度优化方案》（执行摘要、行业特征、P0–P2 问题清单、四条工作流、90 天排期、KPI、合规红线）。

### 3. 平台能力

- 多客户数据隔离（管理员也需指定客户）
- 白标报告：每客户独立品牌色 / 页脚，A4 打印与 PDF
- 分享链接：token + TTL + 轮换 / 续期
- 注册、免费额度、邀请码、兑换码、额度流水
- 后台「平台配置」：平台名 / 副标题 / **站点规范地址** / ICP / 免费额度 / 模型 Key / 频控
- 双主题（亮 / 暗）、Bento 布局、Editorial 去 AI 化视觉、移动端适配

---

## 目录结构

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
│   ├── seo_sync.php          落地页 head 品牌区块同步 + seoBase()
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

---

## 部署

### 环境要求

- PHP 8.2+，扩展 `pdo_sqlite`、`curl`、`mbstring`、`openssl`
- SQLite（无需独立数据库服务）
- Nginx（Apache 亦可，自行转换 rewrite）
- 无 Composer 依赖

### 文件放置

将仓库内容放到 Web 根目录，使下列路径成立：

```
/var/www/your-domain/
├── index.html      ← 由 landing/index.html 复制而来
├── landing/        ← 静态页与知识页
├── seo/            ← SEO/GEO 发现文件
└── diagnose/       ← 本仓库的 index.php、report.php、api/、assets/、data/
```

本站原部署较特殊：根路径 `/` 被另一个 Docker 应用占用，所以官网首页用 `location = /` 精确匹配。普通站点直接用 `location /` 即可。

### Nginx 示例

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.example;
    root /var/www/your-domain;
    index index.html index.php;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    # ---- SEO/GEO 发现文件：务必放在通用 location 之前 ----
    # charset 不能省：llms.txt 是中文 UTF-8，text/plain 不带 charset 时
    # 中文环境下的浏览器会按 GBK 解码，出现乱码。
    location = /robots.txt   { root /var/www/your-domain/seo; charset utf-8; }
    location = /sitemap.xml  { root /var/www/your-domain/seo; charset utf-8; }
    location = /llms.txt     { root /var/www/your-domain/seo; charset utf-8; default_type text/plain; }
    location = /llms-full.txt{ root /var/www/your-domain/seo; charset utf-8; default_type text/plain; }
    location = /favicon.ico  { root /var/www/your-domain/seo; }
    location = /favicon.svg  { root /var/www/your-domain/seo; }
    location = /apple-touch-icon.png { root /var/www/your-domain/seo; }
    location = /og-image.png { root /var/www/your-domain/seo; }

    location ^~ /geo/ {
        root /var/www/your-domain/landing;
        index index.html;
        try_files $uri $uri/ =404;
        add_header X-Robots-Tag "index, follow, max-snippet:-1, max-image-preview:large" always;
    }

    location ^~ /diagnose/ {
        add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
        alias /var/www/your-domain/diagnose/;
        index index.php;
        try_files $uri $uri/ /diagnose/index.php?$query_string;
        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php8.2-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $request_filename;
        }
    }

    location / {
        root /var/www/your-domain/landing;
        try_files $uri $uri/ /index.html;
        add_header X-Robots-Tag "index, follow, max-snippet:-1, max-image-preview:large" always;
    }
}
```

`nginx -t` 通过后 reload。

### 权限与首次初始化

```bash
chown -R www:www /var/www/your-domain/diagnose/data
chmod 755 /var/www/your-domain/diagnose/data
```

首次访问 `/diagnose/` 时，若用户表为空会自动创建管理员 `admin`，随机密码写入 `diagnose/data/initial_admin.txt`（权限 600）。**登录后立即修改密码并删除该文件**。

### 必做配置

登录后台 → 「平台配置」：

1. **站点地址与 SEO/GEO 文件**：填站点根地址（含尾斜杠），保存后会重建 `robots.txt`、`sitemap.xml`、`llms.txt`、`llms-full.txt` 及 5 个知识页的 canonical / OG / JSON-LD / 面包屑，并同步落地页 head。**换域名只改这里，不要再逐个改文件。**
2. **平台名称与品牌**：平台名、副标题、ICP 备案号。
3. **模型 Key**：不填则可见度分析降级为 demo；也可在 `.env` 里配（见 `.env.example`）。

也可以命令行一键重建：

```bash
php /var/www/your-domain/diagnose/api/seo_generate.php --sync
```

脚本基于自身路径定位文件，输出 JSON，全部成功返回码 0。注意：**以 root 执行后需确认生成文件属主仍为 www**（生成器会尝试 `chown` 到运行 PHP-FPM 的用户）；若后台保存时报「文件不可写」，执行 `chown -R www:www landing seo` 即可。

---

## 安全说明

内置 SSRF 逐跳校验（DNS 解析 / IP / 端口 / 重定向）、登录锁定、接口频控、客户隔离、PDO 参数绑定。公开仓库等于公开防御实现——这本身是常规做法，但请在公开前确认：

- 没有默认弱口令残留（首次管理员密码是随机的，且只写本地文件）
- `data/`、`.env`、`keys.json` 从未进入 git 历史
- 没有把生产环境的报告链接、客户名、ICP 留在文档或注释里

---

## 许可证与免责声明

**AGPL-3.0**（见 `LICENSE` 全文）。允许自由使用、修改与自托管；若将修改后的版本作为网络服务提供给第三方，须以 AGPL-3.0 公开相应源码。商业闭源授权可另行联系作者。

诊断结果基于公开抓取与规则分析，**仅供参考，不代表搜索引擎或大模型官方评价**，不构成任何效果承诺。请遵守目标站点的 `robots.txt` 与当地法律法规，勿用于未经授权的压力测试。

---

## 推送到公开平台

```bash
cd geo-diagnose-platform
git init
git add .
git commit -m "Initial release: GEO diagnosis platform"

# GitHub（主仓，技术影响力）
git remote add github https://github.com/<你的账号>/geo-diagnose-platform.git
git push -u github main

# Gitee（国内镜像，先私有自查，再提交公开审核）
git remote add gitee https://gitee.com/<你的账号>/geo-diagnose-platform.git
git push -u gitee main
```

两边 tag 建议对齐；不要把 token 写进 remote URL。
