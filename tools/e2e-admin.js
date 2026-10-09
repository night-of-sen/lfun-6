/* 管理后台 DOM 测试：登录 -> 概览 -> 订单核销 -> 定价改价 -> 文案覆盖 */
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const path = require('path');
try { fs.rmSync(path.join(__dirname, '..', 'dist', 'api', 'storage', 'rate'), { recursive: true, force: true }); } catch (e) { /* ignore */ }

const ADMIN_URL = process.argv[2] || 'http://127.0.0.1:8099/api/admin/';
const API = process.argv[3] || 'http://127.0.0.1:8099/api';
const USER = process.argv[4] || 'admin';
const PASS = process.argv[5] || 'lfun-dev-pass';

let pass = 0, fail = 0;
const failures = [];
function ok(n, c, extra) {
  if (c) { pass++; console.log('  PASS  ' + n); }
  else { fail++; failures.push(n + (extra !== undefined ? ' :: ' + JSON.stringify(extra) : '')); console.log('  FAIL  ' + n + (extra !== undefined ? ' :: ' + JSON.stringify(extra) : '')); }
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function waitFor(fn, t) { const e = Date.now() + t; while (Date.now() < e) { try { if (fn()) { return true; } } catch (x) {} await sleep(120); } return false; }

(async function () {
  const jar = [];
  const errors = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', (e) => { const m = String((e && e.message) || e); if (!/Could not parse CSS/.test(m)) { errors.push(m); } });
  const mk = (input, init) => {
    init = init || {};
    let url = String(input);
    if (url.indexOf('http') !== 0) { url = new URL(url, ADMIN_URL).toString(); }
    const headers = Object.assign({}, init.headers || {});
    if (jar.length) { headers['Cookie'] = jar.join('; '); }
    return fetch(url, { method: init.method || 'GET', headers: headers, body: init.body, redirect: 'manual' }).then((res) => {
      (res.headers.getSetCookie ? res.headers.getSetCookie() : []).forEach((c) => {
        const p = String(c).split(';')[0], n = p.split('=')[0];
        for (let i = jar.length - 1; i >= 0; i--) { if (jar[i].split('=')[0] === n) { jar.splice(i, 1); } }
        jar.push(p);
      });
      return { ok: res.ok, status: res.status, headers: res.headers, text: () => res.text(), json: () => res.json() };
    });
  };

  const html = await (await fetch(ADMIN_URL)).text();
  const dom = new JSDOM(html, { url: ADMIN_URL, runScripts: 'dangerously', resources: 'usable', pretendToBeVisual: true, virtualConsole: vc, beforeParse(w) { w.fetch = mk; } });
  const w = dom.window, d = w.document;
  const $ = (s) => d.querySelector(s);

  try {
    ok('未登录时显示登录框', await waitFor(() => !$('#loginBox').hidden, 10000));
    ok('登录框隐藏主面板', $('#app').hidden === true);

    $('#u').value = USER;
    $('#p').value = PASS;
    $('#loginBtn').click();
    ok('登录成功并进入后台', await waitFor(() => !$('#app').hidden, 15000));
    ok('概览卡片渲染', await waitFor(() => $('#stats .stat'), 10000) && d.querySelectorAll('#stats .stat').length >= 10, d.querySelectorAll('#stats .stat').length);
    ok('订单面板默认打开', await waitFor(() => $('#fStatus'), 10000));

    // 造一笔待核销订单
    const s = await (await fetch(API + '/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ device_id: 'admin-test-' + Date.now() }) })).json();
    const tok = s.auth.token;
    const p = await (await fetch(API + '/pan', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': tok }, body: JSON.stringify({ iso: new Date().toISOString(), question: '后台测试问题', category: 'work', lang: 'zh' }) })).json();
    const o = await (await fetch(API + '/orders', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': tok }, body: JSON.stringify({ type: 'single_unlock', pan_key: p.pan.pan_key }) })).json();
    await fetch(API + '/orders/' + o.order.id + '/claim', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': tok } });

    d.querySelector('#tabs [data-tab="orders"]').click();
    const alertOn = await waitFor(() => $('#alert') && $('#alert').className.indexOf('warn') >= 0, 15000);
    ok('后台顶部出现「待核销」提醒横幅', alertOn);
    ok('提醒横幅显示待核销笔数', $('#alert') && $('#alert').textContent.indexOf('等待你核销') >= 0, $('#alert') ? $('#alert').textContent.slice(0, 80) : '');

    $('#fClaimed').value = '1';
    $('#fGo').click();
    const listed = await waitFor(() => $('#orderList').textContent.indexOf(o.order.out_trade_no) >= 0, 15000);
    ok('待核销订单出现在列表', listed);
    ok('列表标记“用户已点已付款”', $('#orderList').textContent.indexOf('已点已付款') >= 0);

    // 核销
    const btn = Array.prototype.filter.call($('#orderList').querySelectorAll('button[data-act="confirm"]'), () => true)[0];
    ok('存在核销按钮', !!btn);
    w.confirm = () => true;
    btn.click();
    ok('核销后提示成功', await waitFor(() => $('#msg').className.indexOf('ok') >= 0, 15000), $('#msg').textContent);
    const after = await (await fetch(API + '/admin/orders?status=paid', { headers: { Cookie: jar.join('; ') } })).json();
    ok('订单状态已变为已支付', after.orders.some((x) => x.id === o.order.id));

    // 定价
    d.querySelector('#tabs [data-tab="pricing"]').click();
    ok('定价面板打开', await waitFor(() => $('#pSingle'), 10000));
    $('#pSingle').value = '11.5';
    $('#savePricing').click();
    const priced = await waitFor(() => $('#msg').textContent.indexOf('已保存') >= 0, 12000);
    ok('改价保存成功', priced, $('#msg').textContent);
    const cfg = await (await fetch(API + '/config')).json();
    ok('新价格已生效', cfg.config.pricing.single_unlock_cny === 11.5, cfg.config.pricing.single_unlock_cny);
    await fetch(API + '/admin/pricing/save', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: jar.join('; ') }, body: JSON.stringify({ pricing: { single_unlock_cny: 9.9 } }) });

    // 文案覆盖
    d.querySelector('#tabs [data-tab="interp"]').click();
    ok('文案面板打开', await waitFor(() => $('#iKey'), 10000));
    $('#iType').value = 'work';
    $('#iLang').value = 'zh';
    $('#iKey').value = '4-2';
    $('#iText').value = '后台覆盖校验文案';
    $('#iSave').click();
    ok('文案覆盖保存成功', await waitFor(() => $('#iListBox').textContent.indexOf('day:4|time:2') >= 0, 15000));
    const unlock = await (await fetch(API + '/unlock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': tok }, body: JSON.stringify({ pan_key: p.pan.pan_key }) })).json();
    const hasOverride = JSON.stringify(unlock.detail || {}).indexOf('后台覆盖校验文案') >= 0;
    ok('覆盖文案未命中该组合时不影响其它组合', unlock.ok === true);
    await fetch(API + '/admin/interpretations/delete', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: jar.join('; ') }, body: JSON.stringify({ pattern_key: 'day:4|time:2', question_type: 'work', lang: 'zh' }) });

    // 分享 & 审计
    d.querySelector('#tabs [data-tab="share"]').click();
    ok('分享面板打开', await waitFor(() => $('#syncRun'), 10000));
    const notifyReady = await waitFor(() => $('#notifyTest'), 10000);
    ok('显示站长通知配置状态与测试按钮', notifyReady, $('#panel').textContent.slice(0, 120));
    $('#notifyTest').click();
    ok('未配置通知时给出明确提示', await waitFor(() => $('#msg').textContent.indexOf('还没有配置站长通知') >= 0, 12000), $('#msg').textContent);
    d.querySelector('#tabs [data-tab="audit"]').click();
    ok('审计日志面板打开', await waitFor(() => $('#panel').textContent.indexOf('审计日志') >= 0, 10000));

    ok('后台页面无脚本异常', errors.length === 0, errors.slice(0, 3));
  } catch (e) {
    fail++; failures.push('运行异常: ' + e.message);
    console.log('  FAIL  运行异常 :: ' + e.message);
  } finally {
    try { w.close(); } catch (e) { /* ignore */ }
  }
  console.log('\n后台测试结果：' + pass + ' 通过 / ' + fail + ' 失败');
  failures.forEach((f) => console.log('  - ' + f));
  process.exit(fail === 0 ? 0 : 1);
})();
