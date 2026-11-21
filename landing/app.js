const API = '/diagnose/api/index.php';
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

function toast(msg) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove('show'), 2600);
}

async function api(params, body) {
  const qs = typeof params === 'string'
    ? 'action=' + encodeURIComponent(params)
    : new URLSearchParams(params).toString();
  const opt = body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
    : {};
  const r = await fetch(API + '?' + qs, opt);
  return r.json();
}

function openModal(id) {
  closeModal();
  $('#' + id).style.display = 'flex';
  setTimeout(() => $('#' + id + ' input')?.focus(), 60);
}
function closeModal() {
  $$('.modal-overlay').forEach(m => m.style.display = 'none');
}
function showErr(el, msg) {
  el.textContent = msg;
  el.style.display = msg ? 'block' : 'none';
}

$$('.js-register').forEach(b => b.onclick = () => openModal('registerModal'));
$$('.js-login').forEach(b => b.onclick = () => openModal('loginModal'));
$$('[data-close]').forEach(b => b.onclick = closeModal);
$$('.modal-overlay').forEach(m => m.onclick = e => { if (e.target === m) closeModal(); });
$('.js-switch-register').onclick = () => openModal('registerModal');
$('.js-switch-login').onclick = () => openModal('loginModal');

const LEGAL_TEXT = {
  agreement: ['用户协议', `
    <h4>一、服务内容</h4>
    <p>本平台提供官网 GEO 诊断与品牌 AI 可见度分析服务，输出结果基于公开网页抓取与大语言模型采样，仅供优化参考，不构成任何商业承诺、投资建议或官方评价。</p>
    <h4>二、账号与额度</h4>
    <p>你需要使用真实信息注册账号并妥善保管密码。注册赠送额度、邀请奖励与兑换码所得额度仅可用于本平台诊断功能，不可兑换现金、不可转让。平台有权调整免费额度规则，并对滥用、批量注册、恶意刷取奖励的账号采取限制措施。</p>
    <h4>三、使用规范</h4>
    <p>不得利用本平台对非自有或未获授权的网站发起高频抓取，不得逆向工程、干扰服务运行或用于任何违法用途。诊断请求会向目标站点发起访问，请自行确保已获得相应授权。</p>
    <h4>四、报告与分享</h4>
    <p>你可以生成带自有品牌的报告并通过链接分享。分享链接凭令牌访问，持有者即可查看，请自行控制传播范围；可在工作台刷新令牌使旧链接失效。报告链接可能设有有效期。</p>
    <h4>五、免责与变更</h4>
    <p>平台不对诊断结果的准确性、完整性或适用性作明示或默示担保，亦不对因使用本报告产生的直接或间接损失负责。本协议可能随服务调整而更新，继续使用即视为接受更新后的条款。</p>`],
  privacy: ['隐私政策', `
    <h4>一、我们收集的信息</h4>
    <p>注册时收集账号、名称、公司（选填）与密码摘要（不可逆加密存储）；使用服务时记录诊断记录、IP 地址、登录与操作日志，用于配额结算、安全风控与故障排查。我们不会存储你的明文密码。</p>
    <h4>二、信息的使用</h4>
    <p>仅用于：提供诊断与报告服务、额度与邀请奖励结算、频控与防刷、安全审计。不会将你的个人信息出售或用于与本服务无关的营销。</p>
    <h4>三、诊断数据</h4>
    <p>官网诊断会访问你输入的网址并保存抓取摘要与检查结果；品牌可见度在「真实 API 模式」下会将你填写的问题词发送给相应的大模型服务商。请勿在输入中填写敏感个人信息。</p>
    <h4>四、Cookie 与存储</h4>
    <p>我们使用会话 Cookie 维持登录状态，并在浏览器本地保存邀请码来源（30 天）。你可通过清除浏览器数据移除本地存储，但可能影响登录状态与邀请归属。</p>
    <h4>五、数据保留与删除</h4>
    <p>诊断记录与日志会保留用于历史查询与安全审计。如需删除账号及相关数据，请联系平台管理员。我们会采取访问控制、密码哈希与传输加密等合理措施保护数据安全。</p>`]
};
$$('.js-legal').forEach(a => a.onclick = e => {
  e.preventDefault();
  const t = LEGAL_TEXT[a.dataset.legal] || LEGAL_TEXT.agreement;
  $('#legalTitle').textContent = t[0];
  $('#legalBody').innerHTML = t[1];
  openModal('legalModal');
});

