/* 前端端到端测试（真实 DOM 引擎 jsdom + 真实后端）：
 *   起盘 -> 免费层裁剪 -> 付费墙 -> 确认支付 -> 下单 -> 人工收款面板 -> 后台核销 -> 解锁渲染
 * 用法: node tools/e2e-web.js [siteUrl] [apiUrl] [adminUser] [adminPass]
 */
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const path = require('path');
try { fs.rmSync(path.join(__dirname, '..', 'dist', 'api', 'storage', 'rate'), { recursive: true, force: true }); } catch (e) { /* ignore */ }

const SITE = process.argv[2] || 'http://127.0.0.1:8099/';
const API = process.argv[3] || 'http://127.0.0.1:8099/api';
const ADMIN_USER = process.argv[4] || 'admin';
const ADMIN_PASS = process.argv[5] || 'lfun-dev-pass';

let pass = 0, fail = 0;
const failures = [];
function ok(name, cond, extra) {
  if (cond) { pass++; console.log('  PASS  ' + name); }
  else { fail++; failures.push(name + (extra !== undefined ? ' :: ' + JSON.stringify(extra) : '')); console.log('  FAIL  ' + name + (extra !== undefined ? ' :: ' + JSON.stringify(extra) : '')); }
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function waitFor(fn, timeout, label) {
  const end = Date.now() + timeout;
  while (Date.now() < end) {
    try { if (fn()) { return true; } } catch (e) { /* retry */ }
    await sleep(120);
  }
  return false;
}

const scriptErrors = [];

function makeFetch(cookieJar) {
  return function (input, init) {
    init = init || {};
    let url = String(input);
    if (url.indexOf('http') !== 0) { url = new URL(url, SITE).toString(); }
    const headers = Object.assign({}, init.headers || {});
    if (cookieJar.length) { headers['Cookie'] = cookieJar.join('; '); }
    return fetch(url, { method: init.method || 'GET', headers: headers, body: init.body, redirect: 'manual' }).then(function (res) {
      const setC = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
      setC.forEach(function (c) {
        const pair = String(c).split(';')[0];
        const name = pair.split('=')[0];
        for (let i = cookieJar.length - 1; i >= 0; i--) { if (cookieJar[i].split('=')[0] === name) { cookieJar.splice(i, 1); } }
        cookieJar.push(pair);
      });
      const wrapped = {
        ok: res.ok, status: res.status, statusText: res.statusText,
        headers: res.headers,
        text: function () { return res.text(); },
        json: function () { return res.json(); }
      };
      return wrapped;
    });
  };
}

(async function main() {
  const cookieJar = [];
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', function (e) {
    const msg = String((e && e.message) || e);
    if (/Could not parse CSS|Not implemented: HTMLCanvasElement/.test(msg)) { return; }
    scriptErrors.push('jsdomError: ' + msg);
  });
  virtualConsole.on('error', function () { scriptErrors.push('console.error: ' + Array.prototype.join.call(arguments, ' ')); });

  const html = await (await fetch(SITE)).text();
  const dom = new JSDOM(html, {
    url: SITE,
    runScripts: 'dangerously',
    resources: 'usable',
    pretendToBeVisual: true,
    virtualConsole: virtualConsole,
    beforeParse: function (window) {
      window.fetch = makeFetch(cookieJar);
      window.matchMedia = window.matchMedia || function () { return { matches: false, addListener: function () {}, removeListener: function () {}, addEventListener: function () {}, removeEventListener: function () {} }; };
      window.HTMLElement.prototype.scrollIntoView = function () {};
      window.HTMLCanvasElement.prototype.getContext = function () { return null; };
      if (window.HTMLDialogElement) {
        window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); this.__open = true; };
        window.HTMLDialogElement.prototype.show = function () { this.setAttribute('open', ''); this.__open = true; };
        window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.__open = false; this.dispatchEvent(new window.Event('close')); };
      }
      window.addEventListener('error', function (e) { scriptErrors.push('window.onerror: ' + (e.message || e.error)); });
    }
  });

  const win = dom.window;
  const doc = win.document;
  const $ = (sel) => doc.querySelector(sel);
  const $$ = (sel) => Array.prototype.slice.call(doc.querySelectorAll(sel));

  function click(sel) {
    const n = $(sel);
    if (!n) { throw new Error('找不到元素 ' + sel); }
    n.dispatchEvent(new win.MouseEvent('click', { bubbles: true, cancelable: true }));
    return n;
  }

  try {
    // 等前端脚本与配置就绪
    const booted = await waitFor(() => win.LFUN && win.LFUN.config(), 20000);
    ok('前端脚本加载并取得服务端配置', booted);
    if (!booted) { throw new Error('前端未完成初始化'); }

    const cfg = win.LFUN.config();
    ok('配置价格正确', cfg.pricing.single_unlock === 9.9, cfg.pricing);
    ok('页面新增 paywall.css', !!doc.querySelector('link[href="paywall.css"]'));
    ok('页面加载 lfun-pay.js', !!doc.querySelector('script[src="lfun-pay.js"]'));
    ok('导航注入「我的」入口', !!$('nav.header-actions .pw-my-btn'));
    ok('使用声明补充付费与退款条款', !!$('#notice .pw-purchase-section'));
    ok('页脚出现购买须知入口', !!$('[data-pw-purchase]'));

    // 起盘
    $('#question').value = '这次面试还有下一轮吗';
    $('#question').dispatchEvent(new win.Event('input', { bubbles: true }));
    $('#details').value = '上周初面，还没收到反馈';
    $('#questionForm').dispatchEvent(new win.Event('submit', { bubbles: true, cancelable: true }));
    await sleep(300);
    if ($('#skipCast')) { $('#skipCast').click(); }
    const rendered = await waitFor(() => !!$('#result .overview'), 15000);
    ok('结果页渲染完成', rendered);

    ok('免费层保留罗盘', !!$('#result .grand-compass'));
    ok('免费层保留结果标题', !!$('#result .result-top'));
    ok('免费层保留简版断语', !!$('#result .overview'));
    ok('免费层保留公式（可复查）', !!$('#result details.trace'));
    ok('免费层已移除三步详解', !$('#result .reading-steps'));
    ok('免费层已移除行动建议', !$('#result .advice-box'));
    ok('免费层已移除分类专断', !$('#result .topic-reading'));
    ok('免费层已移除解卦语', !$('#result .closing-reading'));
    ok('免费层已移除心理情绪', !$('#result .emotion-reading'));
    ok('分享工具条仍在（免费）', !!$('#result .share-toolbar'));

    const cardOn = await waitFor(() => $('#pwCard .pw-title'), 20000);
    ok('付费墙卡片出现', cardOn);
    const cardText = $('#pwCard') ? $('#pwCard').textContent : '';
    ok('付费墙显示单次价格 ¥9.9', cardText.indexOf('9.9') >= 0);
    ok('付费墙显示会员价格 ¥29', cardText.indexOf('29') >= 0);
    ok('付费墙含「仅供娱乐」声明', cardText.indexOf('仅供娱乐') >= 0 || cardText.indexOf('不构成') >= 0);
    ok('付费墙含未成年人提示', cardText.indexOf('未成年人') >= 0);
    ok('付费墙含退款政策', cardText.indexOf('退款') >= 0);
    ok('付费墙含日时组合断语卖点', cardText.indexOf('组合断语') >= 0);

    const panKey = win.LFUN.state.current.panKey;
    ok('前端取得 pan_key（服务端核定起盘）', typeof panKey === 'string' && panKey.length >= 20, panKey);
    ok('免费层简版断语由服务端下发', typeof win.LFUN.state.current.brief === 'string' && win.LFUN.state.current.brief.length > 0, win.LFUN.state.current.brief);
    const overviewText = ($('#result .overview') || {}).textContent || '';
    ok('免费层总览显示服务端分类断语', overviewText.indexOf(win.LFUN.state.current.brief) >= 0, overviewText.slice(0, 100));

    // 分享卡片用免费层内容
    const payload = win.buildSharePayload();
    ok('分享卡片不含付费解卦语', !!payload && payload.closing.indexOf('解锁') >= 0 || (payload && payload.closing.indexOf('lfun.cloud') >= 0), payload && payload.closing);

    // 点击解锁 -> 确认页
    click('#pwCard [data-act="unlock"]');
    const dlgOn = await waitFor(() => $('#pwPayDialog') && $('#pwPayDialog').hasAttribute('open'), 8000);
    ok('支付前二次确认页出现', dlgOn);
    const payText = $('#pwPayDialog').textContent;
    ok('确认页含金额', payText.indexOf('9.9') >= 0);
    ok('确认页含仅供娱乐提示', payText.indexOf('仅供娱乐') >= 0);
    ok('确认页含未成年人提示', payText.indexOf('未成年人') >= 0);
    ok('确认页含退款说明', payText.indexOf('退款') >= 0);

    click('#pwPayGo');
    const panelOn = await waitFor(() => $('#pwClaimed'), 20000);
    ok('创建订单并进入收款面板', panelOn);
    ok('收款面板显示订单号', /LF\d{10,}/.test($('#pwPayDialog').textContent));
    ok('收款面板含合规提示', $('#pwPayDialog').textContent.indexOf('仅供娱乐') >= 0);
    const orderId = win.LFUN.state.order && win.LFUN.state.order.id;
    ok('订单 ID 存在', typeof orderId === 'number' && orderId > 0, orderId);

    click('#pwClaimed');
    const claimedBox = await waitFor(() => $('#pwClaimedBox'), 12000);
    ok('点「我已付款」后显示等待核销说明', claimedBox, $('#pwClaimedBox') ? $('#pwClaimedBox').textContent.slice(0, 80) : '');
    const claimedText = $('#pwClaimedBox') ? $('#pwClaimedBox').textContent : '';
    ok('等待说明含承诺时长', claimedText.indexOf('30') >= 0, claimedText.slice(0, 120));
    ok('等待说明含回「我的」查看的指引', claimedText.indexOf('我的') >= 0);
    await sleep(500);

    // 后台核销
    const login = await fetch(API + '/admin/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: ADMIN_USER, password: ADMIN_PASS }) });
    const cookie = (login.headers.getSetCookie() || []).map((c) => c.split(';')[0]).join('; ');
    const confRes = await fetch(API + '/admin/orders/' + orderId + '/confirm', { method: 'POST', headers: { Cookie: cookie } });
    const confJson = await confRes.json();
    ok('管理员后台核销成功', confJson.ok === true && confJson.order.status === 'paid', confJson);

    click('#pwRefresh');
    const detailOn = await waitFor(() => $('#result .pw-detail'), 25000);
    ok('轮询到账后渲染详细解读', detailOn);

    ok('解读含起盘三步', $('#result .reading-steps') && $$('#result .reading-step').length >= 4);
    ok('解读含日时组合断语', !!$('.pw-combination'));
    ok('解读含直接判断', !!$('.topic-verdict'));
    ok('解读含关键着眼点网格', $$('.topic-grid article').length === 4);
    ok('解读含行动建议', !!$('.advice-box') && $$('.advice-box li').length >= 3);
    ok('解读含心理与情绪', !!$('.emotion-reading'));
    ok('解读含解卦语', !!$('.closing-reading'));
    ok('解读含合规声明', ($('.pw-detail').textContent || '').indexOf('仅供娱乐') >= 0);
    ok('解锁后付费墙消失', !$('#pwCard'));
    ok('付费解读后才出现打赏入口', !!$('.pw-donate'));

    // 重复查看不再收费
    const again = await win.fetch(API + '/unlock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': win.LFUN.state.token, 'X-Device-Id': win.LFUN.state.device }, body: JSON.stringify({ pan_key: panKey }) });
    const againJson = await again.json();
    ok('重复查看详细解读仍可访问', again.status === 200 && againJson.unlock.reason === 'order', againJson.unlock);

    // 未付费盘面
    const freshPan = await win.fetch(API + '/unlock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': win.LFUN.state.token, 'X-Device-Id': win.LFUN.state.device }, body: JSON.stringify({ pan_key: 'nonexistent0000000000000000000000000000000' }) });
    ok('未知盘面返回 404', freshPan.status === 404, freshPan.status);

    // 我的面板
    click('nav.header-actions .pw-my-btn');
    const meOn = await waitFor(() => $('#pwMeDialog') && $('#pwMeDialog').hasAttribute('open'), 12000);
    ok('「我的」面板可打开', meOn);
    const meOk = await waitFor(() => {
      const d = $('#pwMeDialog');
      return d && d.textContent.indexOf('订单') >= 0 && d.querySelector('#pwMeBody');
    }, 20000);
    const meText = $('#pwMeDialog') ? $('#pwMeDialog').textContent : '';
    ok('「我的」显示会员与订单信息', meOk, meText.slice(0, 140));

    // 寻物流程
    click('#pwMeDialog .pw-close');
    $('#question').value = '我的钥匙丢在哪里了';
    $('#question').dispatchEvent(new win.Event('input', { bubbles: true }));
    $('#category').value = 'auto';
    $('#questionForm').dispatchEvent(new win.Event('submit', { bubbles: true, cancelable: true }));
    await sleep(300);
    if ($('#skipCast')) { $('#skipCast').click(); }
    await waitFor(() => $('#pwCard'), 20000);
    // 等第二次起盘的服务端盘面注册完成（pan_key 与第一盘不同）
    const freshKey = await waitFor(() => win.LFUN.state.current && win.LFUN.state.current.panKey && win.LFUN.state.current.panKey !== panKey, 25000);
    ok('二次起盘（寻物）注册成功', freshKey, win.LFUN.state.current && win.LFUN.state.current.panKey);
    const lostKey = win.LFUN.state.current.panKey;
    ok('寻物识别为 lost 类别', win.LFUN.state.current.issue && win.LFUN.state.current.issue.category === 'lost', win.LFUN.state.current.issue);
    const lostOrder = await fetch(API + '/orders', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': win.LFUN.state.token, 'X-Device-Id': win.LFUN.state.device }, body: JSON.stringify({ type: 'single_unlock', pan_key: lostKey }) });
    const lostOrderJson = await lostOrder.json();
    ok('寻物盘可下单', !!(lostOrderJson && lostOrderJson.order), lostOrderJson);
    if (!lostOrderJson || !lostOrderJson.order) { throw new Error('寻物下单失败'); }
    await fetch(API + '/admin/orders/' + lostOrderJson.order.id + '/confirm', { method: 'POST', headers: { Cookie: cookie } });
    const lostUnlock = await fetch(API + '/unlock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Auth-Token': win.LFUN.state.token, 'X-Device-Id': win.LFUN.state.device }, body: JSON.stringify({ pan_key: lostKey }) });
    const lostJson = await lostUnlock.json();
    const lostSection = (lostJson.detail.sections || []).filter((s) => s.type === 'lost')[0];
    ok('寻物解读含找回倾向', !!lostSection && !!lostSection.chance, lostSection && lostSection.chance);
    ok('寻物解读含方位/位置/节奏', !!lostSection && lostSection.items.length === 3);
    ok('寻物解读含搜索顺序', !!lostSection && lostSection.actions.length === 3);
    ok('寻物解读体现日时组合（宫名+倾向）', !!lostSection && lostSection.combination.indexOf('＋') > 0 && !!lostSection.chance, lostSection && lostSection.combination);
    ok('寻物解读含组合定位信息', !!lostSection && lostSection.items.some((it) => it.text && it.text.length > 0));

    // 健康类问题：免费层必须保留免责提示（合规）
    $('#question').value = '家人的手术要不要往后推迟';
    $('#question').dispatchEvent(new win.Event('input', { bubbles: true }));
    $('#category').value = 'auto';
    $('#questionForm').dispatchEvent(new win.Event('submit', { bubbles: true, cancelable: true }));
    await sleep(300);
    if ($('#skipCast')) { $('#skipCast').click(); }
    const cautionOn = await waitFor(() => $('#result .caution'), 20000);
    ok('健康类问题免费层保留免责提示', cautionOn, $('#result .caution') ? $('#result .caution').textContent.slice(0, 60) : '缺失');
    ok('免责提示未被付费墙裁掉', (($('#result .caution') || {}).hidden !== true) && !!$('#result .overview'));
    ok('免费层仍未下发病理类付费文本', $('#result').textContent.indexOf('容易陷入') === -1);

    await sleep(300);
    ok('页面无未捕获脚本异常', scriptErrors.length === 0, scriptErrors.slice(0, 4));
  } catch (e) {
    fail++;
    failures.push('运行异常: ' + e.message);
    console.log('  FAIL  运行异常 :: ' + e.message + '\n' + (e.stack || '').split('\n').slice(0, 4).join('\n'));
  } finally {
    try { dom.window.close(); } catch (e) { /* ignore */ }
  }

  console.log('\n前端测试结果：' + pass + ' 通过 / ' + fail + ' 失败');
  if (failures.length) { failures.forEach((f) => console.log('  - ' + f)); }
  process.exit(fail === 0 ? 0 : 1);
})();
