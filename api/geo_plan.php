<?php
/**
 * 行业化 GEO 执行方案生成器
 * 把「品牌可见度分析」的实测数据 + 客户所在行业知识库，
 * 组装成一份可直接交付给客户、可打印的正式方案。
 * 纯函数库，不写数据库、不依赖会话。
 */

function gp_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function gp_arr($v) { return is_array($v) ? $v : []; }
function gp_num($v, $d = 0) { return is_numeric($v) ? (float)$v : $d; }

/** 行业知识库：关键词匹配 → 该类目在 AI 搜索中的真实特征与必做事项 */
function geo_industry_profiles() {
    return [
        'home' => [
            'label' => '家装 / 旧房翻新 / 建材',
            'match' => ['装修','家装','旧房','翻新','改造','建材','装饰','施工','整装','局改','水电','防水','家具','设计公司'],
            'how' => '用户多按「城市 + 具体需求 + 哪家靠谱 / 多少钱 / 怎么避坑」提问。AI 倾向引用带真实案例、报价构成、施工工序与验收标准的内容，纯品牌宣传页几乎不会被引用。',
            'platforms' => '带地名的问法在 DeepSeek、豆包、千问之间差异最大，必须按「城市 + 需求」分别采样；通用问法容易被全国性平台与装修百科占据。',
            'questions' => ['{城市}旧房翻新公司哪家好','{城市}老房改造大概多少钱一平','旧房翻新有哪些必须避的坑','{品牌}口碑和投诉怎么样','局部改造找装修公司还是工长','翻新报价单里容易漏哪些项目','水电改造的验收标准是什么'],
            'assets' => ['按「城市 / 小区 / 户型 / 面积 / 造价 / 工期」结构化的案例页（含前后对比）','按工序拆分的施工工艺说明（拆改、水电、防水、泥木、油漆、安装）','透明报价构成页：主材、辅材、人工、管理费与增项规则','避坑与验收 FAQ，答案写成可直接引用的条目','质保与售后条款页，写明年限、范围、响应时效'],
            'trust' => ['营业执照、施工资质与可核验的门店地址','带时间地点的工地实拍、开工与竣工记录','第三方平台评价、投诉处理与回访记录','报价单样本（标注面积与日期）'],
            'compliance' => ['不得使用「最」「第一」「零增项」「绝对」等极限词或无法兑现的承诺','案例须标注面积、户型、日期与适用范围','业主照片、小区名称、门牌号须获授权并脱敏','不得编造资质、获奖、合作品牌或媒体背书'],
            'note' => '本地服务类品牌在通用问法下提及率通常偏低。优先目标不是全网曝光，而是让「城市 + 需求」长尾问法稳定进入前三。',
        ],
        'edu' => [
            'label' => '教育 / 学校 / 培训',
            'match' => ['教育','学校','培训','招生','升学','学区','大学','专业','考研','留学','课程','幼儿园','职校','分数线'],
            'how' => '家长与考生按「地区 + 学段 + 排名 / 分数线 / 口碑 / 升学情况」提问。AI 更信任官方招生简章、公开数据与可核验的办学资质，对营销话术敏感。',
            'platforms' => '政策与分数类问题更依赖官网与权威媒体；择校口碑类问法更容易引用社区讨论，两者需分开采样。',
            'questions' => ['{城市}{学段}学校排名','{学校}录取分数线是多少','{学校}师资和升学情况怎么样','{专业}哪个学校好','{机构}靠谱吗，有投诉吗','{学校}招生简章在哪里看','{专业}就业方向是什么'],
            'assets' => ['招生简章 / 专业目录页，结构化并标注年份','历年分数线与录取数据表，可下载且附口径说明','师资与办学资质公示页','常见问题 FAQ（报名、住宿、学费、转专业）','课程与培养方案说明页'],
            'trust' => ['教育主管部门备案与办学许可证','官网域名与官方公众号信息一致','可核验的公开数据（分数线、招生计划）','权威媒体报道与公示文件'],
            'compliance' => ['不得承诺升学率、保过、包录取','不得使用「名校」「百分百」等无法验证的表述','招生数据须标注年份与来源，避免与官方口径冲突','涉及未成年人信息须脱敏并取得授权'],
            'note' => '教育类查询高度依赖官方数据。校名、地址、院校代码在全网一致，是首要条件。',
        ],
        'finance' => [
            'label' => '金融 / 贷款 / 保险 / 理财',
            'match' => ['金融','贷款','银行','保险','理财','信贷','额度','利率','投资','证券','基金','信用卡','抵押','融资','支付'],
            'how' => '用户按「产品 + 利率 / 额度 / 门槛 / 靠谱吗」提问。AI 会优先引用持牌机构公示、监管信息与客观对比内容，涉及收益承诺的内容通常被降权或拒答。',
            'platforms' => '合规敏感问题在多数平台会触发保守回答。不要只测「哪家好」，要同时测「条件 / 费率 / 风险」类中性问法。',
            'questions' => ['{产品}利率和费用怎么算','{机构}是持牌机构吗','{产品}申请条件是什么','{产品}有哪些风险','{品类}怎么选，需要注意什么','{机构}投诉多吗','提前还款怎么算'],
            'assets' => ['产品要素页：费率区间、期限、门槛、还款方式，附计算示例','费用与风险揭示页','持牌资质与备案信息公示页','常见问题 FAQ（申请、放款、逾期、提前还款）','术语解释与客观对比表'],
            'trust' => ['金融牌照与监管备案编号','官方公示文件与年报','权威财经媒体引用','费率计算口径可复算'],
            'compliance' => ['严禁保本保收益、夸大收益、隐瞒费用','必须显著提示风险与适用条件','不得使用「最低利率」「秒批」等无依据表述','遵守金融营销宣传相关规定，避免诱导借贷'],
            'note' => '金融类内容先过合规再谈可见度。被判为营销话术的内容即使发布，也难被引用。',
        ],
        'health' => [
            'label' => '医疗 / 健康 / 医美',
            'match' => ['医疗','医院','健康','医美','口腔','牙科','整形','体检','诊所','药','康复','皮肤科','眼科','治疗','手术','医美'],
            'how' => '用户按「症状 / 项目 + 哪家医院 / 多少钱 / 有没有风险」提问。AI 对医疗建议极为保守，倾向引用权威医学资料与机构资质，商业推广内容引用率极低。',
            'platforms' => '涉及诊疗建议的问题多数平台会加免责声明并拒答推荐。监测重点应放在「机构资质、项目说明、就诊流程」类信息型问法。',
            'questions' => ['{项目}适合什么情况','{项目}大概费用和恢复期','{医院}资质怎么样','{项目}有哪些风险','{城市}哪里可以做{项目}','{症状}应该看哪个科','术前需要做什么检查'],
            'assets' => ['科室 / 项目说明页：适应范围、流程、周期、风险','医师资质与执业信息公示页','就诊指南与费用说明页','科普 FAQ，引用权威指南并标注来源','术前术后注意事项页'],
            'trust' => ['医疗机构执业许可证与医师执业信息','权威医学指南与文献引用','公开的收费与资质信息','第三方评价中关于就诊流程的具体描述'],
            'compliance' => ['严禁保证疗效、治愈率、无风险等表述','不得使用患者案例做疗效证明或前后对比','医疗内容须标注审核人与更新日期','遵守广告法与医疗广告审查规定'],
            'note' => '医疗行业不建议追求「被推荐」，应先保证机构实体信息准确、可被客观引用。',
        ],
        'legal' => [
            'label' => '法律 / 财税 / 专业咨询',
            'match' => ['法律','律师','律所','诉讼','合同','财税','会计','审计','咨询','知识产权','商标','专利','纠纷','仲裁'],
            'how' => '用户按「纠纷类型 + 流程 / 费用 / 找谁」提问。AI 倾向引用法条、办案流程与客观说明，对结果承诺类表述明显排斥。',
            'platforms' => '法条与程序类问题在 DeepSeek、千问上更稳定；本地律师推荐类问法需按城市单独采样。',
            'questions' => ['{纠纷}处理流程是什么','{纠纷}律师费怎么收','{城市}{纠纷}找谁咨询','{法条}怎么理解','{合同}有哪些风险点','{机构}靠谱吗','诉讼时效是多久'],
            'assets' => ['按纠纷类型拆分的实务说明页：流程、材料、时限','收费方式与范围公示页','团队执业资质与执业年限页','法规解读与常见问题 FAQ','典型案例页（脱敏、不承诺结果）'],
            'trust' => ['律所执业许可证与律师执业信息','公开裁判文书与法规引用','专业媒体与行业目录收录','联系地址与电话全网一致'],
            'compliance' => ['不得承诺胜诉、赔偿金额或办案结果','不得使用「最权威」「百分百」等表述','案例须脱敏并获授权','法律意见须标注适用法域与时效'],
            'note' => '内容专业度比数量更重要。条目式、可直接引用的流程说明最容易进入回答。',
        ],
        'local' => [
            'label' => '本地生活 / 餐饮 / 到店服务',
            'match' => ['餐饮','餐厅','美食','门店','本地','附近','咖啡','酒店','民宿','美容','美发','健身','家政','维修','摄影','婚庆','探店'],
            'how' => '用户按「附近 / 城市 + 品类 + 推荐 / 人均 / 营业时间」提问，AI 高度依赖地图、点评与结构化门店信息。',
            'platforms' => '本地意图在豆包、元宝上更依赖位置与点评数据；DeepSeek 更依赖网页内容，需分别覆盖。',
            'questions' => ['{城市}{品类}哪家靠谱','{店名}人均多少','{店名}营业时间和地址','{商圈}附近有什么推荐','{店名}需要预约吗','{品类}怎么选不踩坑','{店名}有停车位吗'],
            'assets' => ['门店页：地址、电话、营业时间、交通，逐店独立','菜单 / 价目与人均说明页','预约与到店流程页','常见问题 FAQ（停车、包间、外卖、发票）','门店实拍与位置说明'],
            'trust' => ['地图与点评平台信息一致','营业状态实时准确','可核验的门店地址与电话','用户评价中的具体描述'],
            'compliance' => ['不得编造评分、排名或「必吃」等绝对化表述','价格与营业时间须及时更新','使用用户评价需注明来源','食品经营资质公示'],
            'note' => 'NAP（名称 / 地址 / 电话）一致性是本地类可见度的第一优先级。',
        ],
        'estate' => [
            'label' => '房产 / 置业 / 物业',
            'match' => ['房产','楼盘','置业','买房','房价','地产','开发商','物业','二手房','租房','公寓','商铺','板块','户型'],
            'how' => '用户按「城市 / 板块 + 房价 / 楼盘 / 政策 / 靠谱吗」提问。AI 引用备案信息、政策文件与客观数据，营销文案引用率低。',
            'platforms' => '政策类问题各平台口径差异大；价格与开盘信息需按城市单独采样。',
            'questions' => ['{城市}{板块}房价走势','{楼盘}备案价是多少','{城市}购房政策有哪些','{开发商}口碑怎么样','{楼盘}周边配套如何','{区域}值不值得买','物业费怎么收'],
            'assets' => ['楼盘信息页：备案价、户型、面积、交付时间','政策解读页，标注文件来源与日期','周边配套与交通说明页','购房流程与费用清单页','常见问答 FAQ'],
            'trust' => ['预售许可与备案信息','政府公示文件','权威媒体与数据平台引用','项目地址与开发商全称一致'],
            'compliance' => ['严禁承诺升值、学区、回报率','不得使用「绝版」「抄底」等诱导性表述','价格与面积须标注来源与有效期','政策解读须注明适用地区与时间'],
            'note' => '房产类应先解决「实体信息可核验」，再谈内容覆盖。',
        ],
        'b2b' => [
            'label' => 'B2B / 制造 / 工业 / 原材料',
            'match' => ['制造','工业','工厂','机械','设备','原材料','批发','供应','采购','加工','元器件','化工','模具','包装','工程','外贸'],
            'how' => '采购方按「产品参数 / 型号 / 认证 / 厂家 / 交期」提问。AI 倾向引用规格表、技术文档与行业目录，泛品牌页几乎不出现。',
            'platforms' => '技术参数类在 DeepSeek、千问上更依赖官网文档；供应商推荐类问法容易被 B2B 平台占据。',
            'questions' => ['{产品}参数与型号对比','{品类}厂家怎么选','{产品}需要哪些认证','{材料}执行标准是什么','{品牌}最小起订量和交期','{品类}常见质量问题','{设备}怎么维护'],
            'assets' => ['产品参数页，提供可下载的规格书 / PDF','型号对照与选型指南页','认证与检测报告公示页','应用案例与工况说明页','FAQ（交期、起订量、售后、质保）'],
            'trust' => ['ISO / 行业认证与检测报告','官网与 B2B 平台信息一致','行业协会与目录收录','可核验的工厂地址与产能信息'],
            'compliance' => ['参数、认证、产能须与检测报告一致','不得冒用认证或客户案例','案例涉及客户名称须获授权','出口合规与标准版本须标注'],
            'note' => '结构化参数表是 B2B 最容易被 AI 直接引用的内容形式。',
        ],
        'saas' => [
            'label' => 'SaaS / 软件 / 科技服务',
            'match' => ['saas','软件','系统','平台','工具','api','云服务','数字化','ai','app','小程序','开发','数据','crm','erp'],
            'how' => '用户按「场景 + 工具推荐 / 对比 / 定价 / 集成」提问。AI 常引用文档、对比表与第三方评测，官网营销页的引用率低于帮助中心。',
            'platforms' => '对比类问法在各平台差异最大，应固定 3–5 组竞品对比词持续监测。',
            'questions' => ['{场景}用什么工具','{产品}和{竞品}区别','{产品}价格和免费额度','{产品}支持哪些集成','{产品}数据怎么导出','{品类}怎么选型','{产品}安全合规吗'],
            'assets' => ['功能与定价页，结构化并标注版本与日期','帮助中心 / API 文档，段落可独立引用','与竞品的客观对比表','集成与数据迁移说明页','更新日志与安全合规页'],
            'trust' => ['文档站与第三方评测引用','安全合规认证（等保 / ISO / SOC）','公开状态页与更新日志','客户案例（获授权、可核验）'],
            'compliance' => ['功能与定价须与实际一致，避免「永久免费」等无法兑现表述','对比内容须客观、注明数据来源与日期','客户 Logo 使用须获授权','数据与安全声明须可验证'],
            'note' => '帮助中心与文档往往比首页更容易被引用，应优先结构化。',
        ],
        'retail' => [
            'label' => '电商 / 零售 / 消费品',
            'match' => ['电商','零售','消费品','品牌','商品','产品','母婴','美妆','服饰','食品','家电','数码','选购','好物'],
            'how' => '用户按「品类 + 推荐 / 对比 / 多少钱 / 值得买吗」提问。AI 依赖商品参数、评测与价格信息，品牌自述引用率低。',
            'platforms' => '推荐类问法容易被内容平台与电商榜单占据；参数类中性问法更能反映官网内容质量。',
            'questions' => ['{品类}怎么选','{品牌}和{竞品}哪个好','{产品}参数与价格','{产品}适合什么人','{品牌}售后怎么样','{品类}避坑指南','{产品}怎么保养'],
            'assets' => ['商品参数页：规格、材质、适用人群','选购指南与对比表页','价格与售后政策页','常见问题 FAQ（退换、保修、配送）','使用与保养说明页'],
            'trust' => ['第三方评测与检测报告','电商平台信息一致','公开的售后与质保条款','可核验的生产主体信息'],
            'compliance' => ['不得使用「全网最低」「销量第一」等无依据表述','参数须与检测报告一致','促销信息标注有效期','用户评价需注明来源'],
            'note' => '先让产品参数可被结构化读取，再铺设第三方信源。',
        ],
        'auto' => [
            'label' => '汽车 / 出行 / 后市场',
            'match' => ['汽车','车型','车','新能源','续航','4s','试驾','保养','二手车','充电','配件','轮胎','自驾'],
            'how' => '用户按「车型 + 价格 / 续航 / 保养 / 对比」提问。AI 依赖官方参数、权威评测与召回公告。',
            'platforms' => '对比类问法平台差异明显；保养与政策类更依赖官网与官方公告。',
            'questions' => ['{车型}落地价多少','{车型}和{竞品}怎么选','{品牌}保养费用','{车型}真实续航','{品牌}有没有召回','{城市}哪里可以试驾','{车型}有哪些配置'],
            'assets' => ['车型参数页：配置、价格、续航，标注年款','保养与保修政策页','试驾与门店信息页','常见问题 FAQ','召回与公告页'],
            'trust' => ['官方公告与工信部数据','权威媒体评测','门店与授权经销商信息','召回与质检公示'],
            'compliance' => ['续航、油耗须标注测试工况','价格须标注地区与时间','不得使用「最安全」等绝对化表述','召回与安全信息须及时公开'],
            'note' => '年款与配置版本必须清晰，否则 AI 容易引用过期参数。',
        ],
        'general' => [
            'label' => '通用行业（未匹配到专属类目）',
            'match' => [],
            'how' => '用户按「品类 / 需求 + 哪家好 / 怎么选 / 多少钱」提问。AI 倾向引用结构化、可核验、带具体数据的第三方内容。',
            'platforms' => '不同平台对同一问法的引用来源差异明显，建议固定问题词组逐平台复测。',
            'questions' => ['{品类}哪家好','{品类}怎么选','{品类}多少钱','{品牌}靠谱吗','{品类}有哪些坑','{品牌}和同业比怎么样'],
            'assets' => ['产品 / 服务说明页，含结构化参数与适用范围','价格与交付说明页','常见问题 FAQ，条目式、可直接引用','案例与资质公示页','术语解释与对比表'],
            'trust' => ['官网与第三方目录信息一致','公开资质与检测报告','权威媒体与行业目录收录','可核验的联系地址'],
            'compliance' => ['不得使用广告法极限词与无法兑现的承诺','案例与数据须标注来源与日期','引用第三方内容须获授权','个人信息须脱敏'],
            'note' => '通用行业先建立实体一致性与基线问法，再按复测结果决定内容投入方向。',
        ],
    ];
}