async function loadConfig() {
  try {
    const r = await api('public_config');
    // 平台名称由后台「平台配置」驱动；HTML 中的静态默认值保证首屏与 SEO 不延迟
    const pname = (r.app_name || '').trim();
    const ptag = (r.platform_tagline || '').trim();
    if (pname) {
      $$('[data-brand="name"]').forEach(el => el.textContent = pname);
      document.title = pname + ' · 官网 GEO 诊断与品牌 AI 可见度分析平台';
      const og = document.querySelector('meta[property="og:title"]');
      if (og) og.setAttribute('content', pname + ' · 让品牌在 AI 回答中被看见');
      $$('.logo[aria-label]').forEach(el => el.setAttribute('aria-label', pname + '首页'));
      // JSON-LD 中的品牌名由后台保存时回写静态 head，避免 JS 注入造成爬虫看到的实体不一致
    }
    if (ptag) $$('[data-brand="tagline"]').forEach(el => el.textContent = ptag);
    $$('.js-free-quota').forEach(el => el.textContent = r.free_quota);
    if (!r.register_enabled) $$('.js-register').forEach(el => el.style.display = 'none');
    // 邀请文案
    const inv = r.invite || {};
    if (inv.enabled && inv.reward > 0) {
      const note = $('#inviteBannerNote');
      if (note) note.textContent = inv.invitee_bonus > 0
        ? `通过邀请注册，你额外得 ${inv.invitee_bonus} 次奖励，你的好友也得 ${inv.reward} 次`
        : `通过邀请注册，你的好友将获得 ${inv.reward} 次奖励额度`;
    }
    const icp = $('#siteIcp');
    if (icp) icp.textContent = r.site_icp ? r.site_icp : '';
  } catch (e) {}
}

// ===== 邀请码：URL 优先，30 天内 localStorage 兜底 =====
const REF_KEY = 'geo_invite_ref';
const REF_TTL = 30 * 24 * 3600 * 1000;

function pickRef() {
  const urlRef = (params.get('ref') || '').trim().toUpperCase();
  if (urlRef) {
    try { localStorage.setItem(REF_KEY, JSON.stringify({ ref: urlRef, t: Date.now() })); } catch (e) {}
    return urlRef;
  }
  try {
    const saved = JSON.parse(localStorage.getItem(REF_KEY) || 'null');
    if (saved && saved.ref && Date.now() - saved.t < REF_TTL) return saved.ref;
  } catch (e) {}
  return '';
}

async function checkSession() {
  try {
    const r = await api('me');
    if (!r.user) return;
    $$('.js-login').forEach(b => {
      b.textContent = '进入工作台';
      b.onclick = () => location.href = '/diagnose/';
    });
    $$('.js-register').forEach(b => {
      b.textContent = '进入工作台';
      b.onclick = () => location.href = '/diagnose/';
    });
  } catch (e) {}
}

$('#loginForm').onsubmit = async e => {
  e.preventDefault();
  const btn = $('#loginForm button[type=submit]');
  btn.disabled = true;
  showErr($('#loginErr'), '');
  try {
    const r = await api('login', {
      username: $('#loginForm [name=username]').value.trim(),
      password: $('#loginForm [name=password]').value,
    });
    if (!r.ok) {
      showErr($('#loginErr'), r.message || '登录失败');
      return;
    }
    if (pendingDiagnosis) {
      closeModal();
      toast('登录成功，正在提交诊断…');
      setTimeout(submitDiagnosis, 300);
      return;
    }
    location.href = '/diagnose/';
  } catch (err) {
    showErr($('#loginErr'), '网络异常，请稍后重试');
  } finally {
    btn.disabled = false;
  }
};

