const API = 'api/index.php';
const $ = (s, r=document) => r.querySelector(s);
const $$ = (s, r=document) => [...r.querySelectorAll(s)];

const pageTitles = {
  dashboard: ['概览','官网 GEO 体检与品牌 AI 可见度一站式分析'],
  'site-audit': ['官网 GEO 诊断','四层评分 + 50+ 项深度细查，每项附证据与修复建议'],
  visibility: ['品牌可见度分析','问题词 × 平台采样，洞察品牌在 AI 回答中的表现'],
  history: ['历史报告','查看所有诊断记录与报告'],
  trend: ['趋势分析','按时间对比官网得分与品牌可见度变化'],
  branding: ['报告品牌设置','为当前客户配置独立品牌的诊断报告'],
  invite: ['邀请有礼','邀请好友注册，双方获得奖励额度'],
  profile: ['个人中心','管理账户资料、密码与额度流水'],
  redeem: ['兑换额度','使用兑换码增加诊断次数'],
  clients: ['客户管理','创建客户、分配配额、查看客户报告'],
  settings: ['平台配置','注册、邀请、采样、安全与模型 Key'],
  logs: ['操作日志','查看登录与关键操作记录'],
};

let currentHistType = 'audit';
let platformData = [];
let currentUser = null;

function toast(msg){
  const t = $('#toast'); t.textContent = msg; t.classList.add('show');
  clearTimeout(t._t); t._t = setTimeout(()=>t.classList.remove('show'), 2600);
}

function goto(page){
  $$('.nav-item').forEach(n=>n.classList.toggle('active', n.dataset.page===page));
  $$('.page').forEach(p=>p.classList.remove('active'));
  $('#page-'+page)?.classList.add('active');
  const [t,s] = pageTitles[page]||['',''];
  $('#pageTitle').textContent=t; $('#pageSub').textContent=s;
  if(page==='dashboard') loadDashboard();
  if(page==='history') loadHistory(currentHistType);
  if(page==='trend') loadTrend();
  if(page==='branding') loadBranding();
  if(page==='invite') loadInvite();
  if(page==='profile') loadProfile();
  if(page==='redeem') loadRedeem();
  if(page==='settings'){ loadSettings(); loadKeys(); }
  if(page==='clients') loadClients();
  if(page==='logs') loadLogs();
}

$$('.nav-item').forEach(n=>n.onclick=()=>goto(n.dataset.page));
$$('[data-goto]').forEach(b=>b.onclick=()=>goto(b.dataset.goto));
$$('.quick-sites a').forEach(a=>a.onclick=e=>{e.preventDefault();$('#auditUrl').value=a.dataset.url;});

async function api(params, body){
  const opt = body ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)} : {};
  const r = await fetch(API+'?'+new URLSearchParams(params), opt);
  if(r.status === 401){ showLogin(); throw new Error('登录已过期'); }
  return r.json();
}

async function loadDashboard(){
  try{
    const st = await api({action:'stats'});
    $('#st-audits').textContent = st.audits??'—';
    $('#st-vis').textContent = st.visibility??'—';
    $('#st-avgsite').textContent = st.avg_audit_score??'—';
    $('#st-avgvis').textContent = st.avg_vis_score??'—';
    const list = await api({action:'list',type:'audit'});
    const recent = await api({action:'list',type:'visibility'});
    const all = [
      ...list.items.map(x=>({...x,_t:'audit'})),
      ...recent.items.map(x=>({...x,_t:'visibility'})),
    ].sort((a,b)=>b.created_at.localeCompare(a.created_at)).slice(0,5);
    $('#recentList').innerHTML = all.length ? all.map(x=>`
      <div class="recent-row" onclick="openReport('${x._t}','${x.id}')">
        <div class="hist-main"><div class="hm-title">${x._t==='audit'?'官网诊断':x.brand+' · 可见度'} — ${x._t==='audit'?esc(x.domain):esc(x.industry)}</div>
        <div class="hm-sub">${x.created_at}</div></div>
        <div class="hist-score" style="color:${scoreColor(x.score)}">${x.score}</div>
      </div>`).join('') : '<div class="empty">还没有诊断记录，从上方开始一次诊断吧</div>';
  }catch(e){ console.error(e); }
}

function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function scoreColor(s){s=Number(s);return s>=85?'var(--green)':s>=70?'var(--primary)':s>=55?'var(--amber)':s>=40?'#E2703A':'var(--red)';}
function gradeOf(s){s=Number(s);return s>=85?'优秀':s>=70?'良好':s>=55?'及格':s>=40?'偏弱':'待优化';}

// ===== 官网诊断 =====
$('#btnAudit').onclick = async ()=>{
  const url = $('#auditUrl').value.trim();
  if(!url){toast('请输入官网地址');return;}
  $('#auditResult').innerHTML=''; $('#auditProgress').style.display='flex';
  $('#btnAudit').disabled=true;
  try{
    const r = await api({action:'audit'}, {url});
    if(r.error){toast(r.error);refreshQuota();return;}
    renderAudit(r.result, r.id);
    refreshQuota();
    toast('诊断完成');
  }catch(e){toast('请求失败：'+e.message);}
  finally{ $('#auditProgress').style.display='none'; $('#btnAudit').disabled=false; }
};

