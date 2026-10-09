# 问时 · 小六壬 接口文档（/api）

所有接口返回 JSON。失败统一为 `{ ok:false, error:"<code>", message:"<中文提示>" }`。
身份采用匿名设备令牌：请求头 `X-Auth-Token`（或 `lfun_tok` cookie）+ `X-Device-Id`；
首次调用 `POST /api/session` 或任意需要身份的接口会自动懒创建用户。

## 通用错误码

| HTTP | error | 含义 |
| --- | --- | --- |
| 400 | invalid_request / invalid_time / invalid_question / invalid_type | 参数问题 |
| 401 | unauthenticated | 缺少/失效身份 |
| 402 | payment_required | 未付费（响应含 paywall 信息，**不含解读文本**） |
| 403 | forbidden | 越权访问他人盘面 |
| 403 | admin_ip_blocked | 后台访问受限：当前 IP 不在 `admin.ip_allowlist` 内 |
| 403 | admin_https_required | 后台访问受限：必须用 HTTPS（回环地址例外） |
| 403 | admin_not_configured | 后台未配置 `app.install_token`，会话无法签名，拒绝服务 |
| 404 | not_found | 找不到盘面/订单/接口 |
| 405 | method_not_allowed | 方法不对 |
| 429 | rate_limited | 触发限流 |
| 500 | server_error | 服务端异常 |
| 503 | calendar_unavailable | 服务端无法换算农历 |

---

## 公共

### GET /api/health
存活检查，返回驱动、时间与功能开关。

### GET /api/config
前端启动时拉取的公开配置。**不含任何密钥。**

```json
{ "ok": true, "config": {
  "name": "问时 · 小六壬", "version": "2026.09.29-monetization",
  "pricing": { "single_unlock": 9.9, "member_monthly": 29,
               "donation_presets": [2,5,10], "donation_min": 1, "donation_max": 200 },
  "features": { "paywall": true, "membership": true, "cloud_sync": true, "donation": true, "referral": true },
  "pay": { "default_channel": "manual", "channels": ["manual"],
           "manual": { "wx_qr": "media/wxpay-qr.png", "instructions": "…", "auto_grant": false } },
  "compliance": { "footer_notice": "…", "pay_notice": "…", "minor_notice": "…", "refund_notice": "…" },
  "support": { "contact": "微信 xxx", "manual_wait_minutes": 30 } } }
```

---

## 会话

### POST /api/session
`{ device_id }` → `{ auth:{token,device_id}, user:{id,is_member,member_expire_at,member_days_left} }`
令牌同时写入 `lfun_tok` cookie（HttpOnly），用于清空 localStorage 后找回。

### GET /api/me
→ `{ user, orders:[…], pans:[…] }`。用于「我的」页。

---

## 起盘（免费）

### POST /api/pan
| 字段 | 说明 |
| --- | --- |
| `iso` | ISO8601 起盘时间（UTC），缺省为服务器当前时间 |
| `question` / `details` | 问题与补充情境（各 ≤300 字） |
| `category` | `auto` 或类别 id |
| `lang` | `zh` / `en` |
| `lunar_month`/`lunar_day`/`lunar_leap` | 选填，客户端 ICU 农历；服务器优先用自己的 ICU 结果并交叉校验 |

→ `{ pan:{pan_key,…}, brief:"简版断语", subtype, unlocked:false, issue:{category,label}, classified, clarify, auth }`

服务端按与前端完全一致的公式重算三宫（`月宫=(月−1)%6`、`日宫=(月宫+日−1)%6`、`时宫=(日宫+时序−1)%6`），
因此 `pan_key` 与盘面由服务端权威确定。

### GET /api/pan/{pan_key}
同一盘面的免费层信息（越权 403）。

---

## 付费解锁

### POST /api/unlock  ·  GET /api/pans/{pan_key}/interpretation
`{ pan_key, lang? }`

- 未解锁 → **402** `{ error:"payment_required", paywall:{ price_cny, member_cny, pan_key } }`
- 已解锁（订单或会员有效期内）→ 200 `{ unlock:{reason:"order"|"member"}, pan, detail }`

`detail` 是**语言无关的区块数组**，前端只负责渲染：

```json
{ "lang":"zh","category":"work","label":"求职事业",
  "subtype":{"label":"面试推进","guidance":"…"},
  "combination":"留连＋速喜","pattern_key":"day:1|time:2","has_deep":true,
  "sections":[
    {"type":"combination","title":"日时合参 · 留连＋速喜","text":"…组合断语…"},
    {"type":"steps","title":"起盘过程 · 可以逐步复查","items":[{"title":"月上起日 · 找到起点","text":"…"}]},
    {"type":"path","title":"三宫流转 · 边走边校准","text":"…","line":["留连","速喜","小吉"]},
    {"type":"verdict","label":"直接判断","text":"…"},
    {"type":"grid","items":[{"label":"发展过程","text":"…"},{"label":"时间节奏","text":"…"},{"label":"关键着眼点","text":"…"},{"label":"出现什么才算应验","text":"…"}]},
    {"type":"palace_focus","title":"…","items":[{"label":"关注","text":"…"},{"label":"避免","text":"…"}]},
    {"type":"actions","title":"现在怎么做","items":["…","…","…"]},
    {"type":"emotion","title":"心理与情绪","text":"…","items":[{"label":"容易陷入","text":"…"},{"label":"稳定方法","text":"…"}]},
    {"type":"closing","label":"解卦语","text":"…"}
  ],
  "notice":"…仅供娱乐声明…" }
```