/** 按行业词匹配最合适的知识库类目 */
function geo_detect_industry($text) {
    $text = mb_strtolower((string)$text, 'UTF-8');
    $profiles = geo_industry_profiles();
    $best = 'general';
    $bestScore = 0;
    foreach ($profiles as $key => $p) {
        $score = 0;
        foreach ($p['match'] as $kw) {
            if ($kw !== '' && mb_strpos($text, mb_strtolower($kw, 'UTF-8')) !== false) {
                $score += mb_strlen($kw) >= 3 ? 2 : 1;
            }
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $key; }
    }
    return ['key' => $best, 'profile' => $profiles[$best], 'matched' => $bestScore > 0];
}

/** 所有行业都适用的 GEO 必检清单（"需要注意的所有内容"） */
function geo_checklist() {
    return [
        '内容层' => [
            '品牌实体信息（全称、简称、别名、地址、电话、成立时间）在全网保持一致',
            '每个核心页面有且仅有一个 H1，章节标题层级清晰',
            '存在可被独立引用的段落：一条问题对应一段 80–200 字的直接回答',
            'FAQ / 问答页覆盖用户真实问法，答案脱离上下文也能读懂',
            '案例与数据标注时间、范围、来源，避免笼统形容',
            '术语表、对比表、参数表结构化呈现，便于模型摘录',
        ],
        '技术层' => [
            'robots.txt 放行主流 AI 爬虫（GPTBot、ClaudeBot、PerplexityBot、Bytespider 等）',
            'WAF / CDN 不对 AI 爬虫做差分拦截，返回 200 且正文完整',
            '提供 llms.txt，或在首页给出站点结构与核心页面指引',
            'sitemap.xml 完整可访问，重要内页均被收录',
            'canonical、title、H1 指向一致，无重复内容冲突',
            'JSON-LD 标注 Organization / LocalBusiness / Product / FAQPage 等适用类型',
            '正文在 HTML 源码中可直接读取，不依赖前端 JS 渲染',
            '页面响应时间稳定，避免因超时导致抓取失败',
        ],
        '权威层' => [
            '官网、地图、企业信用信息、行业目录中的主体信息完全一致',
            '公开资质、检测报告、备案编号可核验',
            '有第三方媒体、行业协会或权威目录的自然引用',
            '品牌名称与产品型号使用统一写法，减少别名漏判',
            '负面或过期信息有更正渠道与更新记录',
        ],
        '分发层' => [
            '核心内容在多平台同步，避免单一平台抓取失败即失去信源',
            '问答社区、行业媒体、公众号文字稿覆盖目标问法',
            '第三方内容中的品牌描述与官网口径一致',
            '重要页面有内链与站点入口，便于爬虫发现',
        ],
        '监测层' => [
            '固定问题词组（建议 20–30 条）、固定平台、固定周期复测',
            '失败样本单独记录，不补零、不进入分母',
            '记录品牌别名与竞品别名，统一归一化口径',
            '标记负面语境与错误事实，作为优先修复项',
            '保存每轮原始回答快照，便于回溯与对比趋势',
        ],
    ];
}