function renderAudit(r, id, clientId){
  if(!r.layers){$('#auditResult').innerHTML='<div class="empty">诊断失败</div>';return;}
  const cidArg = clientId ? ",'"+clientId+"'" : '';
  const ga = r.grade||{label:gradeOf(r.score),color:scoreColor(r.score)};
  const layerNames = {access:'可访问性',orientation:'导航指引',understanding:'可理解性',quotability:'可引用性'};
  const layerDesc = {access:'AI 能否抓取到内容',orientation:'有无指引文件与规范',understanding:'内容能否被结构化理解',quotability:'内容能否被直接引用'};
  let layersHtml = ['access','orientation','understanding','quotability'].map((k,i)=>{
    const L = r.layers[k];
    return `<div class="layer">
      <div class="layer-head"><div class="layer-name"><span class="layer-step">${i+1}</span>${layerNames[k]} <span style="font-size:11px;color:var(--faint);font-weight:400">${layerDesc[k]}</span></div>
      <div class="layer-score" style="color:${scoreColor(L.score)}">${L.score} / 100</div></div>
      <ul class="check-list">${L.checks.map(c=>`<li><span class="check-dot chk-${c.s==='ok'?'ok':c.s==='warn'?'warn':'bad'}">${c.s==='ok'?'✓':c.s==='warn'?'!':'×'}</span><span class="chk-txt"><strong>${esc(c.t)}</strong>${c.d?`<span>${esc(c.d)}</span>`:''}</span></li>`).join('')}</ul>
    </div>`;
  }).join('');

  const issuesHtml = r.issues.length ? `<div class="sub-section"><h4>待修复问题（${r.issues.length}）</h4></div><ul class="issue-list">${r.issues.map(i=>`<li class="issue ${i.level}"><div class="it">[${esc(i.layer)}] ${esc(i.text)}</div><div class="if">建议：${esc(i.fix)}</div></li>`).join('')}</ul>` : '<div class="sub-section"><h4 style="color:var(--green)">✓ 未发现明显问题</h4></div>';

  let quoteHtml='';
  if(r.quotable_paragraphs?.length){
    quoteHtml=`<div class="sub-section"><h4>可独立引用段落样本</h4>${r.quotable_paragraphs.map(p=>`<div class="soft-block">${esc(p.text)}…</div>`).join('')}</div>`;
  }

  const D = r.deep || {};
  const DStats = D.stats || {};
  const DGroups = D.groups || [];
  const DPages = (D.coverage && D.coverage.pages) || [];
  let innerHtml='';
  if(DPages.length){
    const CS = D.coverage.summary || {};
    innerHtml=`<div class="sub-section"><h4>全站覆盖矩阵（抽样 ${DPages.length} 页 / sitemap ${D.coverage.sitemap_urls||0} 条）</h4><table class="data-table"><thead><tr><th>内页</th><th>状态</th><th>title</th><th>desc</th><th>H1</th><th>canonical</th><th>JSON-LD</th><th>时间</th><th>字数</th><th>可引用段</th></tr></thead><tbody>${DPages.map(p=>`<tr><td class="dt-url">${esc(p.url)}</td><td>${p.status}</td><td>${esc((p.title||'').slice(0,20))||'—'}</td><td>${p.desc?'有':'缺'}</td><td>${p.h1}</td><td>${esc(p.canonical)}</td><td>${esc((p.jsonld||'').slice(0,18))}</td><td>${esc(p.datetime)}</td><td>${p.words}</td><td>${p.quotable}</td></tr>`).join('')}</tbody></table>${Object.keys(CS).length?`<div style="margin-top:10px">${Object.entries(CS).map(([k,v])=>`<div style="font-size:11px;color:var(--sub);margin:3px 0"><b>${esc(k)}</b> ${v.pct}% (${v.ok}/${v.total}) <span style="display:inline-block;width:66px;height:5px;background:var(--line);vertical-align:middle"><i style="display:block;height:100%;width:${v.pct}%;background:var(--brand)"></i></span></div>`).join('')}</div>`:''}</div>`;
  } else if(r.inner_pages?.length){
    innerHtml=`<div class="sub-section"><h4>内页抽样（sitemap）</h4><table class="data-table"><thead><tr><th>内页</th><th>状态</th><th>字数</th><th>JSON-LD</th><th>canonical</th><th>H1</th></tr></thead><tbody>${r.inner_pages.map(p=>`<tr><td class="dt-url">${esc(p.url)}</td><td>${p.status}</td><td>${p.words}</td><td>${p.has_jsonld?'✓':'<span style="color:var(--red)">缺</span>'}</td><td>${p.has_canonical?'✓':'缺'}</td><td>${p.has_h1?'✓':'缺'}</td></tr>`).join('')}</tbody></table></div>`;
  }

  let deepHtml='';
  if(DGroups.length){
    deepHtml=`<div class="sub-section"><h4>深度诊断明细（${DStats.total||0} 项 · 细项通过率 ${DStats.pass_rate||0}%）</h4><p style="font-size:11px;color:var(--faint);margin:4px 0 10px;line-height:1.7">共 ${DStats.groups||0} 组，通过 ${DStats.ok||0} 项 / 待优化 ${DStats.warn||0} 项 / 阻断 ${DStats.bad||0} 项；不计入 GEO 得分，用于定位具体问题并提供复核依据。</p>${DGroups.map(g=>`<details class="deep-fold" style="margin:8px 0;border:1px solid var(--line);border-radius:6px;padding:9px 12px"><summary style="cursor:pointer;font-weight:700;font-size:12.5px">${esc(g.name)} <span style="color:var(--faint);font-weight:400;font-size:11px">— ${esc(g.desc)}</span></summary><table class="data-table" style="margin-top:9px"><thead><tr><th>判定</th><th>检查项</th><th>结论 / 证据 / 建议</th></tr></thead><tbody>${(g.items||[]).map(it=>`<tr><td><span style="font-size:11px;font-weight:700;color:${it.s==='ok'?'var(--green)':it.s==='warn'?'#D97706':'var(--red)'}">${it.s==='ok'?'通过':it.s==='warn'?'待优化':'问题'}</span></td><td><b>${esc(it.t)}</b></td><td>${esc(it.d)}${it.e?`<div style="font-size:10.5px;color:var(--faint);margin-top:3px;word-break:break-all">证据：${esc(it.e)}</div>`:''}${it.fix&&it.s!=='ok'?`<div style="font-size:10.5px;color:var(--sub);margin-top:3px">建议：${esc(it.fix)}</div>`:''}</td></tr>`).join('')}</tbody></table></details>`).join('')}</div>`;
  }

  $('#auditResult').innerHTML = `<div class="panel" style="margin-top:0">
    <div class="result-head">
      <div class="score-ring" style="border-color:${ga.color};color:${ga.color}"><div class="num">${r.score}</div><div class="lbl">GEO 得分</div></div>
      <div class="result-title"><h2>${esc(r.domain)}</h2><p>检测于 ${r.checked_at} · HTTP ${r.meta.http_status} · 响应 ${r.meta.response_ms}ms</p><span class="grade-pill" style="background:${ga.color}">${ga.label}</span></div>
    </div>
    ${r.chain_warning?`<div class="chain-warning">⚠ ${esc(r.chain_warning)}</div>`:''}
    <div class="metrics-grid">
      <div class="metric"><div class="mv" style="color:${scoreColor(r.layers.access.score)}">${r.layers.access.score}</div><div class="ml">可访问性</div></div>
      <div class="metric"><div class="mv" style="color:${scoreColor(r.layers.orientation.score)}">${r.layers.orientation.score}</div><div class="ml">导航指引</div></div>
      <div class="metric"><div class="mv" style="color:${scoreColor(r.layers.understanding.score)}">${r.layers.understanding.score}</div><div class="ml">可理解性</div></div>
      <div class="metric"><div class="mv" style="color:${scoreColor(r.layers.quotability.score)}">${r.layers.quotability.score}</div><div class="ml">可引用性</div></div>
      <div class="metric"><div class="mv">${r.meta.word_count||r.layers.understanding.word_count||'—'}</div><div class="ml">正文字数</div></div>
    </div>
    ${layersHtml}
    ${issuesHtml}
    ${quoteHtml}
    ${innerHtml}
    ${deepHtml}
    <div class="notice">诊断方法：纯抓取分析（四层加权评分 + 多 AI 爬虫 UA 实测、llms.txt / sitemap 质量校验、结构化数据逐类型核对、实体一致性比对、段落级可引用性统计、响应头与内链检查、sitemap 内页微审计）。深度细项单独统计、不计入总分，所有结论仅供优化参考。</div>
    <div class="result-actions"><button class="btn btn-ghost" onclick="window.print()">打印 / 导出 PDF</button><button class="btn btn-ghost" onclick="shareReport('audit','${id}'${cidArg})">复制品牌报告链接</button><button class="btn btn-ghost" onclick="rotateReport('audit','${id}'${cidArg})">刷新报告链接</button><button class="btn btn-ghost" onclick="renewReport('audit','${id}'${cidArg})">续期链接</button><button class="btn btn-primary" onclick="openBrandReport('audit','${id}'${cidArg})">打开品牌报告</button></div>
  </div>`;
}