$('#registerForm').onsubmit = async e => {
  e.preventDefault();
  const pass = $('#registerForm [name=password]').value;
  const confirm = $('#registerForm [name=confirm]').value;
  if (pass !== confirm) {
    showErr($('#registerErr'), '两次输入的密码不一致');
    return;
  }
  const btn = $('#registerForm button[type=submit]');
  btn.disabled = true;
  showErr($('#registerErr'), '');
  try {
    const r = await api('register', {
      username: $('#registerForm [name=username]').value.trim(),
      name: $('#registerForm [name=name]').value.trim(),
      company: $('#registerForm [name=company]').value.trim(),
      password: pass,
      website: $('#registerForm [name=website]').value,
      ref: pickRef(),
    });
    if (!r.ok) {
      showErr($('#registerErr'), r.error || '注册失败');
      return;
    }
    try { localStorage.removeItem(REF_KEY); } catch (e) {}
    if (pendingDiagnosis) {
      closeModal();
      toast(r.invite && r.invite.ok && r.invite.reward > 0
        ? '注册成功！邀请奖励已发放，正在生成你的第一份报告…'
        : '注册成功，正在生成报告…');
      setTimeout(submitDiagnosis, 400);
      return;
    }
    if (r.invite && r.invite.ok && r.invite.reward > 0) {
      toast('注册成功！好友邀请奖励已发放，正在进入工作台…');
    } else {
      toast('注册成功，正在进入工作台…');
    }
    setTimeout(() => location.href = '/diagnose/', 600);
  } catch (err) {
    showErr($('#registerErr'), '网络异常，请稍后重试');
  } finally {
    btn.disabled = false;
  }
};

/* ============================================================
   先诊断后注册：填表 → 暂存 → 注册/登录 → 自动提交 → 打开报告
   ============================================================ */
const START_PLATFORMS = [['deepseek','DeepSeek'],['doubao','豆包'],['qwen','千问'],['wenxin','文心'],['yuanbao','元宝']];
const PENDING_KEY = 'geo_pending_diagnosis';
let startType = 'audit';
let pendingDiagnosis = null;
let submitting = false;

(function initStartForm(){
  const box = $('#startPlatforms');
  if (box) box.innerHTML = START_PLATFORMS.map(([id,name],i) =>
    `<label class="start-check"><input type="checkbox" value="${id}" ${i<3?'checked':''}><span>${name}</span></label>`).join('');

  $$('.quick-sites a').forEach(a => a.onclick = e => {
    e.preventDefault();
    const u = $('#startUrl'); if (u) u.value = a.dataset.url || '';
  });

  function switchType(t) {
    startType = t;
    $$('.start-tab').forEach(x => x.classList.toggle('active', (x.dataset.type || '') === t));
    const isAudit = t === 'audit';
    const af = $('#startAuditFields'), vf = $('#startVisFields');
    if (af) af.style.display = isAudit ? '' : 'none';
    if (vf) vf.style.display = isAudit ? 'none' : '';
    showErr($('#startErr'), '');
  }
  $$('.start-tab').forEach(b => b.onclick = () => switchType(b.dataset.type || 'audit'));

  $$('.js-start').forEach(b => b.onclick = () => {
    const sec = $('#start');
    if (sec) sec.scrollIntoView({ behavior:'smooth', block:'start' });
    setTimeout(() => {
      const el = startType === 'audit' ? $('#startUrl') : $('#startBrand');
      if (el) el.focus({ preventScroll:true });
    }, 520);
  });

  // 刷新页面后保留已填信息（不自动提交、不扣额度）
  try {
    const saved = JSON.parse(sessionStorage.getItem(PENDING_KEY) || 'null');
    if (saved && saved.type && saved.payload) {
      pendingDiagnosis = saved;
      switchType(saved.type);
      const p = saved.payload;
      if (p.url && $('#startUrl')) $('#startUrl').value = p.url;
      if (p.brand && $('#startBrand')) $('#startBrand').value = p.brand;
      if (p.industry && $('#startIndustry')) $('#startIndustry').value = p.industry;
      if (p.question && $('#startQuestion')) $('#startQuestion').value = p.question;
      if (p.platforms && p.platforms.length) $$('#startPlatforms input').forEach(c => c.checked = p.platforms.includes(c.value));
      if (p.mode) { const r = $('input[name=startMode][value="' + p.mode + '"]'); if (r) r.checked = true; }
    }
  } catch (e) {}
})();