/**
 * 生成方案 HTML。
 * $ctx = [report, result, brand_name, id, generated_at, score, score_text, grade]
 */
function geo_plan_html($ctx) {
    $report = gp_arr($ctx['report'] ?? []);
    $result = gp_arr($ctx['result'] ?? []);
    $brandName = (string)($ctx['brand_name'] ?? '');
    $subject = (string)($result['brand'] ?? $report['brand'] ?? '');
    $industryText = (string)($result['industry'] ?? $report['industry'] ?? '');
    $question = (string)($result['question'] ?? $report['question'] ?? '');
    $mode = (string)($report['mode'] ?? $result['mode'] ?? '演示采样');
    $det = geo_detect_industry($industryText);
    $p = $det['profile'];

    $m = gp_arr($result['metrics'] ?? []);
    $plats = gp_arr($result['platforms'] ?? []);
    $comp = gp_arr($result['competitors'] ?? []);
    $sources = gp_arr($result['sources'] ?? []);
    $findings = gp_arr($result['findings'] ?? []);
    $raw = gp_arr($result['raw_samples'] ?? []);

    $rate = gp_num($m['mention_rate'] ?? 0);
    $top1 = isset($m['top1_rate']) ? gp_num($m['top1_rate']) : null;
    $top3 = isset($m['top3_rate']) ? gp_num($m['top3_rate']) : null;
    $valid = (int)($m['valid_samples'] ?? 0);
    $total = (int)($m['total_samples'] ?? 0);
    $rateText = $rate . '%';
    $platformCount = count($plats);
    $failed = [];
    foreach ($plats as $pl) {
        $pl = gp_arr($pl);
        if (($pl['mention_rate'] ?? null) === null || !empty($pl['error'])) {
            $failed[] = $pl['name'] ?? $pl['id'] ?? '未知平台';
        }
    }
    $topComp = null;
    foreach ($comp as $c) {
        $c = gp_arr($c);
        if (!$topComp || gp_num($c['mentions'] ?? 0) > gp_num($topComp['mentions'] ?? 0)) $topComp = $c;
    }
    $maxComp = 1;
    foreach ($comp as $c) $maxComp = max($maxComp, gp_num(gp_arr($c)['mentions'] ?? 0));

    // ---- 依据实测数据自动推导优先级问题 ----
    $actions = [];
    if ($rate < 20) {
        $actions[] = ['P0', '品牌在多数问法下未被提及', '综合提及率 ' . $rateText . '，有效样本 ' . $valid . ' 个。', 'AI 回答中缺少可引用的品牌实体与内容信源，模型无法把品牌与该行业需求建立关联。', '先统一全网品牌实体信息，再按行业问题词上线 3–5 个可独立引用的条目式内容页。', '下一轮复测，同一问题词组在 ≥3 个平台出现品牌，提及率提升 ≥10 个百分点。'];
    } elseif ($rate < 50) {
        $actions[] = ['P1', '品牌提及率处于低位', '综合提及率 ' . $rateText . '，尚未形成稳定推荐。', '内容覆盖了部分问法，但缺少针对长尾问法的结构化答案与第三方信源。', '按「城市 / 场景 / 对比 / 费用」补齐长尾问法内容，并铺设权威信源。', '提及率提升至 ≥50%，且至少 2 个平台稳定命中。'];
    }
    if ($top1 !== null && $top1 < 10) {
        $actions[] = ['P1', '进入推荐首位的比例偏低', 'Top1 首位率 ' . $top1 . '%。', 'AI 能提到品牌，但通常排在竞品之后，说明权威信号与差异化证据不足。', '补充可核验的资质、案例数据、第三方评测与客观对比表。', 'Top1 首位率提升 ≥5 个百分点，或 Top3 率提升 ≥15 个百分点。'];
    }
    if ($failed) {
        $actions[] = ['P1', '部分平台采样无有效结果', '未取得有效数据的平台：' . implode('、', $failed) . '。', '可能是接口未配置、超时或平台拒答，导致该渠道结论不可比。', '检查对应平台 API Key 与网络连通性，或改用该平台可回答的中性问法重测。', '所有目标平台均返回有效样本，失败样本单独记录且不补零。'];
    }
    if ($topComp && gp_num($topComp['mentions'] ?? 0) > 0) {
        $actions[] = ['P1', '竞品占据 AI 推荐位', '本轮被提及最多的竞品：' . ($topComp['name'] ?? '—') . '（' . ($topComp['mentions'] ?? 0) . ' 次）。', '竞品在第三方信源、结构化内容或行业目录中的覆盖率更高。', '对齐竞品被引用最多的内容形式，产出客观对比表与选型指南，不贬低同业。', '在对比类问法中品牌与主要竞品同时出现，且事实描述准确。'];
    }
    if (count($sources) < 3) {
        $actions[] = ['P2', '外部信源数量偏少', '本轮识别到的引用信源 ' . count($sources) . ' 个。', '信源集中会让模型对该品牌的认知依赖单一来源，稳定性差。', '在行业目录、权威媒体、问答社区建立一致的品牌实体信息。', '可识别信源 ≥5 个，且与官网主体信息一致。'];
    }
    if ($valid < 30) {
        $actions[] = ['P2', '有效样本量不足，结论波动风险高', '有效样本 ' . $valid . ' / ' . $total . '。', '样本过少时，单次提及对百分比影响过大。', '固定 20–30 条问题词 × 全平台复测，连续观察 3 轮再判断趋势。', '单轮有效样本 ≥30，并输出至少 3 轮趋势对比。'];
    }
    foreach ($findings as $f) {
        $f = gp_arr($f);
        $lv = strtoupper((string)($f['level'] ?? ''));
        $pri = ($lv === 'HIGH' || $lv === 'CRITICAL' || $lv === 'P0') ? 'P0' : (($lv === 'LOW' || $lv === 'P2') ? 'P2' : 'P1');
        $actions[] = [$pri, (string)($f['text'] ?? '诊断发现'), '来自本轮可见度诊断引擎。', '该问题会影响品牌在 AI 回答中的识别或引用。', (string)($f['fix'] ?? '按诊断建议处理'), '复测时该现象消失或明显减轻。'];
    }
    if (!$actions) {
        $actions[] = ['P2', '未发现明显阻断性问题', '综合提及率 ' . $rateText . '，平台覆盖 ' . $platformCount . ' 个。', '当前基线正常，主要工作是维持并扩展问法覆盖。', '按季度扩展长尾问题词，持续监测竞品与负面语境。', '连续 3 轮复测指标稳定或提升，且无负面语境。'];
    }
    $p0 = 0; $p1 = 0;
    foreach ($actions as $a) { if ($a[0] === 'P0') $p0++; elseif ($a[0] === 'P1') $p1++; }

    $checks = geo_checklist();
    $pmeta = function_exists('platformMeta') ? platformMeta() : [];
    $pname = function($id) use ($pmeta) { return $pmeta[$id]['name'] ?? $id; };

    ob_start(); ?>
    <div class="plan-summary">
      <div class="ps-kicker">Executive Summary · 执行摘要</div>
      <h2><?= gp_h($subject) ?>：AI 搜索可见度优化方案</h2>
      <p>本方案基于 <?= gp_h($valid) ?> 个有效采样、<?= gp_h($platformCount) ?> 个平台的一手数据，结合<strong><?= gp_h($p['label']) ?></strong>行业在 AI 搜索中的提问与引用特征编制。当前品牌综合提及率 <strong><?= gp_h($rateText) ?></strong>，共识别 <strong><?= count($actions) ?></strong> 项待办（其中 P0 <strong><?= $p0 ?></strong> 项、P1 <strong><?= $p1 ?></strong> 项）。建议按「先技术放行与实体统一 → 再内容资产 → 后信源分发 → 持续复测」的顺序推进，首轮复测安排在内容上线后第 4 周。</p>
      <div class="ps-figs">
        <div><b><?= gp_h($rateText) ?></b><span>品牌提及率</span></div>
        <div><b><?= $top1 !== null ? gp_h($top1 . '%') : '—' ?></b><span>Top1 首位率</span></div>
        <div><b><?= $top3 !== null ? gp_h($top3 . '%') : '—' ?></b><span>Top3 前三率</span></div>
        <div><b><?= gp_h($valid) ?>/<?= gp_h($total) ?></b><span>有效 / 总样本</span></div>
        <div><b><?= gp_h($platformCount) ?></b><span>采样平台</span></div>
      </div>
    </div>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">01</span><h3>行业 AI 搜索特征与必检事项</h3><span class="ps-note">行业词：<?= gp_h($industryText ?: '未填写') ?></span></div>
      <p class="plan-lead">识别类目：<b><?= gp_h($p['label']) ?></b><?= $det['matched'] ? '' : '（未匹配到专属关键词，采用通用口径；可在工作台补充更具体的行业词后重新诊断）' ?>。以下内容决定「该写什么、写给谁看、在哪里被引用」。</p>
      <dl class="plan-def">
        <div><dt>用户实际怎么问</dt><dd><?= gp_h($p['how']) ?></dd></div>
        <div><dt>平台差异与采样重点</dt><dd><?= gp_h($p['platforms']) ?></dd></div>
        <div><dt>行业经验基准</dt><dd><?= gp_h($p['note']) ?></dd></div>
        <div><dt>本轮采样问题</dt><dd><?= gp_h($question ?: '由系统按行业词自动生成意图问句') ?>（模式：<?= gp_h($mode) ?>）</dd></div>
      </dl>

      <p class="plan-lead"><b>建议纳入长期监测的问题词（按行业模板替换占位符）：</b></p>
      <div class="plan-chips"><?php foreach ($p['questions'] as $q): ?><span class="plan-chip"><?= gp_h($q) ?></span><?php endforeach; ?></div>

      <p class="plan-lead"><b>该行业需要建设的内容资产：</b></p>
      <ul class="plan-list"><?php foreach ($p['assets'] as $a): ?><li><?= gp_h($a) ?></li><?php endforeach; ?></ul>

      <p class="plan-lead" style="margin-top:16px"><b>AI 判断可信度时依赖的权威信号：</b></p>
      <ul class="plan-list"><?php foreach ($p['trust'] as $t): ?><li><?= gp_h($t) ?></li><?php endforeach; ?></ul>

      <p class="plan-lead" style="margin-top:18px"><b>所有行业通用的 GEO 必检清单：</b>下表为交付前逐项核对用，任一层的缺失都会削弱其他层的效果。</p>
      <table class="table plan-table"><tr><th style="width:88px">层面</th><th>必检项</th></tr>
        <?php foreach ($checks as $layer => $items): ?>
          <tr><td><?= gp_h($layer) ?></td><td><ul class="plan-list" style="margin:0;padding-left:16px"><?php foreach ($items as $it): ?><li><?= gp_h($it) ?></li><?php endforeach; ?></ul></td></tr>
        <?php endforeach; ?>
      </table>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">02</span><h3>现状诊断与差距</h3><span class="ps-note">数据来自本轮实测</span></div>
      <p class="plan-lead">下表为方案的事实依据。失败样本不补零、不计入分母，因此百分比只反映有效回答。</p>
      <table class="table plan-table"><tr><th>平台</th><th>提及率</th><th>命中 / 样本</th><th>可视化</th><th>备注</th></tr>
        <?php if (!$plats): ?><tr><td colspan="5">本轮未产生平台明细数据</td></tr><?php endif; ?>
        <?php foreach ($plats as $pl): $pl = gp_arr($pl); $r = $pl['mention_rate'] ?? null; ?>
          <tr>
            <td><?= gp_h($pl['name'] ?? $pname($pl['id'] ?? '')) ?></td>
            <td><?= $r === null ? '—' : gp_h($r) . '%' ?></td>
            <td><?= gp_h($pl['hits'] ?? 0) ?> / <?= gp_h($pl['samples'] ?? 0) ?></td>
            <td><div class="bar"><i style="width:<?= $r === null ? 0 : gp_h(min(100, gp_num($r))) ?>%;background:<?= $r === null ? '#96978E' : 'var(--brand)' ?>"></i></div></td>
            <td><?= gp_h($pl['error'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php if ($comp): ?>
        <p class="plan-lead" style="margin-top:16px"><b>竞品被提及格局：</b></p>
        <table class="table plan-table"><tr><th>竞品</th><th>提及次数</th><th style="width:50%">对比</th></tr>
          <?php foreach ($comp as $c): $c = gp_arr($c); $n = gp_num($c['mentions'] ?? 0); ?>
            <tr><td><?= gp_h($c['name'] ?? '') ?></td><td><?= gp_h($n) ?></td><td><div class="bar"><i style="width:<?= gp_h($n / $maxComp * 100) ?>%;background:#6E7066"></i></div></td></tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
      <?php if ($sources || !empty($result['alias_note'])): ?>
        <p class="plan-lead" style="margin-top:16px"><b>识别到的引用信源：</b></p>
        <div class="plan-chips"><?php foreach ($sources as $s): $s = gp_arr($s); ?><span class="plan-chip"><?= gp_h($s['domain'] ?? '') ?> · <?= gp_h($s['count'] ?? 0) ?> 次</span><?php endforeach; ?></div>
        <?php if (!empty($result['alias_note'])): ?><div class="plan-note"><b>别名归一化说明：</b><?= gp_h($result['alias_note']) ?></div><?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">03</span><h3>优先级问题清单</h3><span class="ps-note">P0 立即 / P1 本季度 / P2 持续</span></div>
      <?php foreach ($actions as $a): $cls = strtolower($a[0]); ?>
        <div class="plan-action <?= gp_h($cls) ?>">
          <div class="pa-h"><span class="pa-tag"><?= gp_h($a[0]) ?></span><span class="pa-t"><?= gp_h($a[1]) ?></span></div>
          <dl>
            <dt>现象</dt><dd><?= gp_h($a[2]) ?></dd>
            <dt>原因</dt><dd><?= gp_h($a[3]) ?></dd>
            <dt>动作</dt><dd><b><?= gp_h($a[4]) ?></b></dd>
            <dt>验收</dt><dd><?= gp_h($a[5]) ?></dd>
          </dl>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">04</span><h3>GEO 执行方案（四条工作流）</h3><span class="ps-note">可直接作为交付排期依据</span></div>
      <table class="table plan-table"><tr><th style="width:86px">工作流</th><th>具体动作</th><th>交付物</th><th style="width:76px">建议负责</th><th style="width:24%">验收标准</th></tr>
        <tr><td>A · 内容资产</td><td>按行业问题词建设可独立引用的条目式页面，优先覆盖 <?= gp_h(mb_substr((string)$p['assets'][0], 0, 34)) ?> 等核心资产</td><td>3–5 个结构化内容页 + FAQ 条目</td><td>内容 / 市场</td><td>每条问题对应一段 80–200 字直接答案，可被单独摘录</td></tr>
        <tr><td>B · 技术放行</td><td>逐项核对必检清单的「技术层」：robots、AI 爬虫 UA 差分、llms.txt、sitemap、canonical、JSON-LD、正文可抓取性</td><td>官网 GEO 复测报告（得分 ≥85）</td><td>技术 / SEO</td><td>AI 爬虫返回 200 且正文完整，结构化数据可被解析</td></tr>
        <tr><td>C · 权威信源</td><td>统一品牌实体信息，补齐资质公示与第三方目录收录，覆盖行业特有信号：<?= gp_h(mb_substr((string)$p['trust'][0], 0, 30)) ?></td><td>NAP 一致性对照表 + 信源清单</td><td>品牌 / PR</td><td>≥5 个可识别信源，且主体信息与官网完全一致</td></tr>
        <tr><td>D · 监测迭代</td><td>固定 20–30 条问题词 × 全平台，按周期复测；记录别名、负面语境、失败样本与原始回答快照</td><td>月度趋势报告 + 原始样本归档</td><td>运营 / 分析</td><td>连续 3 轮可比，失败样本单独记录且不补零</td></tr>
      </table>
      <div class="plan-note"><b>顺序说明：</b>B 是前置条件——若 AI 爬虫被拦截或正文依赖 JS 渲染，A 与 C 的内容无法被读取；D 用于验证前三项是否真正产生效果。四者缺一，提及率提升都难以稳定。</div>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">05</span><h3>90 天实施排期</h3></div>
      <table class="table plan-table"><tr><th style="width:96px">阶段</th><th>工作重点</th><th>产出</th><th>里程碑</th></tr>
        <tr><td>第 0–2 周<br><span style="color:var(--faint);font-size:10px">基线</span></td><td>完成技术层放行与修复；统一全网品牌实体信息；建立 20–30 条基线问题词与竞品对照词；保存本轮原始回答快照</td><td>技术核对表、问题词库、基线报告</td><td>官网 GEO 复测达标，所有平台可正常采样</td></tr>
        <tr><td>第 3–6 周<br><span style="color:var(--faint);font-size:10px">建设</span></td><td>按工作流 A 上线核心内容资产；按 C 铺设权威信源；同步多平台分发，保证口径一致</td><td>3–5 个内容页、FAQ、信源清单</td><td>核心问法均有可引用页面</td></tr>
        <tr><td>第 7–12 周<br><span style="color:var(--faint);font-size:10px">验证</span></td><td>首轮全量复测；针对未命中问法补内容；扩展长尾问法与对比类问法；输出趋势对比</td><td>复测报告、趋势图、下一轮清单</td><td>提及率 / Top3 率较基线有可量化提升</td></tr>
      </table>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">06</span><h3>KPI 与复测节奏</h3></div>
      <table class="table plan-table plan-kpi"><tr><th>指标</th><th>当前值</th><th>季度目标（建议）</th><th>口径说明</th><th>复测频率</th></tr>
        <tr><td>品牌提及率</td><td><b><?= gp_h($rateText) ?></b></td><td>较基线提升 ≥10 个百分点</td><td>命中品牌的回答 / 有效样本</td><td>每 2 周</td></tr>
        <tr><td>Top3 前三率</td><td><b><?= $top3 !== null ? gp_h($top3 . '%') : '—' ?></b></td><td>提升 ≥15 个百分点</td><td>品牌出现在推荐前三位的比例</td><td>每 2 周</td></tr>
        <tr><td>有效平台覆盖</td><td><b><?= gp_h($platformCount - count($failed)) ?>/<?= gp_h($platformCount) ?></b></td><td>全部平台均有效</td><td>返回可判定回答的平台数</td><td>每轮</td></tr>
        <tr><td>有效样本量</td><td><b><?= gp_h($valid) ?></b></td><td>≥30 / 轮</td><td>失败样本不补零、不进入分母</td><td>每轮</td></tr>
        <tr><td>负面语境占比</td><td><b>按原始样本核对</b></td><td>0</td><td>命中但伴随负面 / 错误事实的比例</td><td>每轮</td></tr>
        <tr><td>可识别信源数</td><td><b><?= gp_h(count($sources)) ?></b></td><td>≥5 且信息一致</td><td>回答中出现的引用域名去重</td><td>每月</td></tr>
      </table>
    </section>

    <section class="plan-sec">
      <div class="plan-sec-h"><span class="pn">07</span><h3>合规与风险红线</h3><span class="ps-note">交付前必须逐条确认</span></div>
      <p class="plan-lead"><b><?= gp_h($p['label']) ?>行业专属：</b></p>
      <ul class="plan-list"><?php foreach ($p['compliance'] as $c): ?><li><?= gp_h($c) ?></li><?php endforeach; ?></ul>
      <div class="plan-note plan-warn">
        <b>通用红线：</b>①不得使用广告法极限词或无法兑现的承诺；②不得向客户承诺「保证 AI 排名 / 一定被推荐」——大模型回答具有随机性，任何机构无法保证；③案例、数据、资质须标注来源与日期并可核验；④引用第三方内容、客户 Logo、用户评价须取得授权；⑤涉及个人信息须脱敏；⑥本方案结论基于单次采样快照，仅供优化参考，不构成搜索引擎或大模型官方评价。<br>
        <b>方法边界：</b>AI 平台会随模型版本、地域、时间调整回答；建议以「固定问题词 + 固定平台 + 多轮趋势」判断效果，避免用单次结果做商业承诺。
      </div>
    </section>

    <section class="plan-appendix">
      <h3>附录 · 采样口径与原始数据</h3>
      <p>以下内容用于复核上述结论，可打印归档。报告编号 <?= gp_h($ctx['id'] ?? '') ?> · 生成时间 <?= gp_h($ctx['generated_at'] ?? '') ?></p>
      <div class="plan-note"><b>口径：</b>问题词「<?= gp_h($question ?: '自动生成意图问句') ?>」；平台 <?= gp_h($platformCount) ?> 个；有效样本 <?= gp_h($valid) ?> / 总样本 <?= gp_h($total) ?>；失败样本不补零、不计入分母；品牌别名按归一化规则识别；命中但伴随负面语境的样本单独标记。<?= gp_h($result['notice'] ?? '单次采样为快照，建议多次趋势观察。') ?></div>
      <?php if ($findings): ?>
        <p class="plan-lead" style="margin-top:16px"><b>引擎原始发现：</b></p>
        <?php foreach ($findings as $f): $f = gp_arr($f); ?>
          <div class="issue <?= gp_h($f['level'] ?? '') ?>" style="box-shadow:none"><div class="it"><?= gp_h($f['text'] ?? '') ?></div><div class="if">建议：<?= gp_h($f['fix'] ?? '') ?></div></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($raw): ?>
        <p class="plan-lead" style="margin-top:16px"><b>原始回答样本：</b></p>
        <?php foreach ($raw as $s): $s = gp_arr($s); $hit = gp_arr($s['hit'] ?? []); ?>
          <details><summary><?= gp_h($pname($s['platform'] ?? '')) ?> · <?= !empty($hit['hit']) ? '命中品牌' : '未命中' ?><?= !empty($s['rank']) ? ' · 推荐第 ' . gp_h($s['rank']) . ' 位' : '' ?><?= !empty($hit['negative']) ? ' · 负面语境' : '' ?><?= !empty($s['question']) ? ' · ' . gp_h(mb_substr((string)$s['question'], 0, 28)) : '' ?></summary>
            <p><?= gp_h(mb_substr((string)($s['text'] ?? ''), 0, 1200)) ?></p>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
