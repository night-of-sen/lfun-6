
/* 从现有前端 app.js / en/app.js 抽取内容表，生成服务端内容库（保证与现状零漂移）。 */
const fs = require('fs');
const path = require('path');

const SITE = process.argv[2];
const OUT  = process.argv[3];

function extract(file, names) {
  const src = fs.readFileSync(file, 'utf8');
  const start = src.indexOf('const PALACES=');
  if (start < 0) throw new Error('PALACES not found: ' + file);
  const markers = ['const $=id=>document.getElementById', 'const $=id=>'];
  let end = -1;
  for (const m of markers) { const i = src.indexOf(m, start); if (i > 0) { end = i; break; } }
  if (end < 0) end = src.length;
  const slice = src.slice(start, end);
  const lines = names.map(function (n) {
    return 'try { __out[' + JSON.stringify(n) + '] = (typeof ' + n + ' !== "undefined") ? ' + n + ' : null; } catch (e) { __out[' + JSON.stringify(n) + '] = null; }';
  }).join('\n');
  return new Function(slice + '\nconst __out={};\n' + lines + '\nreturn __out;')();
}

/* 抽取游离在 DOM 段之后的小常量（CLARIFY_PROMPTS / 英文 lost base）。 */
function extractSingle(file, name) {
  const src = fs.readFileSync(file, 'utf8');
  const start = src.indexOf('const ' + name + '=');
  if (start < 0) return null;
  // 找到匹配的收尾分号
  let i = src.indexOf('{', start), depth = 0, j = i;
  for (; j < src.length; j++) {
    const ch = src[j];
    if (ch === '{') depth++;
    else if (ch === '}') { depth--; if (depth === 0) { j++; break; } }
  }
  const stmt = src.slice(start, j) + ';';
  return new Function(stmt + '\nreturn ' + name + ';')();
}

/* 把 RegExp 转成可序列化标记 */
function serialize(v) {
  if (v instanceof RegExp) return { __re: v.source, __flags: v.flags };
  if (Array.isArray(v)) return v.map(serialize);
  if (v && typeof v === 'object') {
    const o = {};
    for (const k of Object.keys(v)) o[k] = serialize(v[k]);
    return o;
  }
  return v;
}

const zhNames = ['PALACES','INSIGHTS','CATEGORIES','SUBTYPES','LOST_BASE','LOST_COMBOS','TOPIC_READINGS','PALACE_PACE','PALACE_ACTIONS','EMOTIONAL_READINGS','CATEGORY_ENDINGS','VERIFY_SIGNALS','CATEGORY_RULES'];
const enNames = ['PALACES','CATEGORY_NAMES','RULES','READINGS','EMOTIONS','PACE','ACTIONS','CLARIFY'];

const zhRaw = serialize(extract(path.join(SITE, 'app.js'), zhNames));
const enRaw = serialize(extract(path.join(SITE, 'en', 'app.js'), enNames));
const zhClarify = serialize(extractSingle(path.join(SITE, 'app.js'), 'CLARIFY_PROMPTS'));

const ORDER = ['work','cooperation','relationship','finance','study','health','travel','lost','general'];

function pad6(arr, fallback) {
  const out = [];
  for (let i = 0; i < 6; i++) out.push(arr && arr[i] !== undefined && arr[i] !== null ? arr[i] : fallback);
  return out;
}

/* ---------- 中文 ---------- */
const zh = {
  lang: 'zh',
  palaces: [],
  paces: zhRaw.PALACE_PACE,
  palace_actions: zhRaw.PALACE_ACTIONS,
  emotions: [],
  categories: {},
  lost: { base: zhRaw.LOST_BASE, combos: zhRaw.LOST_COMBOS },
  clarify: zhClarify,
  classify_rules: zhRaw.CATEGORY_RULES,
};

for (let i = 0; i < 6; i++) {
  const p = zhRaw.PALACES[i], ins = zhRaw.INSIGHTS[i];
  zh.palaces.push({
    name: p[0], sub: p[1], brief: p[2],
    nature: ins.nature, process: ins.process, focus: ins.focus, avoid: ins.avoid,
  });
  const e = zhRaw.EMOTIONAL_READINGS[i];
  zh.emotions.push({ state: e.state, risk: e.risk, balance: e.balance });
}

