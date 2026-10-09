/* 问时 · 小六壬 — 盈利化客户端（免费层/付费层、下单、解锁、会员、打赏、分享回流）
 * 依赖 dist/app.js 暴露的全局：PALACES / BRANCHES / CATEGORIES / calendar / calculate / lastRenderContext
 * 不修改任何起盘逻辑；只在渲染完成后把付费内容替换为服务端下发的解读。 */
'use strict';

(function () {
  var LANG = (document.documentElement.lang || 'zh').toLowerCase().indexOf('en') === 0 ? 'en' : 'zh';
  var API = String(window.LFUN_API_BASE || 'api').replace(/\/+$/, '');
  var LS_DEVICE = 'lfun-device-id';
  var LS_TOKEN = 'lfun-token';

  var T = {
    zh: {
      freeNote: '以上为免费层：盘面、六神与简版断语。完整解读（起盘过程、分类专断、心理与情绪、解卦语、日时组合断语）属于付费内容。',
      unlockTitle: '解锁这一盘的完整解读',
      unlockLead: '付费解锁的是「增量解读」，起盘、简版结果与分享卡片始终免费。',
      feature1: '月、日、时三步起盘的完整说明与可复查路径',
      feature2: '按问题类别给出的发展过程、节奏、着眼点与应验信号',
      feature3: '日宫 × 时宫 组合断语（付费文案库，持续更新）',
      feature4: '寻物专断：找回倾向、方位象意、可能位置与搜索顺序',
      feature5: '心理与情绪提醒 + 结尾解卦语 + 可执行建议',
      perTime: '单次解锁 · 本盘永久可看',
      memberBtn: '开通会员 ¥{m}/月',
      memberNote: '会员期内所有盘免单次付费，并支持历史盘例云同步。',
      unlockBtn: '解锁详细解读 ¥{p}',
      unlockBtnShort: '解锁详细解读',
      loading: '正在连接解读服务…',
      offline: '暂时无法连接解读服务，请稍后重试。您仍可免费使用起盘与分享卡片。',
      payTitle: '确认支付',
      paySummaryType: '商品',
      paySummaryItem: '项目',
      paySummaryAmount: '应付金额',
      paySummaryNo: '订单号',
      paySummaryStatus: '状态',
      payConfirmBtn: '确认支付 ¥{p}',
      payCancel: '取消',
      confirmNote: '点击「确认支付」将创建订单。付费内容为传统文化娱乐解读，仅供娱乐，不构成任何决策、医疗、法律、财务或安全建议。',
      minorNote: '未成年人请在监护人陪同下使用并完成支付。',
      refundNote: '虚拟内容一经解锁不退款；重复扣款或支付失败全额退款。',
      payManualTitle: '完成付款',
      payManualLead: '请扫码（微信/支付宝）或点击 PayPal 链接完成付款，金额为 ¥{p}（PayPal 按美元结算）。付款完成后点击「我已完成付款」。',
      payNoQr: '管理员尚未配置收款方式。请联系站长获取付款方式，或稍后再试。',
      payViaPaypal: '用 PayPal 付款 {c} {a}',
      payInstructions: '付款说明',
      claimedBtn: '我已完成付款',
      claimedOk: '已记录。管理员核对到账后会立即开通。',
      claimedWaiting: '已记录，等待管理员核销。通常 {n} 分钟内开通。',
      claimedSupport: '若超过 {n} 分钟仍未开通，请联系客服：{c}',
      claimedNoSupport: '若超过 {n} 分钟仍未开通，请通过页面底部的联系方式联系站长。',
      claimedHowBack: '可以关闭本窗口，之后随时从页面右上角「我的」查看订单状态。',
      checking: '正在等待支付确认…（可关闭本窗口，稍后回来查看）',
      paidToast: '支付成功，详细解读已解锁。',
      memberPaidToast: '会员已开通，感谢支持。',
      donatePaidToast: '感谢支持，已收到你的心意。',
      retry: '刷新订单状态',
      close: '关闭',
      myTitle: '我的问时',
      myMember: '会员状态',
      myMemberOn: '有效期至 {d}（剩余 {n} 天）',
      myMemberOff: '未开通会员',
      myPans: '本地/云端盘例',
      myOrders: '订单记录',
      myUnlocks: '已解锁',
      tabOrders: '订单',
      tabPans: '盘例',
      syncBtn: '同步本机历史到云端',
      syncOk: '已同步 {n} 条新盘例。',
      syncNothing: '本机历史已全部同步。',
      memberBuy: '开通 / 续费会员 ¥{m}/月',
      noOrders: '还没有订单。',
      noPans: '还没有盘例记录。',
      donateTitle: '觉得准？请作者喝杯茶',
      donateLead: '随喜赞赏不附加任何权益，也不影响解读内容。',
      donateCustom: '自定义金额',
      donateBtn: '赞赏 ¥{p}',
      donateSend: '确认赞赏',
      referralTitle: '好友分享的盘例',
      referralLead: '这是一位朋友分享的问事盘。你也可以写下自己的问题，免费起一盘。',
      referralBtn: '我也起一盘',
      purchase: '购买须知',
      purchaseTitle: '购买须知与退款政策',
      purchaseBody: '1. 本站为传统民俗文化娱乐体验产品，所有解读仅供娱乐，不构成医疗、法律、财务或安全建议，也不保证任何结果发生。\n2. 单次解锁 ¥{p}，解锁后该盘面的详细解读可永久反复查看。\n3. 会员 ¥{m}/月，会员期内所有盘面的详细解读免费，并可使用历史盘例云同步。\n4. 当前采用人工收款：扫码（微信/支付宝）或经 PayPal 链接付款后请点击「我已完成付款」，管理员核对到账后即刻开通，通常 {n} 分钟内完成。订单状态可随时在「我的」中查看。\n5. 随喜赞赏为自愿赠与，不附加任何权益，也不解锁额外内容。\n6. 虚拟内容一经解锁不退款；若发生重复扣款或支付失败，请凭订单号联系站长，全额退款。\n7. 未成年人请在监护人陪同下使用并完成支付。',
      support: '客服联系：{c}',
      statusPending: '待支付',
      statusPaid: '已支付',
      statusFailed: '已关闭',
      statusRefunded: '已退款',
      typeSingle: '单次详细解读',
      typeMember: '会员（包月）',
      typeDonation: '随喜赞赏',
      errGeneric: '操作失败，请稍后重试。',
      loginNeeded: '请先刷新页面重新建立会话。'
    },
    en: {
      freeNote: 'This is the free layer: the pan, the six palaces and a one-line reading. The full interpretation (casting steps, category reading, emotional pattern, closing reading and day-hour combination) is paid content.',
      unlockTitle: 'Unlock the full reading for this pan',
      unlockLead: 'You pay for the incremental interpretation only. Casting, the short reading and the share card stay free.',
      feature1: 'The full month–day–hour casting path, step by step',
      feature2: 'Category-specific process, timing, focus and confirming signals',
      feature3: 'Day-palace × hour-palace combination readings (an updated library)',
      feature4: 'Lost-item reading: chance of recovery, direction, likely place, search order',
      feature5: 'Emotional pattern, closing reading and practical next steps',
      perTime: 'One-off unlock · kept for this pan forever',
      memberBtn: 'Join membership ¥{m}/month',
      memberNote: 'Members unlock every pan and can sync their history across devices.',
      unlockBtn: 'Unlock full reading ¥{p}',
      unlockBtnShort: 'Unlock full reading',
      loading: 'Connecting to the reading service…',
      offline: 'The reading service is unavailable right now. You can still use casting and the share card for free.',
      payTitle: 'Confirm payment',
      paySummaryType: 'Product',
      paySummaryItem: 'Item',
      paySummaryAmount: 'Amount',
      paySummaryNo: 'Order number',
      paySummaryStatus: 'Status',
      payConfirmBtn: 'Confirm payment ¥{p}',
      payCancel: 'Cancel',
      confirmNote: 'Confirming creates the order. Paid content is a traditional-culture entertainment reading only; it is not advice of any kind.',
      minorNote: 'Minors must use this site and complete payment with a guardian present.',
      refundNote: 'Unlocked virtual content is non-refundable. Duplicate charges or failed payments are refunded in full.',
      payManualTitle: 'Complete the payment',
      payManualLead: 'Scan the code (WeChat/Alipay) or tap the PayPal link to pay ¥{p} (PayPal is settled in USD). When you have paid, tap "I have paid".',
      payNoQr: 'No payment method has been configured yet. Please contact the site owner, or try again later.',
      payViaPaypal: 'Pay with PayPal {c} {a}',
      payInstructions: 'Payment notes',
      claimedBtn: 'I have paid',
      claimedOk: 'Recorded. The owner will confirm it shortly.',
      claimedWaiting: 'Recorded — waiting for the owner to confirm. Usually within {n} minutes.',
      claimedSupport: 'If it is still locked after {n} minutes, contact support: {c}',
      claimedNoSupport: 'If it is still locked after {n} minutes, contact the owner via the link in the footer.',
      claimedHowBack: 'You can close this window and check the order any time from "My readings".',
      checking: 'Waiting for payment confirmation… (you may close this and check back later)',
      paidToast: 'Payment confirmed — the full reading is unlocked.',
      memberPaidToast: 'Membership activated. Thank you.',
      donatePaidToast: 'Thank you for the support.',
      retry: 'Refresh order status',
      close: 'Close',
      myTitle: 'My readings',
      myMember: 'Membership',
      myMemberOn: 'Valid until {d} ({n} days left)',
      myMemberOff: 'No active membership',
      myPans: 'Readings',
      myOrders: 'Orders',
      myUnlocks: 'Unlocked',
      tabOrders: 'Orders',
      tabPans: 'Readings',
      syncBtn: 'Sync this device history',
      syncOk: 'Synced {n} new readings.',
      syncNothing: 'Everything on this device is already synced.',
      memberBuy: 'Start / renew membership ¥{m}/month',
      noOrders: 'No orders yet.',
      noPans: 'No readings yet.',
      donateTitle: 'Was it useful? Buy the author a tea',
      donateLead: 'A voluntary tip. It unlocks nothing and never changes the reading.',
      donateCustom: 'Custom amount',
      donateBtn: 'Tip ¥{p}',
      donateSend: 'Confirm tip',
      referralTitle: 'A reading shared by a friend',
      referralLead: 'Someone shared their pan. You can ask your own question and cast for free.',
      referralBtn: 'Cast my own',
      purchase: 'Purchase notes',
      purchaseTitle: 'Purchase notes and refund policy',
      purchaseBody: '1. This is a traditional-culture entertainment product. Every reading is for entertainment only and is not medical, legal, financial or safety advice.\n2. A one-off unlock costs ¥{p} and stays available for that pan permanently.\n3. Membership is ¥{m}/month: every pan is unlocked while it is active, with history sync.\n4. Payment is collected manually at present: scan the code (WeChat/Alipay) or pay via the PayPal link, then tap "I have paid". The owner confirms and unlocks, usually within {n} minutes. Order status is always available under "My readings".\n5. Tips are voluntary gifts. They unlock nothing.\n6. Unlocked virtual content is non-refundable. Duplicate charges or failed payments are refunded in full — contact the owner with your order number.\n7. Minors must be accompanied by a guardian.',
      support: 'Support: {c}',
      statusPending: 'Pending',
      statusPaid: 'Paid',
      statusFailed: 'Closed',
      statusRefunded: 'Refunded',
      typeSingle: 'Single full reading',
      typeMember: 'Membership (monthly)',
      typeDonation: 'Tip',
      errGeneric: 'Something went wrong. Please try again.',
      loginNeeded: 'Please refresh the page to re-establish the session.'
    }
  };

  function t(key) {
    var vars = arguments.length > 1 ? arguments[1] : null;
    var dict = T[LANG] || T.zh;
    var s = dict[key] !== undefined ? dict[key] : (T.zh[key] !== undefined ? T.zh[key] : key);
    if (vars) {
      Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(String(vars[k])); });
    }
    return s;
  }

  var S = { config: null, token: null, device: null, current: null, order: null, poll: null, pollDelay: 2500, busy: false };

  function esc(v) {
    return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function money(v) {
    var n = Number(v);
    if (!isFinite(n)) { return '¥' + v; }
    return '¥' + (Math.abs(n - Math.round(n)) < 0.005 ? String(Math.round(n)) : n.toFixed(2));
  }

  function api(path, opts) {
    opts = opts || {};
    var headers = { 'Content-Type': 'application/json' };
    if (S.token) { headers['X-Auth-Token'] = S.token; }
    if (S.device) { headers['X-Device-Id'] = S.device; }
    return fetch(API + '/' + path, {
      method: opts.method || 'GET',
      headers: headers,
      credentials: 'same-origin',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (e) { data = { ok: false, error: 'bad_response' }; }
        return { status: res.status, data: data };
      });
    });
  }

  function el(html) {
    var d = document.createElement('div');
    d.innerHTML = html;
    return d.firstElementChild;
  }

  function toast(msg) {
    var node = document.getElementById('pwToast');
    if (!node) {
      node = el('<p id="pwToast" class="pw-toast" role="status" aria-live="polite"></p>');
      document.body.appendChild(node);
    }
    node.textContent = msg;
    node.classList.add('show');
    clearTimeout(node.__timer);
    node.__timer = setTimeout(function () { node.classList.remove('show'); }, 4200);
  }

  function dialog(id, html) {
    var node = document.getElementById(id);
    if (node) { node.remove(); }
    node = el('<dialog id="' + id + '" class="pw-dialog"><button class="pw-close" aria-label="' + esc(t('close')) + '">×</button>' + html + '</dialog>');
    document.body.appendChild(node);
    node.querySelector('.pw-close').addEventListener('click', function () { node.close(); });
    node.addEventListener('click', function (e) { if (e.target === node) { node.close(); } });
    node.addEventListener('close', function () {
      if (id === 'pwPayDialog' && S.poll) { clearTimeout(S.poll); S.poll = null; }
    });
    return node;
  }

  /* ------------------------- 会话 ------------------------- */

  function boot() {
    S.device = null;
    try { S.device = window.localStorage.getItem(LS_DEVICE); } catch (e) { S.device = null; }
    if (!S.device) {
      S.device = uuid4();
      try { window.localStorage.setItem(LS_DEVICE, S.device); } catch (e) { /* ignore */ }
    }
    try { S.token = window.localStorage.getItem(LS_TOKEN) || null; } catch (e) { S.token = null; }

    return api('session', { method: 'POST', body: { device_id: S.device } }).then(function (res) {
      if (res.data && res.data.auth) {
        S.token = res.data.auth.token;
        S.device = res.data.auth.device_id || S.device;
        try {
          window.localStorage.setItem(LS_TOKEN, S.token);
          window.localStorage.setItem(LS_DEVICE, S.device);
        } catch (e) { /* ignore */ }
      }
      return api('config');
    }).then(function (res) {
      S.config = (res.data && res.data.config) || null;
      return S.config;
    });
  }

  function uuid4() {
    if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /* ------------------------- 免费层裁剪 ------------------------- */

  function keepNodes(result) {
    var selectors = LANG === 'en'
      ? ['.en-result-head', '.asked', '.en-card', '.caution', '.en-share']
      : ['.grand-compass', '.result-top', '.asked', '.overview', '.caution', 'details.trace'];
    var kept = [];
    selectors.forEach(function (sel) {
      Array.prototype.forEach.call(result.querySelectorAll(sel), function (n) { kept.push(n); });
    });
    return kept;
  }

  function trimToFreeLayer() {
    var result = document.getElementById('result');
    if (!result || result.hidden) { return; }
    var kept = keepNodes(result);
    if (!kept.length) { return; }
    var fragment = document.createDocumentFragment();
    kept.forEach(function (n) { fragment.appendChild(n); });
    result.replaceChildren(fragment);
    result.classList.add('pw-trimmed');
  }

  /* 中文页用 lastRenderContext、英文页用 lastReading，都是顶层 let（不在 window 上），
   * 必须用裸标识符读取；这里统一取到「最近一次渲染的上下文」。 */
  function lastCtx() {
    try {
      if (typeof lastRenderContext !== 'undefined' && lastRenderContext) { return lastRenderContext; }
    } catch (e) { /* ignore */ }
    try {
      if (typeof lastReading !== 'undefined' && lastReading) { return lastReading; }
    } catch (e) { /* ignore */ }
    return window.lastRenderContext || window.lastReading || null;
  }

  function installRenderHook() {
    if (typeof window.render !== 'function' || window.render.__lfunWrapped) { return; }
    var original = window.render;
    var wrapped = function (record) {
      original(record);
      trimToFreeLayer();
      attachPaywall();
    };
    wrapped.__lfunWrapped = true;
    window.render = wrapped;
  }

  /* ------------------------- 付费层入口 ------------------------- */

  function attachPaywall() {
    var result = document.getElementById('result');
    if (!result || result.hidden) { return; }
    var old = result.querySelector('.pw-card');
    if (old) { old.remove(); }

    var ctx = lastCtx();
    if (!ctx || !ctx.record) { return; }
    S.current = { record: ctx.record, panKey: null, unlocked: false, detail: null };

    var card = el('<section class="pw-card" id="pwCard"></section>');
    card.innerHTML = cardLoadingHtml();
    result.appendChild(card);

    registerPan(ctx.record).then(function (res) {
      if (!res || !res.data || !res.data.pan) {
        card.innerHTML = cardErrorHtml(res && res.data ? res.data.message : '');
        return;
      }
      S.current.panKey = res.data.pan.pan_key;
      S.current.brief = res.data.brief;
      S.current.unlocked = !!res.data.unlocked;
      S.current.issue = res.data.issue;
      S.current.subtype = res.data.subtype;
      // 免费层的「简版断语」用服务端下发的分类断语；离线时保留六宫简版回退。
      if (res.data.brief) {
        var overview = result.querySelector('.overview');
        if (overview) {
          overview.innerHTML = '<strong>总览：</strong>' + esc(res.data.brief)
            + ' 这是对当前处境的观察角度，不是对未来结果的保证。';
        }
      }
      if (S.current.unlocked) {
        loadDetail(card);
      } else {
        card.innerHTML = cardLockedHtml();
        bindCard(card);
      }
    });
  }

  function registerPan(record) {
    var cal = null;
    try { cal = window.calendar(new Date(record.iso)); } catch (e) { cal = null; }
    var body = {
      iso: record.iso,
      question: record.question,
      details: record.details || '',
      category: record.category,
      lang: LANG
    };
    if (cal) { body.lunar_month = cal.month; body.lunar_day = cal.day; body.lunar_leap = !!cal.leap; }
    return api('pan', { method: 'POST', body: body });
  }

  function cardLoadingHtml() {
    return '<p class="pw-eyebrow">' + esc(LANG === 'en' ? 'FULL READING' : '完整解读') + '</p>'
      + '<div class="pw-status"><span class="pw-spinner"></span><span>' + esc(t('loading')) + '</span></div>'
      + '<p class="pw-fine">' + esc(t('freeNote')) + '</p>';
  }

  function cardErrorHtml(message) {
    return '<p class="pw-eyebrow">' + esc(LANG === 'en' ? 'FULL READING' : '完整解读') + '</p>'
      + '<h3 class="pw-title">' + esc(t('unlockTitle')) + '</h3>'
      + '<p class="pw-lead">' + esc(t('offline')) + (message ? ' <small>(' + esc(message) + ')</small>' : '') + '</p>'
      + '<p class="pw-fine">' + esc(t('freeNote')) + '</p>';
  }

  function cardLockedHtml() {
    var cfg = S.config || { pricing: {} };
    var p = cfg.pricing || {};
    var price = money(p.single_unlock);
    var member = money(p.member_monthly);
    var feat = ['feature1', 'feature2', 'feature3', 'feature4', 'feature5'].map(function (k) {
      return '<li>' + esc(t(k)) + '</li>';
    }).join('');
    var buttons = '<div class="pw-actions">'
      + '<button class="pw-btn" data-act="unlock">' + esc(t('unlockBtn', { p: price })) + '</button>';
    if (cfg.features && cfg.features.membership) {
      buttons += '<button class="pw-btn secondary" data-act="member">' + esc(t('memberBtn', { m: member })) + '</button>';
    }
    buttons += '<button class="pw-btn ghost" data-act="purchase">' + esc(t('purchase')) + '</button></div>';
    var memberNote = (cfg.features && cfg.features.membership) ? '<p class="pw-fine">' + esc(t('memberNote')) + '</p>' : '';
    return '<p class="pw-eyebrow">' + esc(LANG === 'en' ? 'FULL READING' : '完整解读') + '</p>'
      + '<h3 class="pw-title">' + esc(t('unlockTitle')) + '</h3>'
      + '<p class="pw-lead">' + esc(t('unlockLead')) + '</p>'
      + '<ul class="pw-list">' + feat + '</ul>'
      + '<div class="pw-price-row"><span class="pw-price">' + esc(price) + '</span>'
      + '<span class="pw-price-note">' + esc(t('perTime')) + '</span></div>'
      + buttons
      + memberNote
      + '<p class="pw-fine">' + esc(t('confirmNote')) + ' ' + esc(t('minorNote')) + ' ' + esc(t('refundNote')) + '</p>'
      + '<p class="pw-fine">' + esc(t('freeNote')) + '</p>';
  }

  function bindCard(card) {
    card.querySelectorAll('[data-act]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var act = btn.getAttribute('data-act');
        if (act === 'unlock') { openPayConfirm('single_unlock'); }
        else if (act === 'member') { openPayConfirm('member_monthly'); }
        else if (act === 'purchase') { showPurchase(); }
      });
    });
  }

  /* ------------------------- 支付流程 ------------------------- */

  var ORDER_TYPE_LABEL = { single_unlock: 'typeSingle', member_monthly: 'typeMember', donation: 'typeDonation' };

  function openPayConfirm(type, amountCents) {
    if (!S.config) { toast(t('errGeneric')); return; }
    if (type !== 'donation' && !S.current) { toast(t('errLoginNeeded')); return; }
    var cfg = S.config;
    var p = cfg.pricing || {};
    var priceCents = type === 'single_unlock' ? Math.round(Number(p.single_unlock) * 100)
      : type === 'member_monthly' ? Math.round(Number(p.member_monthly) * 100)
        : amountCents;
    var price = money(priceCents / 100);
    var item = type === 'single_unlock'
      ? (S.current && S.current.issue ? S.current.issue.label : t('typeSingle')) + (S.current && S.current.subtype && S.current.subtype.label ? ' · ' + S.current.subtype.label : '')
      : t(ORDER_TYPE_LABEL[type] || 'typeSingle');

    var html = '<h3 class="pw-title">' + esc(t('payTitle')) + '</h3>'
      + '<div class="pw-summary">'
      + '<div><span>' + esc(t('paySummaryType')) + '</span><span>' + esc(t(ORDER_TYPE_LABEL[type] || 'typeSingle')) + '</span></div>'
      + '<div><span>' + esc(t('paySummaryItem')) + '</span><span>' + esc(item) + '</span></div>'
      + '<div><span>' + esc(t('paySummaryAmount')) + '</span><span>' + esc(price) + '</span></div>'
      + '</div>'
      + '<div class="pw-notice"><b>' + esc(LANG === 'en' ? 'Please note' : '请注意') + '</b><br>' + esc(t('confirmNote')) + '<br>' + esc(t('minorNote')) + '<br>' + esc(t('refundNote')) + '</div>'
      + '<div class="pw-actions" style="margin-top:18px">'
      + '<button class="pw-btn" id="pwPayGo">' + esc(t('payConfirmBtn', { p: price })) + '</button>'
      + '<button class="pw-btn ghost" id="pwPayCancel">' + esc(t('payCancel')) + '</button>'
      + '</div>'
      + '<p class="pw-fine"><button class="pw-btn ghost small" id="pwPurchaseLink" type="button">' + esc(t('purchase')) + '</button></p>';

    var dlg = dialog('pwPayDialog', html);
    dlg.showModal();
    dlg.querySelector('#pwPayCancel').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('#pwPurchaseLink').addEventListener('click', showPurchase);
    dlg.querySelector('#pwPayGo').addEventListener('click', function () {
      var btn = dlg.querySelector('#pwPayGo');
      btn.disabled = true;
      createOrder(type, amountCents).then(function (res) {
        if (!res.ok) {
          btn.disabled = false;
          toast(res.message || t('errGeneric'));
          return;
        }
        showPayPanel(dlg, res);
      });
    });
  }

  function createOrder(type, amountCents) {
    var body = { type: type, lang: LANG };
    if (type === 'single_unlock') { body.pan_key = S.current.panKey; }
    if (type === 'donation' && amountCents) { body.amount_cents = amountCents; }
    return api('orders', { method: 'POST', body: body }).then(function (res) {
      if (res.data && res.data.already_unlocked) {
        return { ok: true, already: true };
      }
      if (!res.data || !res.data.ok) {
        return { ok: false, message: (res.data && res.data.message) || t('errGeneric') };
      }
      S.order = { id: res.data.order.id, type: res.data.order.type, amount_cny: res.data.order.amount_cny };
      return { ok: true, order: res.data.order, pay: res.data.pay, notice: res.data.notice };
    });
  }

  function showPayPanel(dlg, res) {
    if (res.already) {
      dlg.close();
      if (S.current) { loadDetail(document.getElementById('pwCard')); }
      return;
    }
    var order = res.order, pay = (res.pay && res.pay.params) || {};
    var price = money(order.amount_cny);
    var qr = '';
    if (pay.wx_qr) {
      qr += '<figure><img src="' + esc(pay.wx_qr) + '" alt="WeChat QR"><figcaption>' + esc(LANG === 'en' ? 'WeChat' : '微信') + '</figcaption></figure>';
    }
    if (pay.alipay_qr) {
      qr += '<figure><img src="' + esc(pay.alipay_qr) + '" alt="Alipay QR"><figcaption>' + esc(LANG === 'en' ? 'Alipay' : '支付宝') + '</figcaption></figure>';
    }
    if (pay.paypal && pay.paypal.enabled && pay.paypal.pay_url) {
      qr += '<figure class="pw-paypal"><a href="' + esc(pay.paypal.pay_url) + '" target="_blank" rel="noopener">'
        + '<span class="pw-paypal-btn">PayPal</span>'
        + '<figcaption>' + esc(t('payViaPaypal', { c: pay.paypal.currency, a: pay.paypal.amount })) + '</figcaption></a></figure>';
    }
    if (pay.code_url) {
      qr += '<figure><img src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' + encodeURIComponent(pay.code_url) + '" alt="QR"><figcaption>' + esc(pay.type === 'wxpay_native' ? 'WeChat' : 'Alipay') + '</figcaption></figure>';
    }
    var html = '<h3 class="pw-title">' + esc(pay.type === 'manual' ? t('payManualTitle') : t('payTitle')) + '</h3>'
      + '<p class="pw-lead">' + esc(pay.type && pay.type !== 'manual'
        ? (LANG === 'en' ? 'Scan the QR code below with your payment app.' : '请使用对应的支付应用扫描下方二维码完成付款。')
        : t('payManualLead', { p: price })) + '</p>'
      + (qr ? '<div class="pw-qr">' + qr + '</div>' : '<div class="pw-notice">' + esc(t('payNoQr')) + '</div>')
      + (pay.instructions ? '<p class="pw-fine"><b>' + esc(t('payInstructions')) + '</b>：' + esc(pay.instructions) + '</p>' : '')
      + '<div class="pw-summary"><div><span>' + esc(t('paySummaryAmount')) + '</span><span>' + esc(price) + '</span></div>'
      + '<div><span>' + esc(t('paySummaryNo')) + '</span><span>' + esc(order.out_trade_no) + '</span></div>'
      + '<div><span>' + esc(t('paySummaryStatus')) + '</span><span id="pwOrderStatus">' + esc(t('statusPending')) + '</span></div></div>'
      + '<div class="pw-status"><span class="pw-spinner"></span><span>' + esc(t('checking')) + '</span></div>'
      + '<div class="pw-actions" style="margin-top:16px">'
      + '<button class="pw-btn" id="pwClaimed">' + esc(t('claimedBtn')) + '</button>'
      + '<button class="pw-btn secondary" id="pwRefresh">' + esc(t('retry')) + '</button>'
      + '<button class="pw-btn ghost" id="pwLater">' + esc(t('close')) + '</button>'
      + '</div>'
      + '<div class="pw-notice">' + esc(t('confirmNote')) + '<br>' + esc(t('minorNote')) + '</div>';

    dlg.querySelector('.pw-close').insertAdjacentHTML('beforebegin', '');
    dlg.innerHTML = '<button class="pw-close" aria-label="' + esc(t('close')) + '">×</button>' + html;
    dlg.querySelector('.pw-close').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('#pwLater').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('#pwRefresh').addEventListener('click', function () { pollOrder(order.id, dlg, true); });
    dlg.querySelector('#pwClaimed').addEventListener('click', function () {
      var btn = dlg.querySelector('#pwClaimed');
      btn.disabled = true;
      api('orders/' + order.id + '/claim', { method: 'POST' }).then(function () {
        btn.disabled = false;
        toast(t('claimedOk'));
        showClaimedState(dlg);
        pollOrder(order.id, dlg, true);
      });
    });
    pollOrder(order.id, dlg, false);
  }

  /** 用户点完「我已付款」之后，明确告诉他等待多久、超时找谁，避免干等。 */
  function showClaimedState(dlg) {
    var cfg = S.config || {};
    var wait = (cfg.support && cfg.support.manual_wait_minutes) || 30;
    var contact = (cfg.support && cfg.support.contact) || '';
    var box = dlg.querySelector('#pwClaimedBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'pwClaimedBox';
      box.className = 'pw-notice';
      var actions = dlg.querySelector('#pwClaimed');
      if (actions && actions.parentNode) { actions.parentNode.insertAdjacentElement('afterend', box); }
      else { dlg.appendChild(box); }
    }
    box.innerHTML = '<b>' + esc(t('claimedWaiting', { n: wait })) + '</b><br>'
      + esc(contact ? t('claimedSupport', { n: wait, c: contact }) : t('claimedNoSupport', { n: wait })) + '<br>'
      + esc(t('claimedHowBack'));
  }

  function pollOrder(orderId, dlg, immediate) {
    if (S.poll) { clearTimeout(S.poll); S.poll = null; }
    var run = function () {
      api('orders/' + orderId).then(function (res) {
        var order = res.data && res.data.order;
        if (order) {
          var label = document.getElementById('pwOrderStatus');
          if (label) { label.textContent = t(order.status === 'paid' ? 'statusPaid' : order.status === 'refunded' ? 'statusRefunded' : order.status === 'failed' ? 'statusFailed' : 'statusPending'); }
          if (order.status === 'paid') {
            onOrderPaid(order);
            if (dlg && dlg.open) { dlg.close(); }
            return;
          }
        }
        S.pollDelay = Math.min(S.pollDelay + 1500, 12000);
        S.poll = setTimeout(run, S.pollDelay);
      }).catch(function () {
        S.pollDelay = Math.min(S.pollDelay + 1500, 12000);
        S.poll = setTimeout(run, S.pollDelay);
      });
    };
    S.pollDelay = immediate ? 300 : 2500;
    if (immediate) { run(); } else { S.poll = setTimeout(run, S.pollDelay); }
  }

  function onOrderPaid(order) {
    if (!order) { return; }
    if (order.type === 'donation') {
      toast(t('donatePaidToast'));
      return;
    }
    if (order.type === 'member_monthly') {
      toast(t('memberPaidToast'));
      if (S.current && S.current.panKey) { refreshUnlockState(); }
      else if (S.current) { loadDetail(document.getElementById('pwCard')); }
      return;
    }
    toast(t('paidToast'));
    var card = document.getElementById('pwCard');
    if (card) { loadDetail(card); }
  }

  function refreshUnlockState() {
    if (!S.current || !S.current.panKey) { return; }
    var card = document.getElementById('pwCard');
    if (card) { loadDetail(card); }
  }

  /* ------------------------- 详细解读渲染 ------------------------- */

  function loadDetail(card) {
    if (!S.current || !S.current.panKey) { return; }
    if (card) { card.innerHTML = cardLoadingHtml(); }
    api('unlock', { method: 'POST', body: { pan_key: S.current.panKey, lang: LANG } }).then(function (res) {
      if (res.data && res.data.ok && res.data.detail) {
        S.current.unlocked = true;
        S.current.detail = res.data.detail;
        renderDetail(card, res.data.detail);
        return;
      }
      if (res.status === 402) {
        if (card) {
          card.innerHTML = cardLockedHtml();
          bindCard(card);
        }
        return;
      }
      if (card) { card.innerHTML = cardErrorHtml(res.data && res.data.message); }
    }).catch(function () {
      if (card) { card.innerHTML = cardErrorHtml(''); }
    });
  }

  function sectionMap(sections) {
    var map = {};
    sections.forEach(function (s) { (map[s.type] = map[s.type] || []).push(s); });
    return map;
  }

  function renderDetail(card, detail) {
    var host = document.querySelector('.pw-detail');
    if (host) { host.remove(); }
    var html = buildDetailHtml(detail);
    var node = el(html);
    var result = document.getElementById('result');
    if (card && card.parentNode === result) {
      result.insertBefore(node, card);
    } else if (result) {
      result.appendChild(node);
    }
    if (card) { card.remove(); }
    attachDonate(node);
    if (window.LFUN_ON_DETAIL) { try { window.LFUN_ON_DETAIL(detail, node); } catch (e) { /* noop */ } }
  }

  function buildDetailHtml(detail) {
    var map = sectionMap(detail.sections || []);
    var out = ['<div class="pw-detail">'];

    var steps = (map.steps && map.steps[0] && map.steps[0].items) || [];
    var path = map.path && map.path[0];
    if (steps.length || path) {
      out.push('<div class="reading-steps">');
      steps.forEach(function (s, i) {
        out.push('<article class="reading-step"><span class="step-no">' + (i + 1) + '</span><div><h3>' + esc(s.title) + '</h3><p>' + esc(s.text) + '</p></div></article>');
      });
      if (path) {
        var line = (path.line || []).map(function (n, i) {
          var cls = i === (path.line.length - 1) ? ' class="current"' : '';
          return '<i' + cls + '>' + esc(n) + '<small>' + esc(i === 0 ? (LANG === 'en' ? 'month' : '月宫') : i === 1 ? (LANG === 'en' ? 'day' : '日宫') : (LANG === 'en' ? 'hour' : '时宫')) + '</small></i>';
        }).join('<b>→</b>');
        out.push('<article class="reading-step path-reading"><span class="step-no">' + (steps.length + 1) + '</span><div><h3>' + esc(path.title) + '</h3><p>' + esc(path.text) + '</p><div class="path-line">' + line + '</div></div></article>');
      }
      out.push('</div>');
    }

    if (map.combination && map.combination[0]) {
      out.push('<section class="pw-combination"><p class="pw-eyebrow">' + esc(map.combination[0].title) + '</p><p>' + esc(map.combination[0].text) + '</p></section>');
    }

    if (map.lost && map.lost[0]) {
      var l = map.lost[0];
      out.push('<section class="lost-reading"><div class="lost-head"><div><span>' + esc(l.title) + '</span><h3>' + esc(l.combination) + '</h3></div><strong>' + esc(l.chance) + '</strong></div>');
      out.push('<div class="lost-grid">' + (l.items || []).map(function (it) {
        return '<article><small>' + esc(it.label) + '</small><p>' + esc(it.text) + '</p></article>';
      }).join('') + '</div>');
      out.push('<div class="lost-actions"><h4>' + esc(LANG === 'en' ? 'Search order' : '建议搜索顺序') + '</h4><ol>' + (l.actions || []).map(function (a) {
        return '<li>' + esc(a) + '</li>';
      }).join('') + '</ol></div>');
      if (l.note) { out.push('<p class="lost-note">' + esc(l.note) + '</p>'); }
      out.push('</section>');
      if (l.deep) {
        out.push('<section class="pw-combination"><p class="pw-eyebrow">' + esc(l.combination) + '</p><p>' + esc(l.deep) + '</p></section>');
      }
    }

    if (map.verdict && map.verdict[0]) {
      out.push('<section class="topic-reading"><div class="topic-verdict" style="margin-top:0"><small>' + esc(map.verdict[0].label) + '</small><p>' + esc(map.verdict[0].text) + '。</p></div>');
      if (map.grid && map.grid[0]) {
        out.push('<div class="topic-grid">' + (map.grid[0].items || []).map(function (it) {
          return '<article><small>' + esc(it.label) + '</small><p>' + esc(it.text) + '</p></article>';
        }).join('') + '</div>');
      }
      out.push('<p class="topic-note">' + esc(detail.notice || '') + '</p></section>');
    }

    if (map.palace_focus && map.palace_focus[0]) {
      out.push('<div class="reading-steps"><article class="reading-step"><span class="step-no">' + (steps.length + (path ? 2 : 1)) + '</span><div><h3>' + esc(map.palace_focus[0].title) + '</h3><p>' + (map.palace_focus[0].items || []).map(function (it) {
        return '<b>' + esc(it.label) + '：</b>' + esc(it.text);
      }).join('<br>') + '</p></div></article></div>');
    }

    if (map.actions && map.actions[0]) {
      out.push('<section class="advice-box"><h3>' + esc(map.actions[0].title) + '</h3><ol>' + (map.actions[0].items || []).map(function (a) {
        return '<li>' + esc(a) + '</li>';
      }).join('') + '</ol></section>');
    }

    if (map.emotion && map.emotion[0]) {
      var e = map.emotion[0];
      out.push('<div class="emotion-reading"><div class="emotion-title"><span>' + esc(e.title) + '</span></div><p>' + esc(e.text) + '</p><dl>' + (e.items || []).map(function (it) {
        return '<div><dt>' + esc(it.label) + '</dt><dd>' + esc(it.text) + '</dd></div>';
      }).join('') + '</dl></div>');
    }

    if (map.closing && map.closing[0]) {
      out.push('<blockquote class="closing-reading"><span>' + esc(map.closing[0].label) + '</span><p>' + esc(map.closing[0].text) + '</p></blockquote>');
    }

    if (map.grid && !map.verdict) {
      out.push('<section class="topic-reading"><div class="topic-grid">' + (map.grid[0].items || []).map(function (it) {
        return '<article><small>' + esc(it.label) + '</small><p>' + esc(it.text) + '</p></article>';
      }).join('') + '</div><p class="topic-note">' + esc(detail.notice || '') + '</p></section>');
    }

    if (S.config && S.config.features && S.config.features.donation) {
      out.push(donateHtml());
    }

    out.push('</div>');
    return out.join('');
  }

  function donateHtml() {
    var cfg = S.config || {};
    var p = cfg.pricing || {};
    var presets = p.donation_presets || [2, 5, 10];
    var buttons = presets.map(function (v) {
      return '<button class="pw-btn secondary small" data-tip="' + Math.round(Number(v) * 100) + '">' + esc(money(v)) + '</button>';
    }).join('');
    return '<section class="pw-donate"><h3>' + esc(t('donateTitle')) + '</h3><p>' + esc(t('donateLead')) + '</p>'
      + '<div class="pw-donate-amounts">' + buttons
      + '<input type="number" id="pwTipCustom" min="' + esc(p.donation_min) + '" max="' + esc(p.donation_max) + '" step="0.01" placeholder="' + esc(t('donateCustom')) + '">'
      + '</div></section>';
  }

  function attachDonate(root) {
    root.querySelectorAll('[data-tip]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openPayConfirm('donation', Number(btn.getAttribute('data-tip')));
      });
    });
    var custom = root.querySelector('#pwTipCustom');
    if (custom) {
      custom.addEventListener('change', function () {
        var v = Number(custom.value);
        var p = (S.config && S.config.pricing) || {};
        if (!isFinite(v) || v < Number(p.donation_min || 1) || v > Number(p.donation_max || 200)) {
          toast(LANG === 'en' ? 'Please enter an amount between ¥' + p.donation_min + ' and ¥' + p.donation_max + '.' : '请输入 ¥' + p.donation_min + ' 至 ¥' + p.donation_max + ' 之间的金额。');
          return;
        }
        openPayConfirm('donation', Math.round(v * 100));
      });
    }
  }

  /* ------------------------- 我的页面 ------------------------- */

  function openMy() {
    var dlg = dialog('pwMeDialog', '<h3 class="pw-title">' + esc(t('myTitle')) + '</h3><p class="pw-lead">' + esc(t('loading')) + '</p>');
    dlg.showModal();
    api('me').then(function (res) {
      var data = res.data || {};
      var user = data.user || {};
      var cfg = S.config || { features: {}, pricing: {} };
      var memberLine = user.is_member
        ? t('myMemberOn', { d: String(user.member_expire_at || '').slice(0, 10), n: user.member_days_left })
        : t('myMemberOff');
      var p = cfg.pricing || {};
      var html = '<h3 class="pw-title">' + esc(t('myTitle')) + '</h3>'
        + '<div class="pw-me-row">'
        + '<div class="pw-me-stat"><small>' + esc(t('myMember')) + '</small><b>' + esc(memberLine) + '</b></div>'
        + '<div class="pw-me-stat"><small>' + esc(t('myPans')) + '</small><b>' + ((data.pans || []).length) + '</b></div>'
        + '<div class="pw-me-stat"><small>' + esc(t('myOrders')) + '</small><b>' + ((data.orders || []).length) + '</b></div>'
        + '</div>';
      if (cfg.features && cfg.features.membership) {
        html += '<div class="pw-actions"><button class="pw-btn" id="pwMeMember">' + esc(t('memberBuy', { m: money(p.member_monthly) })) + '</button>'
          + '<button class="pw-btn secondary" id="pwMePurchase">' + esc(t('purchase')) + '</button></div>';
      }
      if (cfg.features && cfg.features.cloud_sync) {
        html += '<p style="margin:14px 0 0"><button class="pw-btn ghost small" id="pwMeSync">' + esc(t('syncBtn')) + '</button></p>';
      }
      html += '<div class="pw-tabs" style="margin-top:18px"><button role="tab" data-tab="orders" aria-selected="true">' + esc(t('tabOrders')) + '</button>'
        + '<button role="tab" data-tab="pans" aria-selected="false">' + esc(t('tabPans')) + '</button></div>'
        + '<div class="pw-scroll" id="pwMeBody"></div>'
        + '<div class="pw-notice">' + esc(t('confirmNote')) + '<br>' + esc(t('minorNote')) + '<br>' + esc(t('refundNote')) + '</div>';

      dlg.innerHTML = '<button class="pw-close" aria-label="' + esc(t('close')) + '">×</button>' + html;
      dlg.querySelector('.pw-close').addEventListener('click', function () { dlg.close(); });

      var orders = data.orders || [], pans = data.pans || [];
      var body = dlg.querySelector('#pwMeBody');
      var draw = function (tab) {
        if (tab === 'orders') {
          body.innerHTML = orders.length
            ? '<table class="pw-table"><thead><tr><th>' + esc(t('paySummaryNo')) + '</th><th>' + esc(t('paySummaryItem')) + '</th><th>' + esc(t('paySummaryAmount')) + '</th><th>' + esc(t('paySummaryStatus')) + '</th></tr></thead><tbody>'
              + orders.map(function (o) {
                return '<tr><td>' + esc(o.out_trade_no) + '</td><td>' + esc(t(ORDER_TYPE_LABEL[o.type] || 'typeSingle')) + '</td><td>' + esc(money(o.amount_cny)) + '</td><td>' + esc(t(o.status === 'paid' ? 'statusPaid' : o.status === 'refunded' ? 'statusRefunded' : o.status === 'failed' ? 'statusFailed' : 'statusPending')) + '</td></tr>';
              }).join('') + '</tbody></table>'
            : '<p class="pw-fine">' + esc(t('noOrders')) + '</p>';
        } else {
          body.innerHTML = pans.length
            ? '<table class="pw-table"><thead><tr><th>' + esc(LANG === 'en' ? 'Question' : '所问') + '</th><th>' + esc(LANG === 'en' ? 'Time' : '起盘时间') + '</th><th></th></tr></thead><tbody>'
              + pans.map(function (p2) {
                return '<tr><td>' + esc(String(p2.question || '').slice(0, 60)) + '</td><td>' + esc(String(p2.solar_label || '').slice(0, 16)) + '</td><td>' + esc(p2.unlocked ? '✓' : '') + '</td></tr>';
              }).join('') + '</tbody></table>'
            : '<p class="pw-fine">' + esc(t('noPans')) + '</p>';
        }
      };
      draw('orders');
      dlg.querySelectorAll('[role="tab"]').forEach(function (b) {
        b.addEventListener('click', function () {
          dlg.querySelectorAll('[role="tab"]').forEach(function (x) { x.setAttribute('aria-selected', String(x === b)); });
          draw(b.getAttribute('data-tab'));
        });
      });
      var memberBtn = dlg.querySelector('#pwMeMember');
      if (memberBtn) { memberBtn.addEventListener('click', function () { openPayConfirm('member_monthly'); }); }
      var purchaseBtn = dlg.querySelector('#pwMePurchase');
      if (purchaseBtn) { purchaseBtn.addEventListener('click', showPurchase); }
      var syncBtn = dlg.querySelector('#pwMeSync');
      if (syncBtn) {
        syncBtn.addEventListener('click', function () {
          syncHistory().then(function (n) {
            toast(n > 0 ? t('syncOk', { n: n }) : t('syncNothing'));
          });
        });
      }
    }).catch(function (e) {
      var dlgFail = document.getElementById('pwMeDialog');
      if (dlgFail) {
        dlgFail.innerHTML = '<button class="pw-close" aria-label="' + esc(t('close')) + '">×</button>'
          + '<h3 class="pw-title">' + esc(t('myTitle')) + '</h3><p class="pw-lead">' + esc(t('offline')) + '</p>';
        dlgFail.querySelector('.pw-close').addEventListener('click', function () { dlgFail.close(); });
      }
    });
  }

  function syncHistory() {
    var local = [];
    try {
      var raw = window.localStorage.getItem('wenshi-v1');
      if (raw) { local = JSON.parse(raw) || []; }
    } catch (e) { local = []; }
    var items = (Array.isArray(local) ? local : []).slice(0, 100).map(function (r, i) {
      return { iso: r.iso, question: r.question, details: r.details || '', category: r.category || 'general', lang: LANG, local_id: 'local-' + i };
    });
    if (!items.length) { return Promise.resolve(0); }
    return api('pans/sync', { method: 'POST', body: { items: items } }).then(function (res) {
      return (res.data && res.data.created) || 0;
    }).catch(function () { return 0; });
  }

  /* ------------------------- 购买须知 ------------------------- */

  function showPurchase() {
    var p = (S.config && S.config.pricing) || {};
    var support = (S.config && S.config.support && S.config.support.contact) || '';
    var wait = (S.config && S.config.support && S.config.support.manual_wait_minutes) || 30;
    var body = t('purchaseBody', { p: money(p.single_unlock), m: money(p.member_monthly), n: wait })
      .split('\n').map(function (l) { return '<p>' + esc(l) + '</p>'; }).join('');
    var dlg = dialog('pwPurchaseDialog', '<h3 class="pw-title">' + esc(t('purchaseTitle')) + '</h3>'
      + '<div class="pw-notice">' + body + (support ? '<p>' + esc(t('support', { c: support })) + '</p>' : '') + '</div>');
    dlg.showModal();
  }

  /* ------------------------- 分享回流 ------------------------- */

  function handleReferral() {
    if (!S.config || !S.config.features || !S.config.features.referral) { return; }
    var params = new URLSearchParams(window.location.search);
    var ref = params.get('ref');
    if (!ref) { return; }
    var panKey = ref.indexOf('pan_') === 0 ? ref.slice(4) : ref;
    if (!/^[a-f0-9]{16,64}$/i.test(panKey)) { return; }
    api('share', { method: 'POST', body: { pan_key: panKey, ref: ref } });
    var target = document.querySelector('.workspace') || document.querySelector('.intro');
    if (!target || document.getElementById('pwReferral')) { return; }
    var banner = el('<div class="pw-referral" id="pwReferral"><p><b>' + esc(t('referralTitle')) + '</b><br>' + esc(t('referralLead')) + '</p>'
      + '<a class="pw-btn small" href="#questionForm">' + esc(t('referralBtn')) + '</a></div>');
    target.parentNode.insertBefore(banner, target);
  }

  /* ------------------------- 导航与声明增强 ------------------------- */

  function enhanceNav() {
    var nav = document.querySelector('nav.header-actions');
    if (!nav || nav.querySelector('.pw-my-btn')) { return; }
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'text-button pw-my-btn';
    btn.textContent = LANG === 'en' ? 'My readings' : '我的';
    btn.addEventListener('click', openMy);
    nav.appendChild(btn);
  }

  function enhanceNotice() {
    var notice = document.getElementById('notice');
    if (!notice || notice.querySelector('.pw-purchase-section')) { return; }
    var section = document.createElement('section');
    section.className = 'pw-purchase-section';
    var p = (S.config && S.config.pricing) || {};
    var waitMin = (S.config && S.config.support && S.config.support.manual_wait_minutes) || 30;
    section.innerHTML = '<h3>' + esc(LANG === 'en' ? '五、Payment, membership and refunds' : '五、付费、会员与退款') + '</h3>'
      + '<p>' + esc(t('purchaseBody', { p: money(p.single_unlock), m: money(p.member_monthly), n: waitMin }).replace(/\n/g, ' ')) + '</p>';
    var date = notice.querySelector('.notice-date');
    if (date) { notice.insertBefore(section, date); } else { notice.appendChild(section); }
    if (date) {
      date.textContent = (LANG === 'en' ? 'Notice version: ' : '声明版本：') + ((S.config && S.config.compliance && S.config.compliance.notice_version) || '2026-09-29');
    }
  }

  /* ------------------------- 免费分享卡片 ------------------------- */

  function overrideSharePayload() {
    window.buildSharePayload = function () {
      var ctx = lastCtx();
      if (!ctx) { return null; }
      var record = ctx.record, p = ctx.p;
      var names = (typeof PALACES !== 'undefined' && PALACES[p.t]) ? PALACES[p.t] : ['', ''];
      var categoryLabel = (typeof CATEGORIES !== 'undefined' && CATEGORIES[record.category]) ? CATEGORIES[record.category][0] : record.category;
      var verdict = (S.current && S.current.brief) || '';
      var sub = (S.current && S.current.subtype) ? S.current.subtype.label : '';
      return {
        record: record, p: p, palace: names[0], type: categoryLabel,
        verdict: verdict || names[0], focus: sub ? (LANG === 'en' ? 'Focus: ' : '关注：') + sub : '',
        closing: LANG === 'en'
          ? 'Full interpretation available at lfun.cloud.'
          : '完整解读可在 lfun.cloud 站内解锁。',
        action: ''
      };
    };
  }

  /* ------------------------- 启动 ------------------------- */

  function boot2() {
    installRenderHook();
    if (document.getElementById('result') && !document.getElementById('result').hidden) {
      trimToFreeLayer();
      attachPaywall();
    }
    enhanceNav();
    enhanceNotice();
    overrideSharePayload();
    handleReferral();
    document.addEventListener('click', function (e) {
      var link = e.target && e.target.closest ? e.target.closest('[data-pw-purchase]') : null;
      if (link) { e.preventDefault(); showPurchase(); }
    });
    if (S.config && S.config.features && S.config.features.cloud_sync && S.token) {
      api('me').then(function (res) {
        if (res.data && res.data.user && res.data.user.is_member) { syncHistory(); }
      }).catch(function () { /* ignore */ });
    }
  }

  /* 同步部分：先装渲染钩子与分享卡片覆盖，再异步拉配置。
   * 这样即使网络很慢，也不会出现「用的是旧 render / 分享卡片里带付费文案」。 */
  installRenderHook();
  overrideSharePayload();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      boot().then(boot2, boot2);
    });
  } else {
    boot().then(boot2, boot2);
  }

  window.LFUN = { t: t, openMy: openMy, showPurchase: showPurchase, config: function () { return S.config; }, state: S };
})();