// ===== 品牌可见度 =====
async function loadPlatforms(){
  try{
    const r = await api({action:'platforms'});
    platformData = r.platforms;
    $('#platformChecks').innerHTML = r.platforms.map(p=>`<label class="${['deepseek','doubao','qwen'].includes(p.id)?'checked':''}"><input type="checkbox" value="${p.id}" ${['deepseek','doubao','qwen'].includes(p.id)?'checked':''}>${p.name}${p.configured?'':' <span style="font-size:10px;color:var(--faint)">(无Key)</span>'}</label>`).join('');
    $$('#platformChecks input').forEach(cb=>cb.onchange=()=>cb.closest('label').classList.toggle('checked',cb.checked));
  }catch(e){}
}

$('#btnGenQ').onclick = async ()=>{
  const ind = $('#visIndustry').value.trim();
  if(!ind){toast('请先填写行业词');return;}
  const r = await api({action:'questions',industry:ind});
  $('#qPreview').style.display='block';
  $('#qPreview').innerHTML = `<div class="qt">自动生成的问题词（${r.questions.length} 个）：</div><ol>${r.questions.map(q=>`<li>${esc(q)}</li>`).join('')}</ol>`;
};

$('#btnVis').onclick = async ()=>{
  const brand = $('#visBrand').value.trim();
  const industry = $('#visIndustry').value.trim();
  const question = $('#visQuestion').value.trim();
  const platforms = $$('#platformChecks input:checked').map(c=>c.value);
  const mode = $('input[name=mode]:checked').value;
  if(!brand||!industry){toast('请填写品牌名和行业词');return;}
  if(!platforms.length){toast('请至少选择一个平台');return;}
  $('#visResult').innerHTML=''; $('#visProgress').style.display='flex'; $('#btnVis').disabled=true;
  $('#visProgressText').textContent = mode==='live'?'正在调用官方 API 采样（可能需要 1-2 分钟）…':'正在生成分析报告…';
  try{
    const r = await api({action:'visibility'}, {brand,industry,question,platforms,mode});
    if(r.error){toast(r.error);refreshQuota();return;}
    renderVisibility(r.result, r.id);
    refreshQuota();
    if(mode==='live' && r.result.mode==='demo') toast('未配置有效 Key，已自动使用演示模式');
    else toast('分析完成');
  }catch(e){toast('请求失败：'+e.message);}
  finally{ $('#visProgress').style.display='none'; $('#btnVis').disabled=false; }
};

function renderVisibility(r, id, clientId){
  const cidArg = clientId ? ",'"+clientId+"'" : '';
  const isDemo = r.mode==='demo';
  const scoreColorV = scoreColor(r.score);
  const m = r.metrics;
  const platHtml = r.platforms.map(p=>{
    const rate = p.mention_rate;
    const label = rate===null?'—':rate+'%';
    const col = rate===null?'var(--faint)':scoreColor(rate);
    const note = (p.error_note||p.error) ? `<span class="plat-note" title="${esc(p.error_note||p.error)}">⚠ 部分样本失败</span>` : '';
    return `<div class="bar-row"><div class="bl">${esc(p.name)}${note}</div><div class="bar-track"><div class="bar-fill" style="width:${rate||0}%;background:${col}"></div></div><div class="bv" style="color:${col}">${label}</div></div>`;
  }).join('');
  const compHtml = (r.competitors||[]).map(c=>`<div class="bar-row"><div class="bl">${esc(c.name)}</div><div class="bar-track"><div class="bar-fill" style="width:${c.mentions/Math.max(...r.competitors.map(x=>x.mentions))*100}%;background:var(--primary-2)"></div></div><div class="bv">${c.mentions}</div></div>`).join('');
  const srcHtml = (r.sources||[]).map(s=>`<span class="src-tag">${esc(s.domain)} <b style="color:var(--primary)">${s.count}</b></span>`).join('');
  const findHtml = (r.findings||[]).map(f=>`<li class="issue ${f.level}"><div class="it">${esc(f.text)}</div><div class="if">建议：${esc(f.fix)}</div></li>`).join('');

  $('#visResult').innerHTML = `<div class="panel" style="margin-top:0">
    <div class="result-head">
      <div class="score-ring" style="border-color:${scoreColorV};color:${scoreColorV}"><div class="num">${r.score}<span style="font-size:14px">%</span></div><div class="lbl">品牌提及率</div></div>
      <div class="result-title"><h2>${esc(r.brand)}</h2><p>行业：${esc(r.industry)} · 问题：${esc(r.question)}</p><span class="grade-pill" style="background:${isDemo?'var(--faint)':scoreColorV}">${isDemo?'演示模式':'真实 API'}</span></div>
    </div>
    <div class="metrics-grid">
      <div class="metric"><div class="mv">${m.mention_rate}%</div><div class="ml">提及率</div></div>
      <div class="metric"><div class="mv">${m.top1_rate===null?'—':m.top1_rate+'%'}</div><div class="ml">Top1 首位率</div></div>
      <div class="metric"><div class="mv">${m.top3_rate===null?'—':m.top3_rate+'%'}</div><div class="ml">Top3 前三率</div></div>
      <div class="metric"><div class="mv">${m.valid_samples}/${m.total_samples}</div><div class="ml">有效样本</div></div>
    </div>
    <div class="sub-section"><h4>分平台提及率</h4>${platHtml}</div>
    ${compHtml?`<div class="sub-section"><h4>竞品被提及频次</h4>${compHtml}</div>`:''}
    ${srcHtml?`<div class="sub-section"><h4>高频引用信源</h4>${srcHtml}</div>`:''}
    ${r.alias_note?`<div class="info-banner">💡 ${esc(r.alias_note)}</div>`:''}
    ${findHtml?`<div class="sub-section"><h4>发现与建议</h4></div><ul class="issue-list">${findHtml}</ul>`:''}
    ${r.raw_samples?.length?`<div class="sub-section"><h4>原始回答样本${m.questions?`（${m.questions} 个问题 × 多平台，共 ${m.total_samples} 个样本）`:''}</h4>${r.raw_samples.map(s=>`<div class="soft-block"><b>${esc(s.platform)}</b> ${s.hit.hit?'<span style="color:var(--green)">· 命中</span>':'· 未命中'}${s.rank?` · <b>推荐第 ${s.rank} 位</b>`:''}${s.question?` <span style="color:var(--faint)">· ${esc(s.question)}</span>`:''}<br>${esc(s.text.slice(0,300))}…</div>`).join('')}</div>`:''}
    <div class="notice">${esc(r.notice||'')}</div>
    <div class="result-actions"><button class="btn btn-ghost" onclick="window.print()">打印 / 导出 PDF</button><button class="btn btn-ghost" onclick="shareReport('visibility','${id}'${cidArg})">复制品牌报告链接</button><button class="btn btn-ghost" onclick="rotateReport('visibility','${id}'${cidArg})">刷新报告链接</button><button class="btn btn-ghost" onclick="renewReport('visibility','${id}'${cidArg})">续期链接</button><button class="btn btn-primary" onclick="openBrandReport('visibility','${id}'${cidArg})">打开品牌报告</button></div>
  </div>`;
}