function readStartForm() {
  if (startType === 'audit') {
    const url = ($('#startUrl')?.value || '').trim();
    if (!url) return { err:'请填写官网地址' };
    if (!/^https?:\/\/[^\s]+$/i.test(url)) return { err:'请输入完整网址，例如 https://your-site.com' };
    return { type:'audit', payload:{ url } };
  }
  const brand = ($('#startBrand')?.value || '').trim();
  const industry = ($('#startIndustry')?.value || '').trim();
  if (!brand) return { err:'请填写品牌名称' };
  if (!industry) return { err:'请填写行业词 / 核心词' };
  const platforms = $$('#startPlatforms input:checked').map(c => c.value);
  if (!platforms.length) return { err:'请至少选择一个采样平台' };
  const mode = $('input[name=startMode]:checked')?.value || 'demo';
  const question = ($('#startQuestion')?.value || '').trim();
  return { type:'visibility', payload:{ brand, industry, question, platforms, mode } };
}

function setRegisterCopy(hasPending) {
  const h = $('#registerModal h2'), sub = $('#registerModal .modal-sub'), btn = $('#registerForm button[type=submit]');
  if (h) h.textContent = hasPending ? '注册后立即生成报告' : '免费注册';
  if (sub) sub.innerHTML = hasPending
    ? '新用户赠送 <b class="js-free-quota">5</b> 次诊断额度。注册成功后会直接提交刚才填好的信息，无需重新输入。'
    : '新用户赠送 <b class="js-free-quota">5</b> 次诊断额度，注册后自动登录';
  if (btn) btn.textContent = hasPending ? '注册并生成报告' : '注册并进入工作台';
  if (hasPending) loadConfig();
}

async function submitDiagnosis() {
  if (!pendingDiagnosis || submitting) return;
  submitting = true;
  const { type, payload } = pendingDiagnosis;
  const form = $('#startForm'), prog = $('#startProgress'), txt = $('#startProgressText');
  if (form) form.style.display = 'none';
  if (prog) prog.style.display = 'flex';
  if (txt) txt.textContent = type === 'audit'
    ? '正在抓取官网并生成诊断报告，可能需要十几秒…'
    : (payload.mode === 'live' ? '正在调用官方 API 采样，约 1–2 分钟…' : '正在生成行业化 GEO 方案…');
  try {
    const r = await api(type, payload);
    if (!r.ok) throw new Error(r.error || r.message || '诊断失败，请稍后重试');
    const s = await api({ action:'report_share', type, id:r.id });
    try { sessionStorage.removeItem(PENDING_KEY); } catch (e) {}
    pendingDiagnosis = null;
    if (s.ok && s.token) {
      location.href = '/diagnose/report.php?type=' + encodeURIComponent(type)
        + '&id=' + encodeURIComponent(r.id) + '&token=' + encodeURIComponent(s.token);
    } else {
      toast('报告已生成，正在进入工作台…');
      setTimeout(() => location.href = '/diagnose/', 900);
    }
  } catch (err) {
    showErr($('#startErr'), err.message || '网络异常，请稍后重试');
    if (form) form.style.display = '';
    if (prog) prog.style.display = 'none';
  } finally {
    submitting = false;
  }
}

