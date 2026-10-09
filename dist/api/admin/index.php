<?php
/* 管理后台单页。所有数据都来自 /api/admin/* 的 JSON 接口，本文件不直接连数据库。 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
$appName = (string) cfg('app.name', '问时');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>管理后台 · <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{--ink:#1d1d1f;--muted:#6e6e73;--line:rgba(0,0,0,.1);--blue:#0a84ff;--green:#1c8c4a;--red:#c0392b;--bg:#f5f5f7}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
header{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 22px;background:rgba(255,255,255,.86);border-bottom:1px solid var(--line);backdrop-filter:blur(14px)}
header h1{margin:0;font-size:16px;letter-spacing:.02em}
header .who{color:var(--muted);font-size:13px}
main{max-width:1180px;margin:0 auto;padding:22px}
.card{padding:20px;border:1px solid var(--line);border-radius:18px;background:#fff;margin-bottom:18px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.stat{padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:#fff}
.stat small{display:block;color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.08em;margin-bottom:6px}
.stat b{font-size:20px}
h2{margin:0 0 14px;font-size:15px;letter-spacing:.02em}
h3{margin:16px 0 8px;font-size:13px;color:var(--muted);letter-spacing:.06em;text-transform:uppercase}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:9px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{color:var(--muted);font-size:11px;letter-spacing:.06em;text-transform:uppercase}
tr:hover td{background:#fafafa}
button{font:inherit;border:1px solid var(--line);background:#fff;border-radius:10px;padding:7px 13px;cursor:pointer;color:var(--ink)}
button:hover{border-color:#bbb}
button.primary{background:var(--ink);color:#fff;border-color:var(--ink);font-weight:600}
button.ok{background:var(--green);color:#fff;border-color:var(--green)}
button.danger{background:#fff;color:var(--red);border-color:rgba(192,57,43,.4)}
button.small{padding:5px 10px;font-size:12px;border-radius:8px}
input,select,textarea{font:inherit;padding:9px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;width:100%}
textarea{min-height:110px;resize:vertical;line-height:1.7}
label{display:block;margin:10px 0 4px;font-size:12px;color:var(--muted);font-weight:700;letter-spacing:.04em}
.row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.row>div{flex:1 1 150px}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.tabs button[aria-selected="true"]{background:var(--ink);color:#fff;border-color:var(--ink);font-weight:600}
.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.pill.pending{background:#fff3d6;color:#8a5a00}
.pill.paid{background:#e2f5e8;color:#1c7a41}
.pill.failed{background:#eee;color:#666}
.pill.refunded{background:#fde8e6;color:#a5352a}
.msg{padding:10px 14px;border-radius:12px;margin-bottom:14px;font-size:13px;display:none}
.msg.err{display:block;background:#fde8e6;color:#a5352a}
.msg.ok{display:block;background:#e2f5e8;color:#1c7a41}
.hint{color:var(--muted);font-size:12.5px;margin:8px 0 0;line-height:1.7}
.login{max-width:360px;margin:12vh auto;padding:26px;border-radius:20px;background:#fff;border:1px solid var(--line)}
.alert{display:none;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:14px 18px;border-radius:16px;margin-bottom:16px;font-size:14px}
.alert.show{display:flex}
.alert.warn{background:#fff4e0;border:1px solid rgba(200,130,0,.3);color:#8a5a00}
.alert.calm{background:#eef7f1;border:1px solid rgba(28,140,74,.25);color:#1c6f43}
code{background:#f2f2f4;padding:1px 5px;border-radius:5px;font-size:12px}
@media(max-width:700px){main{padding:14px}.card{padding:15px}}
</style>
</head>
<body>
<header>
  <h1>问时 · 管理后台</h1>
  <span class="who" id="who"></span>
</header>

<div class="login" id="loginBox">
  <h2>管理员登录</h2>
  <div class="msg" id="loginMsg"></div>
  <label for="u">用户名</label>
  <input id="u" autocomplete="username">
  <label for="p">密码</label>
  <input id="p" type="password" autocomplete="current-password">
  <p></p>
  <button class="primary" id="loginBtn" style="width:100%">登录</button>
  <p class="hint">账号在 <code>api/config.local.php</code> 中配置，运行 <code>api/install.php</code> 后创建。请及时删除 install.php。</p>
</div>

<main id="app" hidden>
  <div class="alert" id="alert"></div>
  <div class="msg" id="msg"></div>

  <section class="card">
    <h2>数据总览</h2>
    <div class="grid" id="stats"></div>
    <p class="hint" id="funnel"></p>
  </section>

  <section class="card">
    <div class="tabs" id="tabs">
      <button data-tab="orders" aria-selected="true">订单</button>
      <button data-tab="pricing" aria-selected="false">定价与开关</button>
      <button data-tab="interp" aria-selected="false">解读文案库</button>
      <button data-tab="share" aria-selected="false">分享回流</button>
      <button data-tab="audit" aria-selected="false">审计日志</button>
    </div>
    <div id="panel"></div>
  </section>
</main>

<script>
(function () {
  'use strict';
  var API = '../admin/';

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(cents) { return '¥' + (Number(cents || 0) / 100).toFixed(2); }

  function call(path, opts) {
    opts = opts || {};
    return fetch(API + path, {
      method: opts.method || 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: opts.body ? JSON.stringify(opts.body) : undefined
    }).then(function (r) {
      return r.json().then(function (j) { return { status: r.status, data: j }; });
    });
  }

  function flash(text, isError) {
    var m = $('msg');
    m.className = 'msg ' + (isError ? 'err' : 'ok');
    m.textContent = text;
    setTimeout(function () { m.className = 'msg'; }, 4200);
  }

  var STATUS_LABEL = { pending: '待支付', paid: '已支付', failed: '已关闭', refunded: '已退款' };
  var STATUS_CLASS = { pending: 'pending', paid: 'paid', failed: 'failed', refunded: 'refunded' };
  var TYPE_LABEL = { single_unlock: '单次解锁', member_monthly: '会员包月', donation: '打赏' };

  function tab(name) {
    Array.prototype.forEach.call($('tabs').children, function (b) {
      b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === name));
    });
    if (name === 'orders') { renderStats(); renderOrders(); }
    else if (name === 'pricing') { renderPricing(); }
    else if (name === 'interp') { renderInterp(); }
    else if (name === 'share') { renderShare(); }
    else if (name === 'audit') { renderStats(true); }
  }

  function renderStats(onlyAudit) {
    return call('stats').then(function (res) {
      if (res.status === 401) { showLogin(); return; }
      var s = res.data.stats || {};
      $('stats').innerHTML = [
        ['付费订单', s.orders_paid], ['待支付', s.orders_pending], ['待核销（已点已付款）', s.orders_claimed],
        ['累计收入', money(s.revenue_cents)], ['打赏收入', money(s.donation_cents)],
        ['用户数', s.users], ['会员数', s.members], ['盘面数', s.pans],
        ['已解锁', s.unlocks], ['解锁率', s.unlock_rate + '%'], ['分享回流', s.share_clicks], ['已退款', s.orders_refunded]
      ].map(function (kv) {
        return '<div class="stat"><small>' + esc(kv[0]) + '</small><b>' + esc(kv[1]) + '</b></div>';
      }).join('');
      $('funnel').textContent = '漏斗：起盘 ' + s.pans + ' → 下单 ' + (s.orders_pending + s.orders_paid) + ' → 付费 ' + s.orders_paid + ' → 解锁 ' + s.unlocks;
      renderAlert(s, res.data.notify || {});
      if (onlyAudit) { renderAudit(res.data.recent_audit || []); }
    });
  }

  function renderAlert(s, notify) {
    var el = $('alert');
    var claimed = Number(s.orders_claimed || 0);
    var extra = '';
    if (notify && notify.support) { extra = ' 客服：' + esc(notify.support) + '。'; }
    if (claimed > 0) {
      el.className = 'alert show warn';
      el.innerHTML = '<span>有 <b>' + claimed + '</b> 笔订单用户已点「我已付款」，等待你核销开通。' + extra + '</span>'
        + '<button class="primary small" id="alertGo">去核销</button>';
      $('alertGo').addEventListener('click', function () { tab('orders'); });
    } else if (notify && !notify.enabled) {
      el.className = 'alert show calm';
      el.innerHTML = '<span>暂无待核销订单。建议开启站长通知：用户点「我已付款」时直接推送到你手机，不用盯着后台。配置见 <code>api/config.local.php.example</code> 的 <code>notify</code> 段。</span>';
    } else {
      el.className = 'alert';
      el.innerHTML = '';
    }
  }

  function renderAudit(rows) {
    $('tabs').querySelector('[data-tab="audit"]').setAttribute('aria-selected', 'true');
    $('panel').innerHTML = '<h2>最近审计日志</h2><table><thead><tr><th>时间(UTC)</th><th>操作者</th><th>动作</th><th>对象</th><th>详情</th></tr></thead><tbody>'
      + rows.map(function (r) {
        return '<tr><td>' + esc(r.created_at) + '</td><td>' + esc(r.actor) + '</td><td>' + esc(r.action) + '</td><td>' + esc(r.target) + '</td><td><code>' + esc(String(r.detail || '').slice(0, 120)) + '</code></td></tr>';
      }).join('') + '</tbody></table>';
  }

  function renderOrders() {
    $('panel').innerHTML = '<h2>订单</h2>'
      + '<div class="row"><div><label>状态</label><select id="fStatus">'
      + '<option value="pending">待支付（可核销）</option><option value="paid">已支付</option><option value="refunded">已退款</option><option value="failed">已关闭</option><option value="all">全部</option>'
      + '</select></div><div><label>只看已点“我已付款”</label><select id="fClaimed"><option value="1">是</option><option value="">否</option></select></div><div><button id="fGo">刷新</button></div></div>'
      + '<div id="orderList"></div>';
    $('fGo').addEventListener('click', loadOrders);
    loadOrders();
  }

  function loadOrders() {
    var status = $('fStatus').value, claimed = $('fClaimed').value;
    $('orderList').innerHTML = '<p class="hint">加载中…</p>';
    call('orders?status=' + encodeURIComponent(status) + '&claimed_only=' + claimed).then(function (res) {
      if (res.status === 401) { showLogin(); return; }
      var rows = res.data.orders || [];
      if (!rows.length) { $('orderList').innerHTML = '<p class="hint">没有符合条件的订单。</p>'; return; }
      $('orderList').innerHTML = '<table><thead><tr><th>订单号</th><th>类型</th><th>金额</th><th>状态</th><th>问题</th><th>创建</th><th>操作</th></tr></thead><tbody>'
        + rows.map(function (o) {
          var actions = '';
          if (o.status !== 'paid') { actions += '<button class="ok small" data-act="confirm" data-id="' + o.id + '">确认收款并开通</button> '; }
          if (o.status === 'pending') { actions += '<button class="small" data-act="fail" data-id="' + o.id + '">关闭</button> '; }
          if (o.status === 'paid') { actions += '<button class="danger small" data-act="refund" data-id="' + o.id + '">退款</button> '; }
          return '<tr><td><code>' + esc(o.out_trade_no) + '</code>' + (o.claimed ? '<br><span class="pill pending">用户已点已付款</span>' : '') + '</td>'
            + '<td>' + esc(TYPE_LABEL[o.type] || o.type) + '</td><td>' + esc(money(o.amount_cents)) + '</td>'
            + '<td><span class="pill ' + (STATUS_CLASS[o.status] || '') + '">' + esc(STATUS_LABEL[o.status] || o.status) + '</span></td>'
            + '<td>' + esc(String(o.question || '').slice(0, 34)) + '</td>'
            + '<td>' + esc(String(o.created_at || '').slice(5, 16)) + '</td>'
            + '<td>' + actions + '</td></tr>';
        }).join('') + '</tbody></table>';

      Array.prototype.forEach.call($('orderList').querySelectorAll('button[data-act]'), function (b) {
        b.addEventListener('click', function () {
          var act = b.getAttribute('data-act'), id = b.getAttribute('data-id');
          if (act === 'refund' && !confirm('确认退款并收回该盘的解锁？')) { return; }
          b.disabled = true;
          call('orders/' + id + '/' + act, { method: 'POST' }).then(function (r) {
            if (!r.data.ok) { flash(r.data.message || '操作失败', true); b.disabled = false; return; }
            flash(act === 'confirm' ? '已确认收款并开通解读。' : (act === 'refund' ? '已退款并收回解锁。' : '订单已关闭。'));
            loadOrders(); renderStats();
          });
        });
      });
    });
  }

  function renderPricing() {
    call('pricing').then(function (res) {
      if (res.status === 401) { showLogin(); return; }
      var p = res.data.pricing || {}, f = res.data.features || {};
      $('panel').innerHTML = '<h2>定价与功能开关</h2>'
        + '<p class="hint">改动立即生效，无需重新部署前端。金额单位为人民币元。</p>'
        + '<div class="row">'
        + '<div><label>单次详细解读（元）</label><input id="pSingle" type="number" step="0.1" min="0.01" value="' + p.single_unlock_cny + '"></div>'
        + '<div><label>会员 / 月（元）</label><input id="pMember" type="number" step="0.1" min="0.01" value="' + p.member_monthly_cny + '"></div>'
        + '<div><label>打赏下限（元）</label><input id="pMin" type="number" step="1" min="0.01" value="' + p.donation_min_cny + '"></div>'
        + '<div><label>打赏上限（元）</label><input id="pMax" type="number" step="1" min="0.01" value="' + p.donation_max_cny + '"></div>'
        + '<div><label>打赏预设（逗号分隔）</label><input id="pPresets" value="' + (p.donation_presets_cny || []).join(',') + '"></div>'
        + '</div>'
        + '<h3>功能开关</h3><div class="row">'
        + featureSwitch('paywall', '付费墙（关闭后详细解读免费）', f.paywall)
        + featureSwitch('membership', '会员体系', f.membership)
        + featureSwitch('cloud_sync', '云端同步', f.cloud_sync)
        + featureSwitch('donation', '打赏', f.donation)
        + featureSwitch('referral', '分享回流统计', f.referral)
        + '</div><p></p><button class="primary" id="savePricing">保存</button>';
      $('savePricing').addEventListener('click', function () {
        var body = {
          pricing: {
            single_unlock_cny: Number($('pSingle').value),
            member_monthly_cny: Number($('pMember').value),
            donation_min_cny: Number($('pMin').value),
            donation_max_cny: Number($('pMax').value),
            donation_presets_cny: $('pPresets').value.split(',').map(function (x) { return Number(x.trim()); }).filter(function (x) { return x > 0; })
          }
        };
        call('pricing/save', { method: 'POST', body: body }).then(function (r) {
          if (!r.data.ok) { flash(r.data.message || '保存失败', true); return; }
          var feats = {};
          ['paywall', 'membership', 'cloud_sync', 'donation', 'referral'].forEach(function (k) {
            feats[k] = $(('f_' + k)) ? $(('f_' + k)).checked : true;
          });
          call('features/save', { method: 'POST', body: { features: feats } }).then(function () {
            flash('已保存并立即生效。');
            renderStats();
          });
        });
      });
    });
  }

  function featureSwitch(key, label, value) {
    return '<div><label>' + esc(label) + '</label><label style="font-weight:400;color:#1d1d1f"><input type="checkbox" id="f_' + key + '" style="width:auto;margin-right:6px"' + (value ? ' checked' : '') + '>启用</label></div>';
  }

  function renderInterp() {
    $('panel').innerHTML = '<h2>解读文案库（后台覆盖）</h2>'
      + '<p class="hint">此处写入的文案会覆盖内置文案库中「日宫 × 时宫」的组合断语，按 问题类别 + 语言 + 组合键 命中，保存后立即生效。不写则使用内置文案库与自动回退。</p>'
      + '<div class="row"><div><label>问题类别</label><select id="iType"><option value="work">求职事业</option><option value="cooperation">合作办事</option><option value="relationship">感情人际</option><option value="finance">钱财收支</option><option value="study">考试学习</option><option value="health">健康状态</option><option value="travel">出行安排</option><option value="lost">寻物</option><option value="general">日常决策</option></select></div>'
      + '<div><label>语言</label><select id="iLang"><option value="zh">中文</option><option value="en">English</option></select></div>'
      + '<div><label>组合键（日宫-时宫，0=大安 1=留连 2=速喜 3=赤口 4=小吉 5=空亡）</label><input id="iKey" placeholder="例如 4-2"></div></div>'
      + '<label for="iText">文案</label><textarea id="iText" placeholder="写入覆盖文案…"></textarea>'
      + '<p><button class="primary" id="iSave">保存覆盖</button> <button class="danger" id="iDel">删除覆盖</button> <button id="iList">刷新列表</button></p>'
      + '<div id="iListBox"></div>';
    $('iList').addEventListener('click', loadInterp);
    $('iSave').addEventListener('click', function () {
      call('interpretations/save', { method: 'POST', body: { pattern_key: 'day:' + ($('iKey').value.split('-')[0] || '0') + '|time:' + ($('iKey').value.split('-')[1] || '0'), question_type: $('iType').value, lang: $('iLang').value, rich_text: $('iText').value } })
        .then(function (r) { flash(r.data.ok ? '已保存，立即生效。' : (r.data.message || '保存失败'), !r.data.ok); loadInterp(); });
    });
    $('iDel').addEventListener('click', function () {
      call('interpretations/delete', { method: 'POST', body: { pattern_key: 'day:' + ($('iKey').value.split('-')[0] || '0') + '|time:' + ($('iKey').value.split('-')[1] || '0'), question_type: $('iType').value, lang: $('iLang').value } })
        .then(function () { flash('已删除覆盖，回退到内置文案。'); loadInterp(); });
    });
    loadInterp();
  }

  function loadInterp() {
    call('interpretations').then(function (res) {
      var rows = res.data.interpretations || [];
      $('iListBox').innerHTML = rows.length
        ? '<h3>已有覆盖（' + rows.length + '）</h3><table><thead><tr><th>组合键</th><th>类别</th><th>语言</th><th>文案</th><th>更新时间</th></tr></thead><tbody>'
          + rows.map(function (r) {
            return '<tr><td><code>' + esc(r.pattern_key) + '</code></td><td>' + esc(r.question_type) + '</td><td>' + esc(r.lang) + '</td><td>' + esc(String(r.rich_text).slice(0, 60)) + '</td><td>' + esc(r.updated_at) + '</td></tr>';
          }).join('') + '</tbody></table>'
        : '<p class="hint">目前没有任何后台覆盖，全部使用内置文案库。</p>';
    });
  }

  function renderShare() {
    call('share').then(function (res) {
      var rows = res.data.share || [];
      $('panel').innerHTML = '<h2>分享回流</h2>'
        + '<p class="hint">按分享落地页被访问的次数排序。数据来自 <code>?ref=pan_xxx</code> 的统计接口。</p>'
        + (rows.length
          ? '<table><thead><tr><th>盘面</th><th>点击</th><th>最近</th></tr></thead><tbody>' + rows.map(function (r) {
            return '<tr><td><code>' + esc(r.pan_key) + '</code></td><td>' + esc(r.clicks) + '</td><td>' + esc(r.last_at) + '</td></tr>';
          }).join('') + '</tbody></table>'
          : '<p class="hint">还没有分享回流数据。</p>')
        + '<h3>运维</h3><p><button id="syncRun">立即执行掉单补偿检查</button></p><p class="hint">建议在 Hostinger 中用 Cron 定时调用 <code>api/tasks/reconcile?token=…</code>。</p>';
      $('syncRun').addEventListener('click', function () {
        call('sync/run', { method: 'POST' }).then(function (r) {
          flash('已检查 ' + (r.data.checked || 0) + ' 笔待支付订单。');
        });
      });
      // 站长通知自检
      call('stats').then(function (res) {
        var n = res.data.notify || {};
        var box = document.createElement('div');
        box.innerHTML = '<h3>站长通知</h3><p class="hint">当前状态：' + (n.enabled ? '已开启（' + esc(n.type) + '）' : '未配置') + '。'
          + (n.support ? '客服联系方式：' + esc(n.support) + '；' : '')
          + '承诺等待时间：' + esc(n.manual_wait_minutes) + ' 分钟。'
          + '用户点「我已付款」时会立刻推送给你。</p><p><button id="notifyTest">发送测试通知</button></p>';
        $('panel').appendChild(box);
        $('notifyTest').addEventListener('click', function () {
          call('notify/test', { method: 'POST' }).then(function (r) {
            var msg;
            if (r.data.ok) {
              msg = r.data.sent ? '测试通知已发出，请查看手机。' : '发送失败：请求已发出但对方未接受，请检查 notify.url 是否正确。';
            } else if (r.data.error === 'not_configured') {
              msg = '还没有配置站长通知。请打开 api/config.local.php，按 notify 段填入企业微信/钉钉/飞书机器人地址后重试。';
            } else {
              msg = r.data.message || '通知测试失败。';
            }
            flash(msg, !r.data.ok || !r.data.sent);
          });
        });
      });
    });
  }

  function showLogin() {
    $('loginBox').hidden = false;
    $('app').hidden = true;
  }

  function showApp(admin) {
    $('loginBox').hidden = true;
    $('app').hidden = false;
    $('who').textContent = admin.username + ' · ';
    var out = document.createElement('button');
    out.textContent = '退出';
    out.className = 'small';
    out.addEventListener('click', function () {
      call('logout', { method: 'POST' }).then(function () { location.reload(); });
    });
    $('who').appendChild(out);
    renderStats();
    tab('orders');
  }

  $('loginBtn').addEventListener('click', function () {
    var btn = $('loginBtn');
    btn.disabled = true;
    call('login', { method: 'POST', body: { username: $('u').value, password: $('p').value } }).then(function (r) {
      btn.disabled = false;
      if (!r.data.ok) {
        var m = $('loginMsg');
        m.className = 'msg err';
        m.textContent = r.data.message || '登录失败';
        return;
      }
      showApp(r.data.admin);
    });
  });
  $('p').addEventListener('keydown', function (e) { if (e.key === 'Enter') { $('loginBtn').click(); } });

  Array.prototype.forEach.call($('tabs').children, function (b) {
    b.addEventListener('click', function () { tab(b.getAttribute('data-tab')); });
  });

  // 后台自动刷新：每 60 秒更新概览；停留在订单页时同时刷新列表，避免漏掉待核销订单。
  setInterval(function () {
    if ($('app').hidden) { return; }
    renderStats();
    var active = $('tabs').querySelector('[aria-selected="true"]');
    if (active && active.getAttribute('data-tab') === 'orders' && $('fStatus')) { loadOrders(); }
  }, 60000);

  call('session').then(function (r) {
    if (r.data && r.data.authenticated) { showApp(r.data.admin); } else { showLogin(); }
  });
})();
</script>
</body>
</html>