async function getReportUrl(type,id,clientId,rotate){
  const params={action:'report_share',type,id};
  if(clientId) params.client_id=clientId;
  // rotate 属状态变更，必须用 POST（服务端已禁止 GET 轮换）
  const r = await api(params, rotate ? {rotate:1} : undefined);
  if(!r.ok || !r.url) throw new Error(r.error||'生成报告链接失败');
  return r.url;
}
async function shareReport(type,id,clientId){
  try{
    const params={action:'report_share',type,id};
    if(clientId) params.client_id=clientId;
    const r = await api(params);
    if(!r.ok || !r.url) throw new Error(r.error||'生成报告链接失败');
    if(navigator.clipboard?.writeText){
      await navigator.clipboard.writeText(r.url);
      toast(r.expires ? `链接已复制，有效期至 ${r.expires}` : '链接已复制（永久有效）');
    }else{
      prompt('复制品牌报告链接：', r.url);
    }
  }catch(e){ toast(e.message); }
}
async function renewReport(type,id,clientId){
  try{
    const params={action:'report_share',type,id};
    if(clientId) params.client_id=clientId;
    const r = await api(params,{renew:1});
    if(!r.ok) throw new Error(r.error||'续期失败');
    toast(r.expires ? `链接有效期已续至 ${r.expires}` : '当前链接为永久有效，无需续期');
  }catch(e){ toast(e.message); }
}
async function rotateReport(type,id,clientId){
  if(!confirm('刷新后旧报告链接会立即失效，确定继续？')) return;
  try{
    const url = await getReportUrl(type,id,clientId,true);
    if(navigator.clipboard?.writeText){
      await navigator.clipboard.writeText(url);
      toast('报告链接已刷新并复制，旧链接已失效');
    }else{
      prompt('新报告链接（旧链接已失效）：', url);
      toast('报告链接已刷新');
    }
  }catch(e){ toast(e.message); }
}
async function openBrandReport(type,id,clientId){
  try{ window.open(await getReportUrl(type,id,clientId), '_blank'); }
  catch(e){ toast(e.message); }
}

// ===== 历史 =====
$$('.tab[data-hist]').forEach(t=>t.onclick=()=>{$$('.tab[data-hist]').forEach(x=>x.classList.remove('active'));t.classList.add('active');currentHistType=t.dataset.hist;loadHistory(currentHistType);});

async function loadHistory(type, clientId, clientName){
  try{
    const params = {action:'list',type};
    if(clientId){ params.all=1; } // 管理员查看客户报告走 all=1
    const r = await api(params);
    let items = r.items;
    if(clientId){ items = items.filter(x=>x.client_id===clientId); }
    $('#historyList').innerHTML = items.length ? items.map(x=>`
      <div class="hist-row" onclick="openReport('${type}','${x.id}'${clientId?",'"+clientId+"'":''})">
        <div class="hist-main"><div class="hm-title">${type==='audit'?esc(x.domain):esc(x.brand)+' · '+esc(x.industry)}</div><div class="hm-sub">${x.client_name?esc(x.client_name)+' · ':''}${x.created_at}${x.mode?' · '+x.mode:''}</div></div>
        <div class="hist-score" style="color:${scoreColor(x.score)}">${x.score}${type==='visibility'?'%':''}</div>
        <button class="hist-brand" title="打开独立品牌报告" onclick="event.stopPropagation();openBrandReport('${type}','${x.id}'${clientId ? ",'"+clientId+"'" : ''})">报告</button>
        <button class="hist-brand" title="刷新报告链接（旧链接会失效）" onclick="event.stopPropagation();rotateReport('${type}','${x.id}'${clientId ? ",'"+clientId+"'" : ''})">刷新</button>
        <button class="hist-del" onclick="event.stopPropagation();delRecord('${type}','${x.id}')">✕</button>
      </div>`).join('') : '<div class="empty">暂无记录</div>';
  }catch(e){}
}

async function delRecord(type,id){
  if(!confirm('确定删除这条记录？'))return;
  await api({action:'delete'},{type,id});
  toast('已删除'); loadHistory(type);
}

async function openReport(type,id,clientId){
  goto(type==='audit'?'site-audit':'visibility');
  try{
    const params={action:'get',type,id};
    if(clientId) params.client_id=clientId;
    const r = await api(params);
    if(type==='audit') renderAudit(r.result, r.id, clientId);
    else renderVisibility(r.result, r.id, clientId);
    setTimeout(()=>$('#'+ (type==='audit'?'auditResult':'visResult')).scrollIntoView({behavior:'smooth',block:'start'}),80);
  }catch(e){}
}

// ===== 配置 =====
async function loadSettings(){
  try{
    const r = await api({action:'settings_get'});
    const s = r.settings||{};
    $('#setPlatformName').value = s.platform_name ?? '';
    $('#setPlatformTagline').value = s.platform_tagline ?? '';
    $('#setFreeQuota').value = s.free_quota ?? 5;
    $('#setRegisterEnabled').value = s.register_enabled ? '1' : '0';
    $('#setInviteEnabled').value = s.invite_enabled ? '1' : '0';
    $('#setInviteReward').value = s.invite_reward ?? 2;
    $('#setInviteeBonus').value = s.invitee_bonus ?? 0;
    $('#setInviteMax').value = s.invite_max ?? 50;
    $('#setShareTtl').value = s.share_ttl_days ?? 30;
    $('#setLiveQuestions').value = s.live_question_limit ?? 5;
    $('#setLiveMaxSamples').value = s.live_max_samples ?? 30;
    $('#setLiveTimeout').value = s.live_timeout ?? 15;
    $('#setRlLogin').value = s.rl_login ?? 10;
    $('#setRlRegister').value = s.rl_register ?? 8;
    $('#setRlDiagnose').value = s.rl_diagnose ?? 60;
    $('#setSiteIcp').value = s.site_icp ?? '';
    if($('#setSiteUrl')) $('#setSiteUrl').value = s.site_url ?? '';
    $('#settingsErr').style.display='none';
  }catch(e){}
}
async function savePartialSettings(data, errEl){
  try{
    const r = await api({action:'settings_update'}, data);
    if(!r.ok){ $(errEl).textContent=r.error||'保存失败'; $(errEl).style.display='block'; return; }
    toast('设置已保存'); loadSettings();
  }catch(e){ toast('保存失败：'+e.message); }
}
function applyPlatformBrand(name, tagline){
  if(name){
    document.querySelectorAll('[data-brand="name"]').forEach(el=>el.textContent=name);
    document.title = name + ' · 官网体检与品牌可见度分析';
  }
  if(tagline) document.querySelectorAll('[data-brand="tagline"]').forEach(el=>el.textContent=tagline);
}
$('#btnSavePlatform').onclick = async ()=>{
  const name = $('#setPlatformName').value.trim();
  const tagline = $('#setPlatformTagline').value.trim();
  try{
    const r = await api({action:'settings_update'}, {platform_name:name, platform_tagline:tagline});
    if(!r.ok){ $('#platformErr').textContent=r.error||'保存失败'; $('#platformErr').style.display='block'; return; }
    applyPlatformBrand(r.settings?.platform_name || name, r.settings?.platform_tagline || tagline);
    $('#platformErr').style.display='none';
    toast('平台名称已保存'); loadSettings();
  }catch(e){ toast('保存失败：'+e.message); }
};
function showSeoSync(sync, okPrefix){
  const box=$('#siteUrlMsg'), err=$('#siteUrlErr');
  if(!sync || !sync.ok){
    err.textContent = (sync?.errors||[]).join('；') || '重建失败';
    err.style.display='block'; box.style.display='none'; return;
  }
  err.style.display='none';
  const files=sync.files||[];
  box.textContent = (okPrefix||'已重建') + ' ' + files.length + ' 个文件 · 站点 ' + (sync.site_url||'') + ' · 品牌 ' + (sync.brand||'');
  box.style.display='block';
  if($('#setSeoStatus')) $('#setSeoStatus').value = '最近重建：'+new Date().toLocaleString('zh-CN')+'（'+files.length+' 个文件）';
}
$('#btnSaveSiteUrl').onclick = async ()=>{
  const url = $('#setSiteUrl').value.trim();
  $('#siteUrlErr').style.display='none'; $('#siteUrlMsg').style.display='none';
  try{
    const r = await api({action:'settings_update'}, {site_url:url});
    if(!r.ok){ $('#siteUrlErr').textContent=r.error||'保存失败'; $('#siteUrlErr').style.display='block'; return; }
    showSeoSync(r.seo_sync, '站点地址已保存，');
    toast('站点地址已保存，SEO/GEO 文件已全量重建'); loadSettings();
  }catch(e){ $('#siteUrlErr').textContent='保存失败：'+e.message; $('#siteUrlErr').style.display='block'; }
};
$('#btnRebuildSeo').onclick = async ()=>{
  $('#siteUrlErr').style.display='none'; $('#siteUrlMsg').style.display='none';
  try{
    const r = await api({action:'seo_rebuild'}, {});
    showSeoSync(r.sync, '已按当前设置重建');
    toast(r.ok?'SEO/GEO 文件已重建':'重建失败，请查看提示');
  }catch(e){ $('#siteUrlErr').textContent='重建失败：'+e.message; $('#siteUrlErr').style.display='block'; }
};
$('#btnSaveSettings').onclick = ()=>{
  savePartialSettings({
    free_quota: Math.max(0, Math.min(1000, parseInt($('#setFreeQuota').value)||0)),
    register_enabled: $('#setRegisterEnabled').value === '1' ? 1 : 0,
  }, '#settingsErr');
};
$('#btnSaveInvite').onclick = ()=>{
  savePartialSettings({
    invite_enabled: $('#setInviteEnabled').value === '1' ? 1 : 0,
    invite_reward: Math.max(0, Math.min(100, parseInt($('#setInviteReward').value)||0)),
    invitee_bonus: Math.max(0, Math.min(100, parseInt($('#setInviteeBonus').value)||0)),
    invite_max: Math.max(0, Math.min(10000, parseInt($('#setInviteMax').value)||0)),
  }, '#inviteErr');
};
$('#btnSaveAdvanced').onclick = ()=>{
  savePartialSettings({
    share_ttl_days: Math.max(0, Math.min(3650, parseInt($('#setShareTtl').value)||0)),
    live_question_limit: Math.max(1, Math.min(15, parseInt($('#setLiveQuestions').value)||5)),
    live_max_samples: Math.max(1, Math.min(100, parseInt($('#setLiveMaxSamples').value)||30)),
    live_timeout: Math.max(5, Math.min(45, parseInt($('#setLiveTimeout').value)||15)),
  }, '#advancedErr');
};
$('#btnSaveSecurity').onclick = ()=>{
  savePartialSettings({
    rl_login: Math.max(1, Math.min(100, parseInt($('#setRlLogin').value)||10)),
    rl_register: Math.max(1, Math.min(100, parseInt($('#setRlRegister').value)||8)),
    rl_diagnose: Math.max(1, Math.min(1000, parseInt($('#setRlDiagnose').value)||60)),
    site_icp: $('#setSiteIcp').value.trim(),
  }, '#securityErr');
};