const startFormEl = $('#startForm');
if (startFormEl) startFormEl.onsubmit = async e => {
  e.preventDefault();
  const parsed = readStartForm();
  if (parsed.err) { showErr($('#startErr'), parsed.err); return; }
  showErr($('#startErr'), '');
  pendingDiagnosis = parsed;
  try { sessionStorage.setItem(PENDING_KEY, JSON.stringify(parsed)); } catch (e) {}
  let me = null;
  try { me = await api('me'); } catch (e) {}
  if (!me || !me.user) {
    setRegisterCopy(true);
    openModal('registerModal');
    return;
  }
  await submitDiagnosis();
};

const params = new URLSearchParams(location.search);
if (params.get('register') === '1') { setRegisterCopy(!!pendingDiagnosis); openModal('registerModal'); }
else if (params.get('login') === '1') openModal('loginModal');

// 有邀请码时只展示提示条：不再自动弹注册，避免打断「先填信息再注册」流程
if (pickRef()) {
  const banner = $('#inviteBanner');
  if (banner) banner.style.display = 'block';
}

loadConfig();
checkSession();

/* ============================================================
   2026 交互层：主题、移动导航、滚动揭示、数字动效、进度条
   ============================================================ */
(function () {
  const root = document.documentElement;

  /* --- 主题切换（与工作台、报告页共用 localStorage key） --- */
  const themeBtn = $('#themeToggle');
  function syncThemeMeta(t) {
    $$('meta[name="theme-color"]').forEach(m => {
      const media = m.getAttribute('media') || '';
      const isDark = media.indexOf('dark') >= 0;
      if ((isDark && t === 'dark') || (!isDark && t === 'light')) {
        m.setAttribute('content', t === 'dark' ? '#070A12' : '#F6F8FD');
      }
    });
  }
  syncThemeMeta(root.getAttribute('data-theme') || 'light');
  if (themeBtn) {
    themeBtn.onclick = () => {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('geo-theme', next); } catch (e) {}
      syncThemeMeta(next);
      window.dispatchEvent(new CustomEvent('geo-theme-change', { detail: next }));
    };
  }

  /* --- 移动端导航 --- */
  const nav = $('#mainNav'), menuBtn = $('#menuToggle');
  function closeMenu() {
    if (!nav || !menuBtn) return;
    nav.classList.remove('open');
    menuBtn.classList.remove('open');
    menuBtn.setAttribute('aria-expanded', 'false');
  }
  if (menuBtn && nav) {
    menuBtn.onclick = () => {
      const open = !nav.classList.contains('open');
      nav.classList.toggle('open', open);
      menuBtn.classList.toggle('open', open);
      menuBtn.setAttribute('aria-expanded', String(open));
    };
    $$('#mainNav a').forEach(a => a.onclick = closeMenu);
    window.addEventListener('resize', () => { if (window.innerWidth > 980) closeMenu(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
  }

  /* --- 滚动揭示（尊重 prefers-reduced-motion） --- */
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) {
    root.classList.remove('anim-ready');
  } else if ('IntersectionObserver' in window) {
    root.classList.add('anim-ready');
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    $$('.reveal').forEach(el => io.observe(el));
  } else {
    root.classList.remove('anim-ready');
  }

  /* --- 数字滚动（首屏指标） --- */
  if (!reduce && 'IntersectionObserver' in window) {
    const cio = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        cio.unobserve(en.target);
        const el = en.target;
        const target = parseFloat(el.dataset.count || '0');
        const suffix = el.dataset.suffix || '';
        const dur = 1100, t0 = performance.now();
        function step(now) {
          const p = Math.min(1, (now - t0) / dur);
          const eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * eased) + suffix;
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = target + suffix;
        }
        requestAnimationFrame(step);
      });
    }, { threshold: 0.5 });
    $$('[data-count]').forEach(el => cio.observe(el));
  }

  /* --- 顶部滚动进度 + header 阴影 --- */
  const bar = $('#scrollProgress'), header = $('.site-header');
  let ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const h = document.documentElement.scrollHeight - window.innerHeight;
      const p = h > 0 ? (window.scrollY / h) * 100 : 0;
      if (bar) bar.style.width = Math.min(100, Math.max(0, p)) + '%';
      if (header) header.classList.toggle('scrolled', window.scrollY > 12);
      ticking = false;
    });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
