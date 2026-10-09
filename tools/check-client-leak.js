/* 客户端付费内容泄漏检查：确认详细解读文案只存在于服务端。
 * 用法：node tools/check-client-leak.js [siteUrl]
 */
const BASE = process.argv[2] || 'http://127.0.0.1:8099';

const MUST_NOT_APPEAR = [
  ['中文 · 分类解卦语', '此课重在守成蓄力'],
  ['中文 · 月宫象意', '月宫落大安，说明起点较稳'],
  ['中文 · 日时组合断语', '日宫与时宫同落'],
  ['中文 · 寻物方位', '东方或原处附近'],
  ['中文 · 心理情绪', '容易陷入'],
  ['中文 · 应验信号', '书面邀约、明确下一轮时间'],
  ['中文 · 日宫+时宫组合', '由留连转速喜'],
  ['英文 · 情绪表', 'You may want certainty and control'],
  ['英文 · 寻物', 'Good chance; it is probably still close'],
  ['英文 · 分类解读', 'Favorable if you build on what is already working'],
];

const MUST_APPEAR_ZH = ['const PALACES=', 'const CATEGORIES=', 'function calculate(', 'function render(', 'const SUBTYPES='];
const MUST_APPEAR_EN = ['const PALACES=', 'function lunar(', 'function render(', 'CATEGORY_NAMES'];

let pass = 0, fail = 0;
function ok(name, cond, extra) {
  if (cond) { pass++; console.log('  PASS  ' + name); }
  else { fail++; console.log('  FAIL  ' + name + (extra ? ' :: ' + JSON.stringify(extra) : '')); }
}

(async function main() {
  const targets = [
    ['dist/app.js', BASE + '/app.js', MUST_APPEAR_ZH],
    ['dist/en/app.js', BASE + '/en/app.js', MUST_APPEAR_EN],
  ];
  const bodies = {};
  for (const [label, url] of targets) {
    const res = await fetch(url);
    const text = await res.text();
    ok('可下载 ' + label, res.status === 200 && text.length > 1000, { status: res.status, len: text.length });
    bodies[label] = text;
  }
  const all = Object.values(bodies).join('\n');

  console.log('  -- 付费文案必须不在客户端 --');
  for (const [label, needle] of MUST_NOT_APPEAR) {
    ok(label + ' 未出现在客户端', all.indexOf(needle) === -1);
  }

  console.log('  -- 免费层必须保留 --');
  for (const [label, url] of targets) {
    const musts = url.indexOf('/en/') >= 0 ? MUST_APPEAR_EN : MUST_APPEAR_ZH;
    for (const m of musts) {
      ok(label + ' 保留 ' + m, bodies[label].indexOf(m) >= 0);
    }
  }

  // 免费层内容确实还能渲染（分享卡片依赖的字段）
  ok('客户端仍提供六宫简版（分享卡片用）', bodies['dist/app.js'].indexOf('const PALACES=') >= 0 && bodies['dist/en/app.js'].indexOf('const PALACES=') >= 0);

  console.log('\n泄漏检查结果：' + pass + ' 通过 / ' + fail + ' 失败');
  process.exit(fail === 0 ? 0 : 1);
})();
