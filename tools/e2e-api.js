
/* 端到端接口冒烟测试：node tools/e2e-api.js [baseUrl] */
const fs = require('fs');
const path = require('path');
const BASE = process.argv[2] || 'http://127.0.0.1:8099';

// 本地跑测试时先清空限流桶，避免连续多次运行互相干扰。
if (BASE.indexOf('127.0.0.1') >= 0 || BASE.indexOf('localhost') >= 0) {
  const rateDir = path.join(__dirname, '..', 'dist', 'api', 'storage', 'rate');
  try { fs.rmSync(rateDir, { recursive: true, force: true }); } catch (e) { /* ignore */ }
}
let pass = 0, fail = 0;
const failures = [];

function ok(name, cond, extra) {
  if (cond) { pass++; console.log('  PASS  ' + name); }
  else { fail++; failures.push(name + (extra ? ' :: ' + JSON.stringify(extra) : '')); console.log('  FAIL  ' + name + (extra ? ' :: ' + JSON.stringify(extra) : '')); }
}

async function api(path, opts) {
  opts = opts || {};
  const headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
  if (opts.token) headers['X-Auth-Token'] = opts.token;
  if (opts.device) headers['X-Device-Id'] = opts.device;
  if (opts.cookie) headers['Cookie'] = opts.cookie;
  const res = await fetch(BASE + '/api/' + path, {
    method: opts.method || 'GET',
    headers,
    body: opts.body ? JSON.stringify(opts.body) : undefined,
    redirect: 'manual',
  });
  let json = null;
  const text = await res.text();
  try { json = JSON.parse(text); } catch (e) { json = { raw: text }; }
  return { status: res.status, json, headers: res.headers };
}

function uuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