寻物类别把 `verdict`/`grid`/`palace_focus` 换成 `{"type":"lost", "chance","combination","items":[方位/位置/节奏],"actions":[搜索顺序],"note","deep"}`。

---

## 订单

### POST /api/orders
`{ type: single_unlock | member_monthly | donation, pan_key?, amount_cents?, channel? }`

- 金额由服务端按定价表核定，**前端传入的 amount_cents 仅作参考**（打赏用于选择金额，并做 1~200 元夹取）。
- 单次解锁必须先有该用户自己的 `pan_key`；已解锁的盘直接返回 `already_unlocked:true` 不重复下单。

→ `{ order:{id,amount_cents,amount_cny,status,out_trade_no,expires_at}, pay:{channel,params}, notice:{pay,minor,refund} }`

`pay.params` 按渠道不同：
- `manual`：`{ wx_qr, alipay_qr, instructions, amount_cny, out_trade_no }`
- `wxpay`：`{ code_url }`（Native 扫码）
- `alipay`：`{ code_url }`（当面付）

### GET /api/orders/{id}
前端轮询用。pending 时附带 `pay`。

### POST /api/orders/{id}/claim
人工收款渠道下用户点「我已完成付款」，记录 `claimed_at`，后台会出现核销标记。

### POST /api/pay/notify/{channel}
支付回调。微信：校验 `Wechatpay-*` 头签名（平台证书）→ AES-256-GCM 解密 → 金额校验 → 幂等入账。
支付宝：RSA2 验签 → 金额校验 → 幂等入账。**验签失败直接拒绝，不修改订单。**

---

## 会员与云同步

### POST /api/pans/sync
`{ items:[{iso,question,details,category,lang,local_id}] }` → `{ created, skipped }`，按 `pan_key` 去重。

### GET /api/pans?limit=&before_id=
分页拉取自己的盘例（只返回本人的，接口按 `user_id` 隔离）。

---

## 分享与运维

### POST /api/share
`{ pan_key, ref }` 记录分享落地点击（`share_clicks` 表）。

### POST /api/tasks/reconcile
掉单补偿，需 `?token=<app.install_token>`。建议 Hostinger Cron 每 10 分钟调用一次：
`curl -s -X POST "https://lfun.cloud/api/tasks/reconcile?token=你的令牌"`

---

## 管理后台接口（/api/admin/*）

登录：`POST /api/admin/login {username,password}` → 下发 HMAC 签名的 `lfun_admin` cookie（HttpOnly/SameSite=Strict，12 小时）。

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/api/admin/session` | 当前登录状态 |
| GET | `/api/admin/stats` | 概览漏斗、收入、会员数、审计日志 |
| GET | `/api/admin/orders?status=&claimed_only=` | 订单列表 |
| POST | `/api/admin/orders/{id}/confirm` | 确认收款并开通（幂等） |
| POST | `/api/admin/orders/{id}/refund` | 退款并收回解锁 |
| POST | `/api/admin/orders/{id}/fail` | 关闭订单 |
| GET/POST | `/api/admin/pricing` `/pricing/save` | 定价读写（立即生效） |
| POST | `/api/admin/features/save` | 功能开关（付费墙/会员/同步/打赏/分享） |
| GET/POST | `/api/admin/interpretations` `/save` `/delete` | 组合断语文案覆盖 |
| GET | `/api/admin/share` | 分享回流 Top |
| POST | `/api/admin/sync/run` | 手动执行掉单补偿 |
| POST | `/api/admin/notify/test` | 发送一条站长通知做自检（未配置 `notify.url` 时返回 400 `not_configured`） |

`GET /api/admin/stats` 还会返回 `admin_guard:{ip_allowlist,require_https,trust_proxy,client_ip}`，
后台据此在「数据总览」显示「你当前的来访 IP」，方便复制进白名单。

`GET /api/admin/stats` 还会返回 `notify:{enabled,type,support,manual_wait_minutes}`，
后台据此显示「待核销」红色横幅与通知配置状态。

### 站长通知（`config.local.php` 的 `notify` 段）

人工收款时，用户点「我已付款」会触发 `lfun_notify_admin()`：

```php
'notify' => [
    'enabled' => true,
    'type'    => 'wecom',    // wecom | dingtalk | feishu | serverchan | bark | generic
    'url'     => '机器人 Webhook 地址',
    'secret'  => '',         // 钉钉加签时才需要
    'on'      => ['claim' => true, 'paid' => false, 'donation' => true],
],
```

后台页面：`/api/admin/`
