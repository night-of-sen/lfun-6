
/**
 * 把付费解读内容从客户端剥离（一次性手术；--check 只比对不写盘）。
 * 剥离后 dist/app.js / dist/en/app.js 只保留免费层：
 *   六宫名称与简版、分类标签、子类标签、分类词表、农历与三宫计算、免费层 render。
 * 详细解读（起盘三步、日时组合、分类专断、心理情绪、解卦语、行动建议）全部改由服务端下发。
 */
const fs = require('node:fs');
const path = require('node:path');

const DIST = path.resolve(__dirname, '..', 'dist');
const CHECK = process.argv.includes('--check');
const BT = String.fromCharCode(96);
const DL = String.fromCharCode(36) + '{';
const NL = String.fromCharCode(92) + 'n';
const ZH_RENDER_TPL = ["function render(record){","const cal=calendar(new Date(record.iso)),p=calculate(cal.month,cal.day,cal.hour),palace=PALACES[p.t],fullQuestion=record.question+(record.details?' '+record.details:''),subtype=detectSubtype(fullQuestion,record.category);","lastRenderContext={record:record,p:p,brief:palace[2]};","$('empty').hidden=true;","$('casting').hidden=true;","$('result').hidden=false;","$('resultTag').textContent=subtype.label+' · '+palace[0];","let caution='';","if(/癌|疾病|病情|怀孕|药|手术|自杀|自伤|死亡|股票|基金|投资|彩票|赌博|起诉|判刑|官司/.test(fullQuestion))caution='<p class=\"caution\">这类问题涉及健康、安全、财务或法律，盘面不能判断实际结果；请以现实证据及相应专业帮助为依据。</p>';","const wheel=@@BT@@<div class=\"grand-compass result-size\" aria-label=\"六宫罗盘，当前落宫@@DL@@palace[0]}\"><div class=\"compass-aura\"></div><div class=\"compass-bezel\"></div><div class=\"time-orbit\">@@DL@@[...BRANCHES].map((x,i)=>@@BT@@<span style=\"--i:@@DL@@i}\">@@DL@@x}</span>@@BT@@).join('')}</div><div class=\"palace-orbit\">@@DL@@PALACES.map((x,i)=>@@BT@@<span class=\"@@DL@@i===p.t?'active':''}\" style=\"--i:@@DL@@i}\">@@DL@@x[0]}<small>@@DL@@['安定','迟缓','消息','口舌','助力','未定'][i]}</small></span>@@BT@@).join('')}</div><div class=\"compass-axis\"></div><div class=\"compass-needle\"></div><div class=\"compass-hub\"><b>@@DL@@palace[0]}</b><small>时宫落定</small></div></div>@@BT@@;","const trace=@@BT@@<details class=\"trace\"><summary>查看计算公式与使用约定</summary><code class=\"formula\">月宫 = (@@DL@@cal.month}−1) mod 6 = @@DL@@p.m}<br>日宫 = (@@DL@@p.m}+@@DL@@cal.day}−1) mod 6 = @@DL@@p.d}<br>时宫 = (@@DL@@p.d}+@@DL@@p.hourIndex+1}−1) mod 6 = @@DL@@p.t}</code><p>六宫索引依次为 0 大安、1 留连、2 速喜、3 赤口、4 小吉、5 空亡。每一步都把起点计为一。</p><p>以时宫为最终依据；00:00 换日@@DL@@cal.leap?'；闰月按原月份计算':''}。完整解读在解锁后由服务端下发，不推定他人真实想法，也不替代现实调查。</p></details>@@BT@@;","$('result').innerHTML=@@BT@@@@DL@@wheel}<div class=\"result-top\"><div class=\"palace\">@@DL@@palace[0]}</div><div><h2>@@DL@@palace[1]}</h2><p>@@DL@@escapeHtml(cal.solar)} · 北京时间<br>@@DL@@cal.label} · @@DL@@BRANCHES[p.hourIndex]}时@@DL@@record.manual?' · 指定时间':''}</p></div></div><p class=\"asked\">所问：@@DL@@escapeHtml(record.question)}@@DL@@record.details?@@BT@@<small>补充：@@DL@@escapeHtml(record.details)}</small>@@BT@@:''}</p><p class=\"overview\"><strong>总览：</strong>@@DL@@palace[2]} 这是对当前处境的观察角度，不是对未来结果的保证。</p>@@DL@@caution}@@DL@@trace}@@BT@@;","}"];
const EN_RENDER_TPL = ["function render(record){","const cal=lunar(new Date(record.iso)),p=calculate(cal),hour=PALACES[p.t],month=PALACES[p.m],day=PALACES[p.d],brief=hour[2];","lastReading={record:record,p:p,cal:cal,brief:brief,reading:{verdict:brief,focus:'',transition:'',closing:''}};","$('empty').hidden=true;","$('result').hidden=false;","$('resultTag').textContent=@@BT@@@@DL@@CATEGORY_NAMES[record.category]} · @@DL@@hour[0]}@@BT@@;","$('result').innerHTML=@@BT@@<div class=\"en-result-head\"><div class=\"en-palace\">@@DL@@hour[0]}</div><div><h2>@@DL@@hour[1]}</h2><p>@@DL@@cal.solar} CST · Lunar @@DL@@cal.leap?'leap ':''}month @@DL@@cal.month}, day @@DL@@cal.day}</p></div></div><p class=\"asked\">Asked: @@DL@@escapeHtml(record.question)}@@DL@@record.details?@@BT@@<small>Context: @@DL@@escapeHtml(record.details)}</small>@@BT@@:''}</p><section class=\"en-card\"><small>DIRECT ANSWER</small><h3>@@DL@@escapeHtml(brief)}</h3><p>@@DL@@escapeHtml(CATEGORY_NAMES[record.category])} · @@DL@@hour[0]} — @@DL@@hour[1]}</p></section><section class=\"en-card\"><small>HOW IT WAS CALCULATED</small><p><b>1 · Month:</b> Lunar month @@DL@@cal.month} counts from Da An and lands on @@DL@@month[0]} — @@DL@@month[2]}</p><p><b>2 · Day:</b> Lunar day @@DL@@cal.day} counts from @@DL@@month[0]} and lands on @@DL@@day[0]} — @@DL@@day[2]}</p><p><b>3 · Hour:</b> The current two-hour period counts from @@DL@@day[0]} and lands on @@DL@@hour[0]} — @@DL@@hour[2]}</p><p>The full interpretation is delivered by the server after unlock.</p></section><div class=\"en-share\"><button id=\"copyResult\">Copy reading</button><button id=\"shareResult\" class=\"secondary\">Share</button></div>@@BT@@;","const text=@@BT@@Wenshi · Xiao Liu Ren@@NL@@Question: @@DL@@record.question}@@NL@@Result: @@DL@@hour[0]} — @@DL@@brief}@@NL@@@@NL@@Traditional cultural experience only.@@BT@@;","$('copyResult').addEventListener('click',async e=>{await navigator.clipboard.writeText(text);e.currentTarget.textContent='Copied'});","$('shareResult').addEventListener('click',async()=>{if(navigator.share)await navigator.share({title:'Wenshi · Xiao Liu Ren',text});else await navigator.clipboard.writeText(text)});","$('result').scrollIntoView({behavior:'smooth',block:'start'});","}"];
const subst = (s) => s.split('@@BT@@').join(BT).split('@@DL@@').join(DL).split('@@NL@@').join(NL);