async function loadKeys(){
  try{
    const r = await api({action:'keys_get'});
    $('#keyEditList').innerHTML = (r.keys||[]).map(k=>`
      <div class="key-edit-row">
        <div class="ker-name"><strong>${esc(k.name)}</strong><small>${esc(k.env)}</small></div>
        <span class="key-status ${k.configured?'ks-on':'ks-off'}">${k.configured?'已配置':'未配置'}</span>
        <input type="password" class="inp" data-key="${k.id}" autocomplete="new-password" placeholder="${k.configured?'留空保持不变（'+esc(k.masked)+'）':'填写 API Key'}">
        ${k.configured?`<label class="ker-remove"><input type="checkbox" data-remove="${k.id}"> 清空</label>`:''}
      </div>`).join('');
    $('#keysErr').style.display='none';
  }catch(e){}
}
$('#btnSaveKeys').onclick = async ()=>{
  const keys={};
  $$('#keyEditList input[data-key]').forEach(i=>{ if(i.value.trim()) keys[i.dataset.key]=i.value.trim(); });
  const remove=$$('#keyEditList input[data-remove]:checked').map(i=>i.dataset.remove);
  if(!Object.keys(keys).length && !remove.length){ toast('请填写 Key 或勾选要清空的项'); return; }
  try{
    const r = await api({action:'keys_update'}, {keys,remove});
    if(r.error){ $('#keysErr').textContent=r.error; $('#keysErr').style.display='block'; return; }
    toast('API Key 已保存');
    $$('#keyEditList input[data-key]').forEach(i=>i.value='');
    loadKeys();
  }catch(e){ toast('保存失败：'+e.message); }
};

// ===== 邀请有礼 =====
async function loadInvite(){
  try{
    const r = await api({action:'invite_info'});
    const v = r.invite||{};
    $('#inviteUrl').value = v.url || '';
    $('#inviteCode').textContent = v.code || '————';
    $('#invCount').textContent = v.invited ?? '—';
    $('#invEarned').textContent = v.earned ?? '—';
    $('#invReward').textContent = v.reward ?? '—';
    $('#invMax').textContent = v.max ?? '—';
    $('#inviteClosed').style.display = v.enabled ? 'none' : 'block';
    $('#inviteRuleHint').textContent = `每成功邀请 1 位好友注册，即可获得 ${v.reward ?? 0} 次奖励额度`;
    $('#inviteeBonusRule').textContent = v.invitee_bonus > 0
      ? `通过邀请链接注册的好友，在免费额度之外还能额外获得 ${v.invitee_bonus} 次奖励`
      : '通过邀请链接注册的好友，同样享受平台免费注册额度';
  }catch(e){}
}
$('#btnCopyInvite').onclick = async ()=>{
  const url = $('#inviteUrl').value;
  if(!url){ toast('邀请链接尚未生成'); return; }
  try{
    if(navigator.clipboard?.writeText){ await navigator.clipboard.writeText(url); toast('邀请链接已复制，快发给好友吧'); }
    else prompt('复制邀请链接：', url);
  }catch(e){ prompt('复制邀请链接：', url); }
};
$('#btnCopyCode').onclick = async ()=>{
  const code = $('#inviteCode').textContent.trim();
  if(!code || code === '————'){ toast('邀请码尚未生成'); return; }
  try{
    if(navigator.clipboard?.writeText){ await navigator.clipboard.writeText(code); toast('邀请码已复制'); }
    else prompt('复制邀请码：', code);
  }catch(e){ prompt('复制邀请码：', code); }
};

