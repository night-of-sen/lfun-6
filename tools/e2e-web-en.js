/* 英文页面 DOM 冒烟测试（jsdom + 真实后端） */
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const path = require('path');
try { fs.rmSync(path.join(__dirname, '..', 'dist', 'api', 'storage', 'rate'), { recursive: true, force: true }); } catch (e) { /* ignore */ }

const SITE = (process.argv[2] || 'http://127.0.0.1:8099') + '/en/';
let pass = 0, fail = 0;
const failures = [];
function ok(n, c, extra) {
  if (c) { pass++; console.log('  PASS  ' + n); }
  else { fail++; failures.push(n); console.log('  FAIL  ' + n + (extra !== undefined ? ' :: ' + JSON.stringify(extra) : '')); }
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function waitFor(fn, t) { const e = Date.now() + t; while (Date.now() < e) { try { if (fn()) { return true; } } catch (x) {} await sleep(120); } return false; }

(async function () {
  const jar = [], errors = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', (e) => { const m = String((e && e.message) || e); if (!/Could not parse CSS|HTMLCanvasElement/.test(m)) errors.push(m); });
  const mk = (input, init) => {
    init = init || {};
    let url = String(input);
    if (url.indexOf('http') !== 0) { url = new URL(url, SITE).toString(); }
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

  const html = await (await fetch(SITE)).text();
  const dom = new JSDOM(html, {
    url: SITE, runScripts: 'dangerously', resources: 'usable', pretendToBeVisual: true, virtualConsole: vc,
    beforeParse(w) {
      w.fetch = mk;
      w.matchMedia = w.matchMedia || function () { return { matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }; };
      w.HTMLElement.prototype.scrollIntoView = function () {};
      w.HTMLCanvasElement.prototype.getContext = function () { return null; };
      if (w.HTMLDialogElement) {
        w.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
        w.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new w.Event('close')); };
      }
      w.addEventListener('error', (e) => errors.push('window.onerror: ' + (e.message || e.error)));
    }
  });
  const w = dom.window, d = w.document;
  const $ = (s) => d.querySelector(s);

  try {
    ok('英文页加载 paywall.css', !!$('link[href="../paywall.css"]'));
    ok('英文页加载 lfun-pay.js', !!$('script[src="../lfun-pay.js"]'));
    ok('英文页初始化成功', await waitFor(() => w.LFUN && w.LFUN.config(), 20000));

    $('#question').value = 'Will this interview move forward?';
    $('#question').dispatchEvent(new w.Event('input', { bubbles: true }));
    $('#questionForm').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
    ok('英文页起盘渲染', await waitFor(() => $('#result .en-result-head'), 15000));

    ok('免费层保留落宫标题', !!$('#result .en-result-head'));
    ok('免费层保留所问', !!$('#result .asked'));
    ok('免费层保留直接判断卡片', !!$('#result .en-card'));
    ok('免费层保留分享入口', !!$('#result .en-share'));
    ok('免费层未包含情绪/行动/解卦', !$('#result .emotion-reading') && !$('#result .en-actions') && !$('#result .en-closing'));

    const card = await waitFor(() => $('#pwCard .pw-title'), 20000);
    ok('英文付费墙出现', card);
    const ct = $('#pwCard') ? $('#pwCard').textContent : '';
    ok('英文付费墙为英文', /[A-Za-z]/.test(ct) && !/[\u4e00-\u9fff]/.test(ct), ct.slice(0, 80));
    ok('英文付费墙含价格', ct.indexOf('9.9') >= 0);
    ok('英文付费墙含娱乐声明', /entertainment|not advice/i.test(ct), ct.slice(0, 120));

    ok('英文免费层无付费文案', $('#result').textContent.indexOf('You may want certainty') === -1);
    ok('英文页无未捕获异常', errors.length === 0, errors.slice(0, 3));
  } catch (e) {
    fail++; failures.push('运行异常: ' + e.message);
    console.log('  FAIL  运行异常 :: ' + e.message);
  } finally {
    try { dom.window.close(); } catch (e) { /* ignore */ }
  }
  console.log('\n英文页测试结果：' + pass + ' 通过 / ' + fail + ' 失败');
  failures.forEach((f) => console.log('  - ' + f));
  process.exit(fail === 0 ? 0 : 1);
})();