function readLines(file) { return fs.readFileSync(path.join(DIST, file), 'utf8').split(/\r?\n/); }
function expect(lines, no, needle) {
  if ((lines[no - 1] || '').indexOf(needle) === -1) {
    throw new Error('前置校验失败：第 ' + no + ' 行缺少 ' + JSON.stringify(needle));
  }
}

function buildZh() {
  const lines = readLines('app.js');
  [[4, 'const PALACES='], [5, 'const INSIGHTS='], [13, 'const CATEGORIES='], [14, 'const SUBTYPES='],
   [22, 'CATEGORIES.finance='], [28, 'const LOST_BASE='], [36, 'const LOST_COMBOS='],
   [44, 'function getLostReading('], [45, 'const TOPIC_READINGS='], [55, 'const PALACE_PACE='],
   [56, 'const PALACE_ACTIONS='], [57, 'const EMOTIONAL_READINGS='], [65, 'const CATEGORY_ENDINGS='],
   [75, 'const VERIFY_SIGNALS='], [76, 'function getTopicReading('], [92, 'function detectSubtype('],
   [93, 'function analyzePath('], [97, 'const resultObserver='], [98, 'function enrichInterpretation('],
   [103, 'function render(record){'], [108, "$('result').innerHTML="], [109, 'function renderHistory()']]
    .forEach((c) => expect(lines, c[0], c[1]));

  const src = lines.join('\n');
  const start = src.indexOf('const PALACES=');
  const end = src.indexOf('const $=id=>document.getElementById');
  const names = ['CATEGORIES', 'SUBTYPES'];
  const body = src.slice(start, end) + '\nconst __o={};\n' + names.map((n) =>
    'try{__o[' + JSON.stringify(n) + ']=(typeof ' + n + '!=="undefined")?' + n + ':null}catch(e){__o[' + JSON.stringify(n) + ']=null}'
  ).join('\n') + '\nreturn __o;';
  const v = new Function(body)();
  const newCats = 'const CATEGORIES={' + Object.keys(v.CATEGORIES).map((k) => k + ':[' + JSON.stringify(v.CATEGORIES[k][0]) + ']').join(',') + '};';
  const newSubs = 'const SUBTYPES={' + Object.keys(v.SUBTYPES).map((k) =>
    k + ':[' + v.SUBTYPES[k].map((s) => '[/' + s[0].source + '/' + s[0].flags + ',' + JSON.stringify(s[1]) + ']').join(',') + ']'
  ).join(',') + '};';
  const newDetect = "function detectSubtype(q,category){const found=(SUBTYPES[category]||[]).find(x=>x[0].test(q));return found?{label:found[1]}:{label:CATEGORIES[category][0]};}";

  const line98 = lines[97];
  const cut = line98.indexOf('function enrichInterpretation(');
  if (cut < 0) { throw new Error('第 98 行结构变化'); }
  return [].concat(
    lines.slice(0, 4),
    [newCats, newSubs],
    lines.slice(76, 91),
    [newDetect],
    lines.slice(93, 96),
    [line98.slice(0, cut)],
    lines.slice(98, 102),
    ZH_RENDER_TPL.map(subst),
    lines.slice(108)
  ).join('\n');
}