// ===== 登录 / 会话 =====
function showLogin(){
  $('#appMain').style.display='none';
  $('#loginScreen').style.display='flex';
  $('#loginErr').style.display='none';
  setTimeout(()=>$('#loginUser')?.focus(),50);
}
function showApp(){
  $('#loginScreen').style.display='none';
  $('#appMain').style.display='flex';
}
function applyUser(u){
  currentUser = u;
  const isAdmin = u.role==='admin';
  $('#userName').textContent = u.name||u.username;
  $('#userRole').textContent = isAdmin?'管理员':(u.company||'客户');
  $('#userAvatar').textContent = (u.name||u.username).slice(0,1).toUpperCase();
  updateQuotaChip(u);
  $$('.admin-only').forEach(el=>el.style.display = isAdmin?'':'none');
  showApp();
  // 载入基础数据
  loadPlatforms();
  loadDashboard();
  const p = new URLSearchParams(location.search).get('report');
  if(p && p.includes(':')){const [t,id]=p.split(':');openReport(t,id);}
}
function updateQuotaChip(u){
  const el=$('#quotaChip'); if(!el) return;
  const q=Number(u.quota), used=Number(u.used);
  el.textContent = (q<0 || u.role==='admin') ? '额度不限' : `剩余额度 ${Math.max(0,q-used)} / ${q}`;
}
async function refreshQuota(){
  try{ const r=await api({action:'me'}); if(r.user){ currentUser=r.user; updateQuotaChip(r.user); } }catch(e){}
}

$('#loginForm').onsubmit = async (e)=>{
  e.preventDefault();
  const btn=$('#btnLogin'); btn.disabled=true; btn.textContent='登录中…';
  $('#loginErr').style.display='none';
  try{
    const r = await api({action:'login'},{username:$('#loginUser').value,password:$('#loginPass').value});
    if(r.ok){ applyUser(r.user); }
    else { $('#loginErr').textContent=r.message||'登录失败'; $('#loginErr').style.display='block'; }
  }catch(err){ $('#loginErr').textContent='连接失败，请重试'; $('#loginErr').style.display='block'; }
  finally{ btn.disabled=false; btn.textContent='登录'; }
};
$('#btnLogout').onclick = async ()=>{
  await api({action:'logout'});
  currentUser=null; showLogin();
};

// ===== 客户管理 =====
async function loadClients(){
  try{
    const r = await api({action:'clients'});
    $('#clientList').innerHTML = r.clients.length ? r.clients.map(c=>{
      const isAdmin=c.role==='admin';
      const unlimited=int(c.quota)<0;
      const pct=unlimited?0:Math.min(100,Math.round(c.used/c.quota*100));
      return `<div class="client-row ${c.enabled?'':'status-off'}">
        <div class="client-avatar ${isAdmin?'admin':''}">${esc((c.name||c.username).slice(0,1))}</div>
        <div class="client-main">
          <div class="cn">${esc(c.name)} <span class="role-tag ${isAdmin?'role-admin':'role-customer'}">${isAdmin?'管理员':'客户'}</span>${c.enabled?'':'<span class="role-tag role-customer">已禁用</span>'}</div>
          <div class="cm">账号 ${esc(c.username)}${c.company?' · '+esc(c.company):''} · 创建于 ${esc((c.created_at||'').slice(0,10))}</div>
        </div>
        <div class="client-quota">
          <div class="qnum">${unlimited?'不限':c.used+' / '+c.quota}</div>
          <div class="qlbl">已用配额</div>
          ${unlimited?'':`<div class="quota-bar"><i style="width:${pct}%;background:${pct>=90?'var(--red)':'var(--primary)'}"></i></div>`}
        </div>
        <div class="client-actions">
          <button class="icon-btn" title="查看报告" onclick="viewClientReports('${c.id}','${esc(c.name)}')">📋</button>
          <button class="icon-btn" title="重置配额" onclick="resetQuota('${c.id}')">↺</button>
          <button class="icon-btn" title="编辑" onclick="editClient('${c.id}')">✎</button>
          <button class="icon-btn danger" title="删除" onclick="delClient('${c.id}')">✕</button>
        </div>
      </div>`;
    }).join('') : '<div class="empty">还没有客户，点击右上角新建</div>';
  }catch(e){}
}
function int(v){return parseInt(v)||0;}

function openClientModal(c){
  $('#cfId').value = c?.id || '';
  $('#cfUsername').value = c?.username || '';
  $('#cfUsername').disabled = !!c;
  $('#cfName').value = c?.name || '';
  $('#cfPassword').value = '';
  $('#cfPassHint').textContent = c ? '（留空则不修改）' : '';
  $('#cfCompany').value = c?.company || '';
  $('#cfRole').value = c?.role || 'customer';
  $('#cfQuota').value = c?.quota ?? 5;
  $('#cfEnabled').value = String(c?.enabled ?? 1);
  const b = c?.brand || {};
  $('#cfReportBrand').value = b.report_brand||'';
  $('#cfReportColor').value = /^#[0-9a-fA-F]{6}$/.test(b.report_color||'') ? b.report_color : '#2563EB';
  $('#cfReportLogo').value = b.report_logo||'';
  $('#cfReportTagline').value = b.report_tagline||'';
  $('#cfReportContact').value = b.report_contact||'';
  $('#cfReportFooter').value = b.report_footer||'';
  $('#clientModalTitle').textContent = c ? '编辑客户' : '新建客户';
  $('#cfErr').style.display='none';
  $('#clientModal').style.display='flex';
}
$('#btnNewClient').onclick = ()=>openClientModal(null);
$('#clientModalClose').onclick = $('#cfCancel').onclick = ()=>$('#clientModal').style.display='none';
$('#clientModal').onclick = (e)=>{if(e.target.id==='clientModal')$('#clientModal').style.display='none';};

$('#cfSave').onclick = async ()=>{
  const id=$('#cfId').value;
  const data={
    name:$('#cfName').value, company:$('#cfCompany').value, role:$('#cfRole').value,
    quota:int($('#cfQuota').value), enabled:int($('#cfEnabled').value),
    report_brand:$('#cfReportBrand').value, report_color:$('#cfReportColor').value,
    report_logo:$('#cfReportLogo').value, report_tagline:$('#cfReportTagline').value,
    report_contact:$('#cfReportContact').value, report_footer:$('#cfReportFooter').value,
  };
  if(!id){ data.username=$('#cfUsername').value; data.password=$('#cfPassword').value; }
  else if($('#cfPassword').value){ data.password=$('#cfPassword').value; }
  if(!data.name){ $('#cfErr').textContent='请填写客户名称'; $('#cfErr').style.display='block'; return; }
  try{
    const r = await api({action: id?'client_update':'client_create'}, id?{...data,id}:data);
    if(r.error){ $('#cfErr').textContent=r.error||r.message||'保存失败'; $('#cfErr').style.display='block'; return; }
    $('#clientModal').style.display='none';
    toast(id?'已保存':'客户已创建'); loadClients();
  }catch(e){}
};

async function editClient(id){
  const r = await api({action:'clients'});
  const c = r.clients.find(x=>x.id===id);
  if(c) openClientModal(c);
}
async function delClient(id){
  if(!confirm('确定删除该客户？其报告将保留但失去归属。'))return;
  const r = await api({action:'client_delete'},{id});
  if(r.error){toast(r.message||'删除失败');return;}
  toast('已删除'); loadClients();
}
async function resetQuota(id){
  if(!confirm('确定重置该客户的配额使用量？'))return;
  await api({action:'client_reset_usage'},{id});
  toast('配额已重置'); loadClients();
}
function viewClientReports(id,name){
  goto('history');
  $('#pageTitle').textContent = name+' 的报告';
  $('#pageSub').textContent = '管理员查看客户诊断记录';
  loadHistory(currentHistType, id, name);
}

// ===== 独立品牌报告设置 =====
let brandClientId = null;