(async function main() {
  console.log('== 问时 · API 端到端测试 == ' + BASE);

  const health = await api('health');
  ok('health 200', health.status === 200 && health.json.ok === true, health.json);

  const conf = await api('config');
  ok('config 返回定价', conf.json.config && conf.json.config.pricing.single_unlock_cny === 9.9, conf.json.config && conf.json.config.pricing);
  ok('config 未泄露密钥', JSON.stringify(conf.json).indexOf('private_key') === -1);

  // --- 用户 A ---
  const devA = uuid();
  const sA = await api('session', { method: 'POST', body: { device_id: devA } });
  ok('session 创建用户', sA.status === 200 && sA.json.auth && sA.json.auth.token, sA.json);
  const tokA = sA.json.auth.token;

  const sA2 = await api('session', { method: 'POST', body: { device_id: devA }, token: tokA });
  ok('同一设备识别为同一用户', sA2.json.user.id === sA.json.user.id, { a: sA.json.user.id, b: sA2.json.user.id });

  // 只带 device_id（模拟清空 localStorage 后用 cookie 找回）
  const sA3 = await api('session', { method: 'POST', body: { device_id: devA } });
  ok('仅凭 device_id 找回同一用户', sA3.json.user.id === sA.json.user.id, { a: sA.json.user.id, b: sA3.json.user.id });

  // --- 起盘 ---
  const panRes = await api('pan', { method: 'POST', token: tokA, device: devA, body: {
    iso: new Date().toISOString(),
    question: '这次面试还有下一轮吗',
    details: '上周初面，还没收到反馈',
    category: 'auto',
    lang: 'zh',
  }});
  ok('POST /pan 成功', panRes.status === 200 && panRes.json.pan && panRes.json.pan.pan_key, panRes.json);
  const panKey = panRes.json.pan.pan_key;
  ok('起盘分类为 work', panRes.json.issue.category === 'work', panRes.json.issue);
  ok('免费层返回简版断语', typeof panRes.json.brief === 'string' && panRes.json.brief.length > 0, panRes.json.brief);
  ok('免费层未解锁', panRes.json.unlocked === false);
  ok('免费层不下发详细解读', panRes.json.detail === undefined);

  // --- 未付费访问详细解读 -> 402 ---
  const locked = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: panKey } });
  ok('未付费返回 402', locked.status === 402 && locked.json.error === 'payment_required', locked.json);
  ok('402 不泄露解读文本', !locked.json.detail);

  // --- 别人不能看 ---
  const devB = uuid();
  const sB = await api('session', { method: 'POST', body: { device_id: devB } });
  const tokB = sB.json.auth.token;
  const cross = await api('unlock', { method: 'POST', token: tokB, device: devB, body: { pan_key: panKey } });
  ok('越权访问返回 403', cross.status === 403, cross.json);

  // --- 下单 ---
  const orderRes = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'single_unlock', pan_key: panKey } });
  ok('下单成功', orderRes.status === 200 && orderRes.json.order && orderRes.json.order.id, orderRes.json);
  const orderId = orderRes.json.order.id;
  ok('金额由服务端核定 990 分', orderRes.json.order.amount_cents === 990, orderRes.json.order);
  ok('人工收款返回收款参数', orderRes.json.pay.channel === 'manual', orderRes.json.pay);
  ok('下单带合规提示', !!(orderRes.json.notice && orderRes.json.notice.pay && orderRes.json.notice.minor), orderRes.json.notice);

  // 前端传金额不生效
  const orderRes2 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'single_unlock', pan_key: panKey, amount_cents: 1 } });
  ok('前端传金额不生效', orderRes2.json.order.amount_cents === 990, orderRes2.json.order);

  const poll = await api('orders/' + orderId, { token: tokA, device: devA });
  ok('订单查询 pending', poll.json.order.status === 'pending', poll.json.order);

  const cfgSupport = await api('config');
  ok('公开配置含客服与人工核销等待时长', typeof cfgSupport.json.config.support.manual_wait_minutes === 'number' && cfgSupport.json.config.support.manual_wait_minutes === 30, cfgSupport.json.config.support);
  ok('公开配置不泄露通知 webhook', JSON.stringify(cfgSupport.json).indexOf('webhook') === -1 && JSON.stringify(cfgSupport.json).indexOf('notify') === -1);

  const claim = await api('orders/' + orderId + '/claim', { method: 'POST', token: tokA, device: devA });
  ok('用户标记已付款', claim.json.order.claimed === true && claim.json.order.status === 'pending', claim.json.order);

  // --- 管理员核销 ---
  const login = await api('admin/login', { method: 'POST', body: { username: 'admin', password: 'lfun-dev-pass' } });
  ok('管理员登录', login.status === 200 && login.json.ok === true, login.json);
  const setCookie = login.headers.getSetCookie ? login.headers.getSetCookie() : [login.headers.get('set-cookie')];
  const adminCookie = (setCookie || []).map(function (c) { return String(c).split(';')[0]; }).join('; ');
  ok('管理员 cookie 已下发', adminCookie.indexOf('lfun_admin=') === 0, adminCookie);

  const badLogin = await api('admin/login', { method: 'POST', body: { username: 'admin', password: 'wrong' } });
  ok('错误密码被拒绝', badLogin.status === 401, badLogin.json);

  const noAuth = await api('admin/stats');
  ok('未登录访问后台返回 401', noAuth.status === 401, noAuth.json);

  const statsEarly = await api('admin/stats', { cookie: adminCookie });
  ok('后台暴露待核销数量与通知状态', statsEarly.json.stats.orders_claimed >= 1 && statsEarly.json.notify && statsEarly.json.notify.enabled === false, statsEarly.json.notify);
  const notifyTest = await api('admin/notify/test', { method: 'POST', cookie: adminCookie });
  ok('未配置通知时测试接口明确报错', notifyTest.status === 400 && notifyTest.json.error === 'not_configured', notifyTest.json);

  const orderList = await api('admin/orders?status=pending&claimed_only=1', { cookie: adminCookie });
  ok('后台可见待核销订单', orderList.json.orders.some(function (o) { return o.id === orderId; }), orderList.json.orders && orderList.json.orders.length);

  const confirm = await api('admin/orders/' + orderId + '/confirm', { method: 'POST', cookie: adminCookie });
  ok('管理员确认收款', confirm.json.order.status === 'paid', confirm.json.order);

  const confirm2 = await api('admin/orders/' + orderId + '/confirm', { method: 'POST', cookie: adminCookie });
  ok('重复确认幂等', confirm2.json.first_time === false, confirm2.json);

  // --- 解锁 ---
  const unlocked = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: panKey } });
  const det = unlocked.json.detail || {};
  ok('付费后解锁成功', unlocked.status === 200 && !!unlocked.json.detail, unlocked.json.raw ? String(unlocked.json.raw).slice(0, 300) : unlocked.json);
  ok('详细解读包含区块', Array.isArray(det.sections) && det.sections.length >= 5, det.sections && det.sections.map(function (s) { return s.type; }));
  ok('详细解读带合规声明', typeof det.notice === 'string' && det.notice.length > 0);
  ok('detail 含 pattern_key', /^day:\d\|time:\d$/.test(String(det.pattern_key)), det.pattern_key);
  ok('返回内容为合法 JSON（无 PHP 警告）', !unlocked.json.raw);

  const again = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: panKey } });
  ok('同一盘重复查看不再收费', again.status === 200 && again.json.unlock.reason === 'order', again.json.unlock);

  const againOrder = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'single_unlock', pan_key: panKey } });
  ok('已解锁不再重复下单', againOrder.json.already_unlocked === true, againOrder.json);

  // --- 会员 ---
  const memberOrder = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'member_monthly' } });
  ok('会员下单 2900 分', memberOrder.json.order.amount_cents === 2900, memberOrder.json.order);
  await api('admin/orders/' + memberOrder.json.order.id + '/confirm', { method: 'POST', cookie: adminCookie });
  const me = await api('me', { token: tokA, device: devA });
  ok('会员状态生效', me.json.user.is_member === true && me.json.user.member_days_left > 25, me.json.user);

  const newPan = await api('pan', { method: 'POST', token: tokA, device: devA, body: {
    iso: new Date(Date.now() - 3600000).toISOString(), question: '这个项目本月适合上线吗', category: 'auto', lang: 'zh',
  }});
  const memberUnlock = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: newPan.json.pan.pan_key } });
  ok('会员期内免订单解锁', memberUnlock.status === 200 && memberUnlock.json.unlock.reason === 'member', memberUnlock.json.unlock);

  // 会员叠加
  const before = me.json.user.member_expire_at;
  const mo2 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'member_monthly' } });
  await api('admin/orders/' + mo2.json.order.id + '/confirm', { method: 'POST', cookie: adminCookie });
  const me2 = await api('me', { token: tokA, device: devA });
  ok('会员有效期正确叠加', me2.json.user.member_expire_at > before, { before: before, after: me2.json.user.member_expire_at });

  // --- 云同步 ---
  const items = [];
  for (let i = 0; i < 3; i++) {
    items.push({ iso: new Date(Date.now() - i * 86400000).toISOString(), question: '同步测试 ' + i, details: '', category: 'general', lang: 'zh', local_id: 'local-' + i });
  }
  const sync1 = await api('pans/sync', { method: 'POST', token: tokA, device: devA, body: { items: items } });
  ok('云同步写入', sync1.json.created === 3, sync1.json);
  const sync2 = await api('pans/sync', { method: 'POST', token: tokA, device: devA, body: { items: items } });
  ok('云同步去重', sync2.json.created === 0, sync2.json);
  const pansList = await api('pans', { token: tokA, device: devA });
  ok('云同步可拉取', pansList.json.pans.length >= 4, pansList.json.pans && pansList.json.pans.length);

  const bPans = await api('pans', { token: tokB, device: devB });
  ok('用户只能读自己的盘例', bPans.json.pans.length === 0, bPans.json);

  // --- 打赏 ---
  const d1 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'donation', amount_cents: 500 } });
  ok('打赏 500 分', d1.json.order.amount_cents === 500, d1.json.order);
  const d2 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'donation', amount_cents: 1 } });
  ok('打赏下限校验（1 元=100 分）', d2.json.order.amount_cents === 100, d2.json.order);
  const d3 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'donation', amount_cents: 99999999 } });
  ok('打赏上限校验（200 元=20000 分）', d3.json.order.amount_cents === 20000, d3.json.order);

  await api('admin/orders/' + d1.json.order.id + '/confirm', { method: 'POST', cookie: adminCookie });
  const afterDonation = await api('me', { token: tokA, device: devA });
  ok('打赏不解锁会员', afterDonation.json.user.member_days_left > 25 && afterDonation.json.pans.length >= 4);

  // --- 分享 ---
  const share = await api('share', { method: 'POST', body: { pan_key: panKey, ref: 'test-ref' } });
  ok('分享回流记录', share.json.recorded === true, share.json);
  const adminShare = await api('admin/share', { cookie: adminCookie });
  ok('后台可看分享数据', adminShare.json.share.some(function (s) { return s.pan_key === panKey; }), adminShare.json.share);

  // --- 退款 ---
  const refundTarget = d3.json.order.id;
  const refunded = await api('admin/orders/' + refundTarget + '/refund', { method: 'POST', cookie: adminCookie });
  ok('退款状态正确', refunded.json.order.status === 'refunded', refunded.json.order);

  // --- 统计 ---
  const stats = await api('admin/stats', { cookie: adminCookie });
  ok('后台统计可用', stats.json.stats && stats.json.stats.orders_paid >= 3, stats.json.stats);

  // --- 定价后台修改 ---
  const priceSave = await api('admin/pricing/save', { method: 'POST', cookie: adminCookie, body: { pricing: { single_unlock_cny: 12.5 } } });
  ok('后台可改价', priceSave.json.pricing.single_unlock_cents === 1250, priceSave.json.pricing);
  const conf2 = await api('config');
  ok('改价立即对前端生效', conf2.json.config.pricing.single_unlock_cny === 12.5, conf2.json.config.pricing);
  await api('admin/pricing/save', { method: 'POST', cookie: adminCookie, body: { pricing: { single_unlock_cny: 9.9 } } });

  // --- 文案库后台覆盖 ---
  const saveInterp = await api('admin/interpretations/save', { method: 'POST', cookie: adminCookie, body: {
    pattern_key: 'day:' + unlocked.json.detail.pattern_key.split('|')[0].split(':')[1] + '|time:' + unlocked.json.detail.pattern_key.split('|')[1].split(':')[1],
    question_type: 'work', lang: 'zh', rich_text: '后台覆盖测试文案',
  }});
  ok('后台可覆盖文案', saveInterp.json.ok === true, saveInterp.json);
  const reUnlock = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: panKey } });
  const combo = ((reUnlock.json.detail || {}).sections || []).filter(function (s) { return s.type === 'combination'; })[0];
  ok('覆盖文案已生效', combo && combo.text === '后台覆盖测试文案', combo);
  await api('admin/interpretations/delete', { method: 'POST', cookie: adminCookie, body: {
    pattern_key: combo ? unlocked.json.detail.pattern_key.replace('day:', 'day:') : '', question_type: 'work', lang: 'zh',
  }});

  // --- 英文 ---
  const enPan = await api('pan', { method: 'POST', token: tokB, device: devB, body: {
    iso: new Date().toISOString(), question: 'Will this interview move to the next round?', category: 'auto', lang: 'en',
  }});
  ok('英文起盘', enPan.status === 200 && enPan.json.issue.category === 'work', enPan.json.issue);
  const enOrder = await api('orders', { method: 'POST', token: tokB, device: devB, body: { type: 'single_unlock', pan_key: enPan.json.pan.pan_key } });
  await api('admin/orders/' + enOrder.json.order.id + '/confirm', { method: 'POST', cookie: adminCookie });
  const enUnlock = await api('unlock', { method: 'POST', token: tokB, device: devB, body: { pan_key: enPan.json.pan.pan_key } });
  ok('英文付费流程可用', enUnlock.status === 200 && enUnlock.json.detail, enUnlock.json);
  const enBody = JSON.stringify(enUnlock.json.detail.sections);
  ok('英文解读为英文（无中文残留）', /[A-Za-z]/.test(enBody) && !/[\u4e00-\u9fff]/.test(enBody), enBody.slice(0, 200));
  ok('英文解读含组合断语', enUnlock.json.detail.has_deep === true, enUnlock.json.detail.has_deep);

  // --- 寻物类别 ---
  const lostPan = await api('pan', { method: 'POST', token: tokA, device: devA, body: {
    iso: new Date().toISOString(), question: '我的钥匙丢在哪里了', category: 'auto', lang: 'zh',
  }});
  ok('寻物自动识别', lostPan.json.issue.category === 'lost', lostPan.json.issue);
  const lostUnlock = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: lostPan.json.pan.pan_key } });
  const lostSection = lostUnlock.json.detail.sections.filter(function (s) { return s.type === 'lost'; })[0];
  ok('寻物解读包含找回倾向/方位/位置/顺序', !!(lostSection && lostSection.chance && lostSection.items.length === 3 && lostSection.actions.length === 3), lostSection);

  // --- 寻物 36 组合无空白 ---
  const lostCombos = await api('health');
  const lostLibProbe = await (async function () {
    const d = new Date(Date.now() - 7200000).toISOString();
    const p = await api('pan', { method: 'POST', token: tokA, device: devA, body: { iso: d, question: '钥匙找不到了', category: 'lost', lang: 'zh' } });
    return p.json.pan.pan_key;
  })();
  await api('admin/orders', { cookie: adminCookie });
  const lostOrder2 = await api('orders', { method: 'POST', token: tokA, device: devA, body: { type: 'single_unlock', pan_key: lostLibProbe } });
  await api('admin/orders/' + lostOrder2.json.order.id + '/confirm', { method: 'POST', cookie: adminCookie });
  const lost2 = await api('unlock', { method: 'POST', token: tokA, device: devA, body: { pan_key: lostLibProbe } });
  const lost2sec = (lost2.json.detail.sections || []).filter(function (s) { return s.type === 'lost'; })[0];
  ok('寻物组合含宫位组合名', !!lost2sec && lost2sec.combination.split('＋').length === 2, lost2sec && lost2sec.combination);
  ok('寻物组合含找回倾向', !!lost2sec && lost2sec.chance.length > 0);
  ok('寻物组合含位置描述', !!lost2sec && lost2sec.items[1].text.length > 0);

  // --- 时间校验 ---
  const badTime = await api('pan', { method: 'POST', token: tokA, device: devA, body: { iso: '1800-01-01T00:00:00Z', question: '测试时间范围' } });
  ok('超出年份范围被拒', badTime.status === 400, badTime.json);

  console.log('\n结果：' + pass + ' 通过 / ' + fail + ' 失败');
  if (failures.length) {
    console.log('失败项：');
    failures.forEach(function (f) { console.log('  - ' + f); });
  }
  process.exit(fail === 0 ? 0 : 1);
})();