function buildEn() {
  const lines = readLines('en/app.js');
  [[4, 'const PALACES='], [5, 'const CATEGORY_NAMES='], [7, 'const READINGS='], [17, 'const EMOTIONS='],
   [18, 'const PACE='], [19, 'const ACTIONS='], [20, 'const CLARIFY='], [25, 'function lostReading('],
   [26, 'function buildReading('], [27, 'function downloadCard('], [29, 'function render(record){']]
    .forEach((c) => expect(lines, c[0], c[1]));

  let d = lines[26];
  if (d.indexOf('data.reading.verdict') < 0 || d.indexOf('ACTIONS[data.p.t]') < 0) { throw new Error('downloadCard 结构变化'); }
  d = d.split('wrap(data.reading.verdict').join('wrap(data.brief||data.reading.verdict');
  d = d.split('y=wrap(data.reading.closing,100,y+55,880,43,8)+35;').join("y=wrap('Full interpretation available at lfun.cloud.',100,y+55,880,43,8)+35;");
  d = d.split("wrap('Next step: '+ACTIONS[data.p.t],100,y,880,35,3);").join('wrap(CATEGORY_NAMES[data.record.category],100,y,880,35,3);');

  return [].concat(
    lines.slice(0, 6),
    lines.slice(19, 24),
    [d],
    [lines[27]],
    EN_RENDER_TPL.map(subst),
    lines.slice(29)
  ).join('\n');
}

function run(file, builder) {
  const src = fs.readFileSync(path.join(DIST, file), 'utf8');
  const out = builder();
  const leaked = ['CATEGORY_ENDINGS', 'EMOTIONAL_READINGS', 'TOPIC_READINGS', 'LOST_COMBOS', 'VERIFY_SIGNALS',
    'PALACE_ACTIONS', 'PALACE_PACE', 'getTopicReading', 'getLostReading', 'analyzePath', 'buildReading', 'const READINGS']
    .filter((n) => out.indexOf(n) >= 0);
  if (leaked.length) { throw new Error(file + ' 仍有付费标识残留：' + leaked.join(', ')); }
  if (out.indexOf('@@BT@@') >= 0 || out.indexOf('@@DL@@') >= 0) { throw new Error(file + ' 模板占位符未替换'); }
  try {
    new Function(out);
  } catch (e) {
    throw new Error(file + ' 生成结果语法错误：' + e.message);
  }
  console.log(file + ': ' + src.length + ' -> ' + out.length + ' 字符，付费表已清空且语法通过');
  if (!CHECK) { fs.writeFileSync(path.join(DIST, file), out); }
}

run('app.js', buildZh);
run('en/app.js', buildEn);
console.log(CHECK ? '（--check，未写盘）' : '已写盘。');