async function loadBrandingClients(){
  try{
    const r = await api({action:'clients'});
    const sel = $('#brandClientSelect');
    sel.innerHTML = r.clients.map(c=>`<option value="${c.id}">${esc(c.name)}（${esc(c.username)}）</option>`).join('');
    const target = brandClientId || currentUser.id;
    sel.value = target;
    brandClientId = sel.value;
    sel.onchange = ()=>loadBranding(sel.value);
  }catch(e){}
}

function syncBrandPreview(){
  const b = {
    report_brand: $('#bfBrand').value || '品牌名称',
    report_color: $('#bfColor').value || '#2563EB',
    report_tagline: $('#bfTagline').value || '报告标语',
    report_contact: $('#bfContact').value || '联系方式将显示在这里',
  };
  $('#brandPreview').style.setProperty('--brand', b.report_color);
  $('#bpLogoMark').textContent = b.report_brand.slice(0,2);
  $('#bpName').textContent = b.report_brand;
  $('#bpTagline').textContent = b.report_tagline;
  $('#bpContact').textContent = b.report_contact;
}
['bfBrand','bfColor','bfTagline','bfContact'].forEach(id=>{
  const el = $('#'+id); if(el) el.addEventListener('input', syncBrandPreview);
});

async function loadBranding(clientId){
  try{
    if(currentUser?.role === 'admin'){
      if(!$('#brandClientSelect').options.length) await loadBrandingClients();
      if(clientId){ brandClientId = clientId; $('#brandClientSelect').value = clientId; }
    }
    const target = brandClientId || currentUser.id;
    const params = {action:'brand_get'};
    if(target) params.client_id = target;
    const r = await api(params);
    const b = r.brand||{};
    $('#bfBrand').value = b.report_brand||'';
    $('#bfColor').value = /^#[0-9a-fA-F]{6}$/.test(b.report_color||'') ? b.report_color : '#2563EB';
    $('#bfLogo').value = b.report_logo||'';
    $('#bfTagline').value = b.report_tagline||'';
    $('#bfContact').value = b.report_contact||'';
    $('#bfFooter').value = b.report_footer||'';
    $('#brandErr').style.display='none';
    syncBrandPreview();
  }catch(e){}
}

$('#btnResetBrand').onclick = ()=>loadBranding(brandClientId);
$('#btnSaveBrand').onclick = async ()=>{
  const data = {
    report_brand: $('#bfBrand').value,
    report_color: $('#bfColor').value,
    report_logo: $('#bfLogo').value,
    report_tagline: $('#bfTagline').value,
    report_contact: $('#bfContact').value,
    report_footer: $('#bfFooter').value,
  };
  if(currentUser?.role === 'admin' && $('#brandClientSelect').value) data.client_id = $('#brandClientSelect').value;
  try{
    const r = await api({action:'brand_update'}, data);
    if(!r.ok){ $('#brandErr').textContent=r.error||'保存失败'; $('#brandErr').style.display='block'; return; }
    toast('报告品牌设置已保存');
    syncBrandPreview();
  }catch(e){ toast('保存失败：'+e.message); }
};