for (const cat of ORDER) {
  if (cat === 'lost') continue;
  const c = zhRaw.CATEGORIES[cat];
  const topic = zhRaw.TOPIC_READINGS[cat] || zhRaw.TOPIC_READINGS.general;
  const endings = zhRaw.CATEGORY_ENDINGS[cat] || zhRaw.CATEGORY_ENDINGS.general;
  const subs = (zhRaw.SUBTYPES[cat] || []).map(function (s) {
    return {
      pattern: (s[0] && s[0].__re) ? s[0].__re : '',
      flags: (s[0] && s[0].__flags) ? s[0].__flags : 'i',
      label: s[1], guidance: s[2],
    };
  });
  zh.categories[cat] = {
    label: c[0],
    base_actions: c[1],
    guidance: c[2],
    verdict: topic.map(function (x) { return x[0]; }),
    focus: topic.map(function (x) { return x[1]; }),
    closing: pad6(endings, ''),
    signal: (zhRaw.VERIFY_SIGNALS && zhRaw.VERIFY_SIGNALS[cat]) || (zhRaw.VERIFY_SIGNALS && zhRaw.VERIFY_SIGNALS.general) || '',
    subtypes: subs,
  };
}

/* ---------- 英文 ---------- */
const en = {
  lang: 'en',
  palaces: [],
  paces: enRaw.PACE,
  palace_actions: enRaw.ACTIONS,
  emotions: [],
  categories: {},
  lost: { base: [], combos: {} },
  clarify: enRaw.CLARIFY,
  classify_rules: enRaw.RULES,
};

for (let i = 0; i < 6; i++) {
  const p = enRaw.PALACES[i];
  const e = enRaw.EMOTIONS[i];
  en.palaces.push({ name: p[0], sub: p[1], brief: p[2] });
  en.emotions.push({ state: e[0], risk: e[1], balance: e[1] });
}

for (const cat of ORDER) {
  if (cat === 'lost') continue;
  const rd = enRaw.READINGS[cat] || enRaw.READINGS.general;
  en.categories[cat] = {
    label: enRaw.CATEGORY_NAMES[cat],
    base_actions: [],
    guidance: rd.map(function (x) { return x[1]; }),
    verdict: rd.map(function (x) { return x[0]; }),
    focus: rd.map(function (x) { return x[1]; }),
    closing: [],
    signal: '',
    subtypes: [],
  };
}
/* 英文寻物基础表来自 lostReading() 内的数组 */
const enLostSrc = fs.readFileSync(path.join(SITE, 'en', 'app.js'), 'utf8');
const lm = enLostSrc.match(/const base=\[([\s\S]*?)\]\[p\.t\]/);
if (lm) {
  const arr = (new Function('return [' + lm[1] + '];'))();
  en.lost.base = arr.map(function (x) { return { chance: x[0], direction: x[1], position: x[2], timing: '' }; });
}

/* ---------- PHP 代码生成 ---------- */
function esc(s) { return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'"); }
function php(v, indent) {
  const pad = ' '.repeat(indent);
  const pad2 = ' '.repeat(indent + 4);
  if (v === null || v === undefined) return 'null';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  if (typeof v === 'number') return Number.isInteger(v) ? String(v) : String(v);
  if (typeof v === 'string') return "'" + esc(v) + "'";
  if (Array.isArray(v)) {
    if (v.length === 0) return '[]';
    const isList = v.every(function (x) { return x === null; }) || true;
    return '[\n' + v.map(function (x) { return pad2 + php(x, indent + 4) + ','; }).join('\n') + '\n' + pad + ']';
  }
  const keys = Object.keys(v);
  if (keys.length === 0) return '[]';
  return '[\n' + keys.map(function (k) {
    return pad2 + "'" + esc(k) + "' => " + php(v[k], indent + 4) + ',';
  }).join('\n') + '\n' + pad + ']';
}

function writePhp(file, header, data) {
  const body = '<?php\n' + header + '\nreturn ' + php(data, 0) + ';\n';
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, body);
  return body.length;
}

const hz = '/* 自动生成，请勿手改：由 tools/build-content.js 从 dist/app.js 抽取，保证与前端现状一致。\n * 人工扩写请改 data/deep.zh.php 与 data/overrides.zh.php。 */';
const he = '/* Auto-generated by tools/build-content.js from dist/en/app.js. Do not edit by hand;\n * author overrides in data/deep.en.php and data/overrides.en.php. */';

const a = writePhp(path.join(OUT, 'content.zh.php'), hz, zh);
const b = writePhp(path.join(OUT, 'content.en.php'), he, en);
console.log('content.zh.php ' + a + ' bytes');
console.log('content.en.php ' + b + ' bytes');
console.log('zh categories: ' + Object.keys(zh.categories).join(','));
console.log('en categories: ' + Object.keys(en.categories).join(','));
console.log('en lost base: ' + en.lost.base.length);
fs.writeFileSync(path.join(path.dirname(OUT), '..', '..', '..', '..', 'lfun', 'content_normalized.json'), JSON.stringify({ zh: zh, en: en }, null, 1));