// 初始化：先检查登录态
async function initApp(){
  try{
    const r = await api({action:'me'});
    if(r.user) applyUser(r.user);
    else showLogin();
  }catch(e){ showLogin(); }
}
// script 在 body 底部时 DOMContentLoaded 可能已触发，需兼容
if(document.readyState === 'loading'){
  window.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

// ===== 趋势分析 =====
function trendDelta(arr){
  if(!arr || arr.length<2) return null;
  return (Number(arr[arr.length-1].score)-Number(arr[0].score)).toFixed(1);
}
async function loadTrend(){
  try{
    const r=await api({action:'trend',limit:40});
    const A=r.trend?.audit||[], V=r.trend?.visibility||[];
    if(!A.length && !V.length){
      $('#trendChart').innerHTML='<div class="empty">暂无数据，完成一次诊断后即可看到趋势</div>';
      $('#trendSummary').innerHTML=''; $('#trendTable').innerHTML='';
      return;
    }
    const W=720,H=210,P=26;
    const y=v=>H-P-(Math.max(0,Math.min(100,Number(v)))/100)*(H-2*P);
    const grid=[0,25,50,75,100].map(v=>`<line x1="${P}" y1="${y(v)}" x2="${W-P}" y2="${y(v)}" stroke="#eef1f5"/><text x="2" y="${y(v)+4}" font-size="10" fill="#9aa0aa">${v}</text>`).join('');
    const line=(arr,color,series)=>{
      if(!arr.length) return '';
      const n=arr.length, step=n>1?(W-2*P)/(n-1):0;
      const pts=arr.map((x,i)=>`${(P+i*step).toFixed(1)},${y(x.score).toFixed(1)}`).join(' ');
      return `<polyline fill="none" stroke="${color}" stroke-width="2" stroke-linejoin="round" points="${pts}"/>`+
        arr.map((x,i)=>`<circle data-series="${series}" cx="${(P+i*step).toFixed(1)}" cy="${y(x.score).toFixed(1)}" r="3.5" fill="#fff" stroke="${color}" stroke-width="2"><title>${esc(x.label)} · ${x.score} · ${esc(x.created_at)}</title></circle>`).join('');
    };
    $('#trendChart').innerHTML=`<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;display:block">${grid}${line(A,'#2563eb','a')}${line(V,'#0f6e56','v')}</svg>
      <div class="trend-axis"><span>最早</span><span>时间顺序 →（鼠标悬停圆点查看明细）</span><span>最近</span></div>`;
    const dA=trendDelta(A), dV=trendDelta(V);
    const chip=(v,unit)=>v===null?'—':`<b style="color:${Number(v)>=0?'var(--green)':'var(--red)'}">${Number(v)>0?'+':''}${v}${unit}</b>`;
    $('#trendSummary').innerHTML=`<div class="trend-kpi"><div><small>官网诊断</small><strong>${A.length}</strong><span>累计变化 ${chip(dA,'')}</span></div>
      <div><small>可见度分析</small><strong>${V.length}</strong><span>累计变化 ${chip(dV,'%')}</span></div>
      <div><small>最新官网得分</small><strong>${A.length?A[A.length-1].score:'—'}</strong><span>${A.length?esc(A[A.length-1].created_at):''}</span></div>
      <div><small>最新提及率</small><strong>${V.length?V[V.length-1].score+'%':'—'}</strong><span>${V.length?esc(V[V.length-1].created_at):''}</span></div></div>`;
    const rows=[...A.map(x=>({...x,_t:'官网诊断'})),...V.map(x=>({...x,_t:'可见度分析'}))]
      .sort((a,b)=>b.created_at.localeCompare(a.created_at)).slice(0,10);
    $('#trendTable').innerHTML=`<table class="data-table"><thead><tr><th>类型</th><th>对象</th><th>得分</th><th>时间</th></tr></thead><tbody>${rows.map(x=>`<tr><td>${x._t}</td><td>${esc(x.label)}</td><td style="color:${scoreColor(x.score)};font-weight:700">${x.score}${x._t==='可见度分析'?'%':''}</td><td>${esc(x.created_at)}</td></tr>`).join('')}</tbody></table>`;
  }catch(e){ $('#trendChart').innerHTML='<div class="empty">趋势加载失败</div>'; }
}

// ===== 个人中心 =====
async function loadProfile(){
  try{
    const r=await api({action:'me'});
    const u=r.user||currentUser||{};
    $('#pfName').value=u.name||''; $('#pfCompany').value=u.company||''; $('#pfPhone').value=u.phone||'';
    $('#pfOldPwd').value=''; $('#pfNewPwd').value=''; $('#pfNewPwd2').value='';
    $('#profileErr').style.display='none'; $('#pwdErr').style.display='none';
    await loadCredits();
  }catch(e){}
}
async function loadCredits(){
  try{
    const r=await api({action:'credits'});
    const kinds={register:'注册赠送',invite:'邀请奖励',invite_bonus:'受邀奖励',redeem:'兑换码',diagnose:'诊断消耗'};
    $('#creditList').innerHTML=r.items?.length?r.items.map(x=>`<div class="credit-row">
      <span class="cr-kind">${esc(kinds[x.kind]||x.kind)}</span>
      <span class="cr-remark">${esc(x.remark||'')}</span>
      <span class="cr-amount ${Number(x.amount)>=0?'plus':'minus'}">${Number(x.amount)>0?'+':''}${x.amount}</span>
      <span class="cr-bal">余额 ${x.balance_after<0?'不限':x.balance_after}</span>
      <span class="cr-time">${esc(x.created_at)}</span></div>`).join(''):'<div class="empty">暂无额度流水</div>';
  }catch(e){}
}
$('#btnSaveProfile').onclick=async()=>{
  try{
    const r=await api({action:'profile_update'},{name:$('#pfName').value,company:$('#pfCompany').value,phone:$('#pfPhone').value});
    if(r.error){$('#profileErr').textContent=r.error;$('#profileErr').style.display='block';return;}
    currentUser=r.user; $('#userName').textContent=r.user.name; toast('资料已保存');
  }catch(e){toast('保存失败：'+e.message);}
};
$('#btnChangePwd').onclick=async()=>{
  const p1=$('#pfNewPwd').value,p2=$('#pfNewPwd2').value;
  if(p1!==p2){$('#pwdErr').textContent='两次输入的新密码不一致';$('#pwdErr').style.display='block';return;}
  try{
    const r=await api({action:'change_password'},{old_password:$('#pfOldPwd').value,new_password:p1});
    if(r.error){$('#pwdErr').textContent=r.error;$('#pwdErr').style.display='block';return;}
    $('#pwdErr').style.display='none'; $('#pfOldPwd').value='';$('#pfNewPwd').value='';$('#pfNewPwd2').value='';
    toast('密码已修改，请牢记新密码');
  }catch(e){toast('修改失败：'+e.message);}
};

// ===== 兑换额度 =====
async function loadRedeem(){
  try{
    if(currentUser?.role==='admin') await loadRedeemCodes();
  }catch(e){}
}
async function loadRedeemCodes(){
  try{
    const r=await api({action:'redeem_codes'});
    $('#redeemCodeList').innerHTML=r.items?.length?r.items.map(x=>`<div class="client-row">
      <div class="client-main"><div class="cn"><code>${esc(x.code)}</code> <span class="role-tag ${x.used_by?'role-customer':'role-admin'}">${x.used_by?'已使用':'未使用'}</span></div>
      <div class="cm">${x.amount} 次 · ${esc(x.batch||'—')} · ${esc(x.created_at)}${x.used_name?' · 使用人 '+esc(x.used_name):''}</div></div>
      <button class="icon-btn" title="复制兑换码" onclick="copyRedeemCode('${esc(x.code)}')">⧉</button>
    </div>`).join(''):'<div class="empty">还没有生成兑换码</div>';
  }catch(e){}
}
function copyRedeemCode(c){
  if(navigator.clipboard?.writeText) navigator.clipboard.writeText(c).then(()=>toast('兑换码已复制'));
  else prompt('复制兑换码：',c);
}
$('#btnRedeem').onclick=async()=>{
  const code=$('#redeemCode').value.trim();
  if(!code){toast('请输入兑换码');return;}
  try{
    const r=await api({action:'redeem'},{code});
    if(r.error){$('#redeemErr').textContent=r.error;$('#redeemErr').style.display='block';return;}
    $('#redeemErr').style.display='none'; $('#redeemCode').value='';
    toast(`兑换成功，增加 ${r.amount} 次额度`); refreshQuota();
  }catch(e){toast('兑换失败：'+e.message);}
};
$('#btnGenCodes').onclick=async()=>{
  try{
    const r=await api({action:'redeem_create'},{
      amount:int($('#rcAmount').value)||10, count:int($('#rcCount').value)||5, batch:$('#rcBatch').value
    });
    if(r.error){toast(r.error);return;}
    $('#newCodes').style.display='block';
    $('#newCodes').innerHTML=`<div class="cb-title">本次生成 ${r.codes.length} 个兑换码（请复制保存）：</div>`+r.codes.map(c=>`<code>${esc(c)}</code>`).join('');
    toast('兑换码已生成'); loadRedeemCodes();
  }catch(e){toast('生成失败：'+e.message);}
};

// ===== 操作日志 =====
async function loadLogs(){
  try{
    const r=await api({action:'logs',limit:200});
    $('#logList').innerHTML=r.items?.length?r.items.map(x=>`<div class="log-row">
      <span class="lg-event">${esc(x.event)}</span><span class="lg-detail">${esc(x.detail||'')}</span>
      <span class="lg-ip">${esc(x.ip||'')}</span><span class="lg-time">${esc(x.created_at)}</span></div>`).join(''):'<div class="empty">暂无日志</div>';
  }catch(e){}
}
$('#btnRefreshLogs').onclick=loadLogs;

/* ============================================================
   2026 交互层：主题切换、移动端侧栏抽屉
   ============================================================ */
(function () {
  const root = document.documentElement;

  function syncThemeMeta(t) {
    $$('meta[name="theme-color"]').forEach(m => {
      const media = m.getAttribute('media') || '';
      const isDark = media.indexOf('dark') >= 0;
      if ((isDark && t === 'dark') || (!isDark && t === 'light')) {
        m.setAttribute('content', t === 'dark' ? '#070A12' : '#F6F8FD');
      }
    });
  }
  function toggleTheme() {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('geo-theme', next); } catch (e) {}
    syncThemeMeta(next);
  }
  syncThemeMeta(root.getAttribute('data-theme') || 'light');
  ['#appThemeToggle', '#loginThemeToggle'].forEach(sel => {
    const b = $(sel); if (b) b.onclick = toggleTheme;
  });
  window.addEventListener('storage', e => {
    if (e.key === 'geo-theme' && (e.newValue === 'dark' || e.newValue === 'light')) {
      root.setAttribute('data-theme', e.newValue);
      syncThemeMeta(e.newValue);
    }
  });

  /* 移动端侧栏 */
  const menuBtn = $('#appMenuBtn'), scrim = $('#sidebarScrim'), closeBtn = $('#sidebarClose');
  function setSidebar(open) {
    document.body.classList.toggle('sidebar-open', open);
    if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open));
  }
  if (menuBtn) menuBtn.onclick = () => setSidebar(!document.body.classList.contains('sidebar-open'));
  if (scrim) scrim.onclick = () => setSidebar(false);
  if (closeBtn) closeBtn.onclick = () => setSidebar(false);
  $$('.nav-item').forEach(n => n.addEventListener('click', () => {
    if (window.innerWidth <= 980) setSidebar(false);
  }));
  window.addEventListener('resize', () => { if (window.innerWidth > 980) setSidebar(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setSidebar(false); });
})();
