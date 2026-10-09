# 问时 · 小六壬（lfun.cloud）接手文档 —— 给 Muse

> 接手人：Muse　｜　上一手：DeepSeek Harness　｜　文档版本：2026-10-09
> 配套文档：`docs/交付说明-盈利化改造-20260929.md`（做了什么、为什么）、`docs/部署指南-Hostinger.md`（上线步骤）、
> `docs/验收清单.md`（真机走查）、`docs/API接口文档.md`（接口）、`docs/DEEP-CONTENT-SPEC.md`（文案库编写规范）

---

## 0. 三分钟上手

```bash
# 1) 进仓库
cd lfun/site                        # ↑ 你的实际路径

# 2) 配好本地配置（首次）
copy dist\api\config.local.php.example dist\api\config.local.php
#    本地用 SQLite 即可：db.driver=sqlite；填一个 install_token 和 admin 密码

# 3) 装测试依赖（只需一次，DOM 测试用）
cd tools && npm install && cd ..

# 4) 初始化数据库（本地 SQLite）
php dist/api/install.php            # 或 & <你的php路径> dist/api/install.php

# 5) 起本地服务（前台，模拟 /api 重写）
powershell -File tools\serve.ps1 -Port 8099
#    中文站   http://127.0.0.1:8099/
#    英文站   http://127.0.0.1:8099/en/
#    管理后台 http://127.0.0.1:8099/api/admin/   账号见 config.local.php

# 6) 另开一个终端，跑全量测试
powershell -File tools\run-all-tests.ps1
```

全部通过的话，你就已经站在和上一手一样的位置上了。

---

## 1. 这个项目是什么

面向中文用户（含英文版）的**小六壬在线起盘工具**。用户输入一件具体的事，系统按北京时间 + 中国农历的月、日、时
依法起盘，自动识别 9 类问题，给出可复查的计算路径、分类解读、心理提示与行动建议。

**技术形态**：纯静态前端 + PHP 服务端（无框架）。前端可离线起盘，服务端只负责付费内容与记账。
域名 `lfun.cloud`，托管 Hostinger（hPanel / hcdn）。

**当前产品定位**：传统文化娱乐体验。这个定位是合规红线，不是营销话术 —— 见第 12 节。

---

## 2. 现状快照（先看这一节，避免误判）

| 项目 | 状态 |
| --- | --- |
| **线上 lfun.cloud** | ⚠️ **仍是改造前的旧版**（免费全量内容、零收入）。本次改动**没有部署**，只在本地仓库里 |
| 本地仓库 | `lfun/site/`，Git 已提交 8 个新提交，工作区干净 |
| 部署包 | `lfun/site/build/lfun-hostinger-20260929.zip`（约 192 KB，可直接上传 Hostinger） |
| 数据库 | 本地用 SQLite（`dist/api/storage/lfun.sqlite`）；生产建议 MySQL |
| 支付 | **人工收款**（站长无商户号）：收款码 → 用户点「我已付款」→ 站长收到推送 → 后台一键开通 |
| 自动化验证 | PHP lint 28 文件 / 内容库自检 / 通知载荷 27 项 / 客户端泄漏 22 项 / 接口 69 项 / 中文页 69 项 / 英文页 15 项 / 后台 23 项，**全部通过** |

### 已经真正验证过的

- 人工收款全链路：起盘 → 付费墙 → 确认支付页 → 下单 → 收款面板 → 点「我已付款」→ **站长收到真实 HTTP 推送** → 后台核销 → 解锁渲染（jsdom 真 DOM + 真后端 + 本地 HTTP 接收端实测）
- 付费墙严谨性：未付费 `/api/unlock` 返回 402 且**响应体不含任何解读文本**；付费文案已从客户端剥离并加断言
- 越权隔离（403）、订单幂等、金额服务端核定、会员叠加、打赏上下限、云同步去重、英文流程无中文残留
- 中文/英文页面在 jsdom 下的完整 DOM 行为

### 还没验证过的（接手后请优先处理）

| # | 事项 | 说明 |
| --- | --- | --- |
| 1 | **MySQL 未实测** | 本地只有 SQLite。DDL 写了两套（MySQL / SQLite），但没在真 MySQL 上跑过 `install.php`。**上线前第一件要验的事** |
| 2 | 未部署 | 部署包没有上传到 Hostinger |
| 3 | 真机浏览器未走查 | 本环境沙箱禁止 Chrome 的命名管道 IPC，起不来真浏览器 → 前端用 jsdom 验证。视觉/触屏/真机分享需人工按 `docs/验收清单.md` 走一遍 |
| 4 | 微信/支付宝未联调 | 签名、验签、回调解密、金额校验、幂等都按官方规范实现，但没有真实商户号，**回调用真回调打过** |
| 5 | 通知 Webhook 只测过 generic | 企业微信/钉钉/飞书/Server酱/Bark 的**载荷格式**有单测，但真实机器人地址只测过 `generic`（本地接收端）与钉钉加签串 |

---

## 3. 本地环境要求

- **PHP 8.1+**，需要扩展：`pdo_mysql`、`pdo_sqlite`、`intl`（农历换算，必须）、`curl`（支付/通知）、`mbstring`、`openssl`
  - 本机验证用的是一份**便携版 PHP 8.5.11**，放在 `C:\Users\52978\Documents\DSH\.tools-php\php\php.exe`，**不在仓库里**。
  - `tools/serve.ps1` 与 `tools/run-all-tests.ps1` 会优先用这个路径，找不到就回退到 PATH 里的 `php`。
  - 你自己装 PHP 的话，只要 `php` 在 PATH 里，两个脚本都能直接用。
  - `intl` 缺失时 `/api/pan` 会回退到「客户端上报农历」，仍能用，但推荐装。
- **Node 18+**（验证用 24.19.0）。DOM 测试需要 jsdom：`cd tools && npm install`（`tools/node_modules` 已被 gitignore）
- **PowerShell 5.1+**（Windows 自带）。⚠️ 所有 `.ps1` 必须存成 **UTF-8 with BOM**，否则 5.1 会把中文读成乱码并报「字符串未终止」。改完脚本后跑一次 `node tools/fix-bom.js`（见第 10 节）
- 端口：本文档统一用 **8099**

---

## 4. 目录地图

```
lfun/site/
├─ dist/                       ← ★ 部署根目录，内容直接上传到 Hostinger 的 public_html
│  ├─ index.html               中文页（含 paywall.css / lfun-pay.js 引用）
│  ├─ app.js                   【免费层客户端】起盘算法 + 分类 + 免费层渲染（33.9K→14.4K 字符）
│  ├─ lfun-pay.js              【新增】付费层客户端：付费墙 / 下单 / 解锁 / 会员 / 打赏 / 分享回流（约 900 行）
│  ├─ paywall.css              【新增】付费层与后台样式
│  ├─ share-card.js            分享卡片（免费功能，画布绘制）
│  ├─ notice.js / future-ui.js 声明弹窗 / 鼠标 3D 倾斜
│  ├─ *.css                    原有视觉主题（product-ui / compass-premium / future-* 等，不要乱动）
│  ├─ en/                      英文站（index.html + app.js + en.css）
│  ├─ media/                   收款码图片放这里（README.txt 有说明）
│  └─ api/                     ★ PHP 服务端
│     ├─ index.php             前端控制器：所有 /api/* 路由都在这
│     ├─ install.php           安装脚本（用完必须删）
│     ├─ config.defaults.php   默认配置（读，不要改）
│     ├─ config.local.php.example  ← 复制成 config.local.php 填私有配置
│     ├─ admin/index.php       管理后台单页（HTML+JS，调 /api/admin/* 的 JSON 接口）
│     ├─ lib/                  业务库（见下表）
│     ├─ lib/channels/         manual / wxpay / alipay 三个支付渠道
│     └─ data/                 ★ 内容库与付费文案库（可人工改，见第 7 节）
├─ tools/                      开发与测试工具（不部署）
├─ tests/                      纯 PHP 自检脚本
└─ docs/                       文档
```

### `dist/api/lib/` 各文件职责

| 文件 | 职责 |
| --- | --- |
| `bootstrap.php` | 加载配置（defaults + local 深合并）、错误处理、输出缓冲、常量 `LFUN_API_DIR` |
| `util.php` | JSON/参数/响应辅助（`lfun_json` / `lfun_raw` / `lfun_fail`）、限流、哈希 |
| `db.php` | PDO 单例、**双份 DDL**（MySQL / SQLite）、审计日志 |
| `settings.php` | 定价与功能开关（配置文件为默认值，后台可覆盖）、`public_config()` |
| `users.php` | 匿名设备用户（token + device_id 双备份）、会员有效期叠加 |
| `panels.php` | **服务端起盘**：ICU 农历换算 + 三宫公式（与前端完全一致）+ `pan_key` 生成 |
| `pans.php` | 盘面存取、云同步（去重） |
| `unlocks.php` | 解锁记录与权限判定（已付费 / 会员有效期内） |
| `orders.php` | 订单状态机、幂等入账、掉单补偿、人工「我已付款」 |
| `payments.php` | 渠道分发 + 简易 HTTP 客户端 |
| `content.php` | 内容库加载与合并、组合断语查找（DB 覆盖 > deep 文件 > 回退） |
| `interpret.php` | 把内容库组装成**语言无关的区块数组**，前端只负责渲染 |
| `notify.php` | 站长推送（6 种渠道 + 钉钉加签） |
| `admin.php` | 后台 JSON 接口路由与实现（登录用 HMAC 签名 cookie，无 session 存储） |

---

## 5. 免费层 / 付费层的边界

这是整个改造的核心设计，改任何东西之前先理解它。

**免费层（永远免费，本地渲染，可离线）**
1. 六宫罗盘（六宫名 + 时支 + 指针 + 落宫高亮 + 动画）
2. 结果头（落宫、副标题、北京时间、农历、时辰）
3. 所问 + 补充情况
4. **总览一句话断语** ← 优先用服务端下发的分类断语，离线回退到六宫简版
5. 计算公式与使用约定（`details.trace`）
6. 分享卡片
7. 识别结果与子类标签
8. 健康/财务/法律类免责提示（`.caution`）

**付费层（服务端下发，`POST /api/unlock`）**
日时组合断语 → 起盘三步详解 → 三宫路径 → 直接判断 → 四项观察点（发展过程/节奏/着眼点/应验信号）→
该宫重点 → 现在怎么做 → 心理与情绪 → 解卦语；寻物类别把中段换成寻物专断（找回倾向/方位/位置/节奏/搜索顺序）。

**两条铁律**
1. **付费文案不得出现在客户端产物里。** 已用 `tools/check-client-leak.js` 逐条断言（22 项）。
2. **未付费时 `/api/unlock` 必须返回 402 且响应体不含任何解读文本。**

**免费层是怎么和付费层接起来的**（改前端时必读）

```
app.js            渲染免费层，把上下文放进 lastRenderContext（中文）/ lastReading（英文）
                      ↓
lfun-pay.js       同步包住 window.render：渲染完 → trimToFreeLayer() 只留免费节点
                      ↓
                  POST /api/pan  → 拿 pan_key、服务端核定的分类、简版断语
                      ↓
                  未解锁 → 注入付费墙卡片；已解锁/会员 → POST /api/unlock 拉 detail
                      ↓
                  detail.sections 是语言无关的区块数组，按 type 渲染成站内既有样式
```

`lfun-pay.js` 里几个必须知道的地方：

- `lastCtx()`：中文页读 `lastRenderContext`、英文页读 `lastReading`。**这两个都是顶层 `let`，不在 `window` 上，必须用裸标识符读取**（踩过坑）。
- `keepNodes()`：定义「什么算免费层」。**加新的免费节点时一定要同步加进这里**，否则会被裁掉（`.caution` 就被漏过一次）。
- `buildDetailHtml()`：把服务端 `sections` 渲染成 DOM。加新区块类型时改这里 + `interpret.php` 的 `interpret_detail()`。

---

## 6. 数据模型与接口

### 表（`dist/api/lib/db.php` 的 `db_schema()`，双份 DDL）

| 表 | 用途 | 关键约束 |
| --- | --- | --- |
| `users` | 匿名设备用户 | `device_id` 唯一、`token_hash` 唯一、`member_expire_at` |
| `divination_pans` | 盘面 | `pan_key` 唯一（= sha1(user+分钟+类别+语言)） |
| `orders` | 订单 | `out_trade_no` 唯一、状态 pending/paid/failed/refunded |
| `unlocks` | 解锁 | `pan_key` 唯一 → **同一盘只付一次** |
| `settings` | 后台覆盖的定价/开关 | k/v |
| `interpretations` | 后台覆盖的组合断语 | (pattern_key, question_type, lang) 唯一 |
| `share_clicks` / `audit_logs` / `admin_users` | 分享回流 / 审计 / 后台账号 | — |

### 接口（完整字段见 `docs/API接口文档.md`）

`GET /api/health` ・ `GET /api/config` ・ `POST /api/session` ・ `GET /api/me` ・
`POST /api/pan` ・ `GET /api/pan/{key}` ・ `POST /api/unlock` ・ `GET /api/pans/{key}/interpretation` ・
`POST /api/orders` ・ `GET /api/orders/{id}` ・ `POST /api/orders/{id}/claim` ・ `POST /api/pay/notify/{channel}` ・
`POST /api/pans/sync` ・ `GET /api/pans` ・ `POST /api/share` ・ `POST /api/tasks/reconcile` ・
`/api/admin/*`（登录、概览、订单核销/退款、定价、开关、文案库、分享、通知测试、掉单补偿）

**路由兼容三种部署**：`.htaccess` rewrite、URL 带 `index.php`、`?r=` 查询参数。
`route_path()` 只用 `REQUEST_URI` + 自身目录算 `/api` 前缀 —— **不要改回去用 `PATH_INFO` / `SCRIPT_NAME`**，
同目录下还有 `api/admin/index.php`，会把这俩值改掉导致路由被截错（踩过坑）。

### 后台访问限制（三层，至少开一层）

`/api/admin/` 能核销订单、改价、改文案，**上线前必须加限制**。顺序即推荐顺序：

| 层 | 做法 | 特点 |
| --- | --- | --- |
| 1 | Hostinger hPanel →「目录隐私」→ 选中 `public_html/api/admin` | 最省事；PHP 之前就拦掉；不可伪造 |
| 2 | `admin.ip_allowlist` + `admin.require_https` | 仓库自带，可测；支持 CIDR；配错会把自己锁在外面 |
| 3 | `app.install_token` 签名的后台 Cookie | 已内建；**token 为空时后台直接拒绝服务**（fail closed） |

代码位置：`dist/api/lib/admin.php` 的 `admin_ip_in_allowlist()` / `admin_guard_check()` / `admin_guard_enforce()`；
闸门在两个入口都挂了：`admin_dispatch()`（JSON 接口）和 `dist/api/admin/index.php`（后台页面本身）。

几条设计约定，改的时候别破坏：

- **`admin_secret()` 永不回退到硬编码常量。** 早先版本在 `install_token` 为空时用了一个固定字符串，等于任何人都能伪造后台会话；现在直接抛错、后台拒服。
- **默认只看 `REMOTE_ADDR`**，只有 `admin.trust_proxy = true` 才采信 `X-Forwarded-For`。不要为了「让 HTTPS 检查通过」而打开它——那是拿 IP 白名单的可靠性换方便。
- **`require_https` 默认 true**，但 `127.0.0.1` / `::1` 例外，否则本地和 CI 全被挡住。
- 白名单**未命中一律 403**，包括 `/api/admin/login`，且会写一条 `audit_logs`（actor=`guard`）。
- 后台「数据总览」在 `ip_allowlist` 为空时会显示警告横幅，并给出你当前的来访 IP。

验证：`php tests/verify-admin-guard.php`（35 项，含 CIDR 边界、IPv6、v4/v6 混用、fail-closed、XFF 防伪造），
以及 `tools/e2e-api.js` 里对 `stats.admin_guard` 的断言。

### 后台访问限制（三层，至少开一层）

`/api/admin/` 能核销订单、改价、改文案，**上线前必须加限制**。顺序即推荐顺序：

| 层 | 做法 | 特点 |
| --- | --- | --- |
| 1 | Hostinger hPanel →「目录隐私」→ 选中 `public_html/api/admin` | 最省事；PHP 之前就拦掉；不可伪造 |
| 2 | `admin.ip_allowlist` + `admin.require_https` | 仓库自带，可测；支持 CIDR；配错会把自己锁在外面 |
| 3 | `app.install_token` 签名的后台 Cookie | 已内建；**token 为空时后台直接拒绝服务**（fail closed） |

代码位置：`dist/api/lib/admin.php` 的 `admin_ip_in_allowlist()` / `admin_guard_check()` / `admin_guard_enforce()`；
闸门在两个入口都挂了：`admin_dispatch()`（JSON 接口）和 `dist/api/admin/index.php`（后台页面本身）。

几条设计约定，改的时候别破坏：

- **`admin_secret()` 永不回退到硬编码常量。** 早先版本在 `install_token` 为空时用了一个固定字符串，等于任何人都能伪造后台会话；现在直接抛错、后台拒服。
- **默认只看 `REMOTE_ADDR`**，只有 `admin.trust_proxy = true` 才采信 `X-Forwarded-For`。不要为了「让 HTTPS 检查通过」而打开它——那是拿 IP 白名单的可靠性换方便。
- **`require_https` 默认 true**，但 `127.0.0.1` / `::1` 例外，否则本地和 CI 全被挡住。
- 白名单**未命中一律 403**，包括 `/api/admin/login`，且会写一条 `audit_logs`（actor=`guard`）。
- 后台「数据总览」在 `ip_allowlist` 为空时会显示警告横幅，并给出你当前的来访 IP。

验证：`php tests/verify-admin-guard.php`（35 项，含 CIDR 边界、IPv6、v4/v6 混用、fail-closed、XFF 防伪造），
以及 `tools/e2e-api.js` 里对 `stats.admin_guard` 的断言。

---

## 7. 内容库体系（最容易踩坑的一节）

```
dist/api/data/
├─ content.zh.php      【自动生成·勿手改】palaces / categories 标签 / subtypes 标签 / 分类词表 / 寻物基础表
├─ content.en.php      【自动生成·勿手改】同上（英文）
├─ deep.zh.php         【人工维护·可改】8 类 × 36 条日宫×时宫组合断语 = 288 条（付费层核心增量）
├─ deep.en.php         【人工维护·可改】英文 288 条
├─ overrides.zh.php    【人工维护·可改】寻物 7 个空格补齐 + 寻物搜索顺序
└─ overrides.en.php    【人工维护·可改】英文六宫 nature/process/focus/avoid、应验信号、子类、寻物补齐
```

**加载与优先级**（`lib/content.php` 的 `content_library()`）：

```
content.{lang}.php  ← 由 tools/build-content.js 从 dist/app.js / dist/en/app.js 抽取生成
      ↓ content_merge 覆盖
overrides.{lang}.php  ← 人工补齐
      ↓
运行时查组合断语 content_deep()：数据库 interpretations 覆盖  >  deep.{lang}.php  >  空（调用方回退）
```

**三条禁令**

1. **不要手改 `content.zh.php` / `content.en.php`** —— 它们由 `tools/build-content.js` 从客户端 `app.js` 生成。
   要改分类标签或子类标签，改 `dist/app.js` 里的 `CATEGORIES` / `SUBTYPES`，然后重新跑：
   `node tools/build-content.js dist dist/api/data`
2. **不要跑 `tools/deep-content/build-deep-zh.mjs`** —— 那是当初一次性生成 `deep.zh.php` 的脚本，会把你的手改全部覆盖（目录里有 README.txt 提醒）。
3. **所有组合都必须有内容**：9 类 × 36 = 324 个组合，`tests/verify-content.php` 会逐条断言，不允许空白。

**改单条文案的推荐做法**：直接用管理后台 `/api/admin/` → 「解读文案库」写入覆盖。
优先级最高、存数据库、存完即生效、不用重新部署，也不用担心被生成脚本覆盖。

---

## 8. 常见任务 SOP

| 我想… | 怎么做 | 验证 |
| --- | --- | --- |
| **改价格** | 后台「定价与开关」改单次/会员/打赏区间，保存 | 打开首页，付费墙价格立刻变；改价会同步影响新订单金额（服务端核定） |
| **改一句解读文案** | 后台「解读文案库」填「日宫-时宫」+类别+语言，保存 | `POST /api/unlock` 后看该组合文案变了 |
| **批量改组合断语** | 直接编辑 `dist/api/data/deep.zh.php` 对应 key | `php tests/verify-content.php` + 重新起盘看效果 |
| **加一个问题类别** | ① `dist/app.js` 加 `CATEGORIES.xxx` / `SUBTYPES.xxx` / `CATEGORY_RULES.xxx` ② 跑 build-content.js ③ 在 `content.zh.php` 补 verdict/focus/guidance/closing/signal（或写进 overrides）④ 在 `deep.zh.php` 补 36 条 ⑤ 同步英文 ⑥ 前端 `<select id="category">` 加选项 | `tests/verify-content.php` |
| **改免费层内容** | 改 `dist/app.js` 的 `render()`；若新增节点，**必须同步加进 `lfun-pay.js` 的 `keepNodes()`** | `node tools/e2e-web.js` |
| **改付费层区块** | 服务端 `lib/interpret.php` 的 `interpret_detail()` 加 `sections`；前端 `lfun-pay.js` 的 `buildDetailHtml()` 加对应 `type` 渲染 | `node tools/e2e-api.js` + `e2e-web.js` |
| **接微信/支付宝商户号** | `config.local.php` 打开对应渠道 enabled 并填密钥，`pay.default_channel` 改掉 | 先小额真实支付一笔，确认回调验签与金额校验 |
| **配站长推送** | `config.local.php` 的 `notify` 段填机器人地址 | 后台「分享回流/运维」→「发送测试通知」 |
| **调后台自动刷新间隔** | `api/admin/index.php` 末尾的 `setInterval`（当前 60 秒） | 改完刷新后台看网络面板 |
| **改支付前确认页文案** | `dist/lfun-pay.js` 顶部的 `T.zh` / `T.en` 字典 | `node tools/e2e-web.js` |
| **出部署包** | `powershell -File tools\package.ps1` | 包内不含 `config.local.php` / sqlite / storage 数据 |

---

## 9. 测试体系

```bash
powershell -File tools\run-all-tests.ps1          # 一键全量（需要本地服务已在 8099 运行）
```

| 套件 | 覆盖 | 上次结果 |
| --- | --- | --- |
| PHP 语法检查 | `dist/api/**/*.php` 共 28 个文件 | 0 错误 |
| `tests/verify-content.php` | 内容库完整性 + 324 组合无空白 + 禁用词/专业锚点 | 全部通过 |
| `tests/verify-notify.php` | 6 种通知渠道载荷 + 钉钉加签 + 默认关闭 | 27/27 |
| `tests/verify-admin-guard.php` | CIDR 匹配 + 后台闸门（HTTPS/白名单/fail-closed/XFF 防伪造） | 35/35 |
| `tests/verify-admin-guard.php` | CIDR 匹配 + 后台闸门（HTTPS/白名单/fail-closed/XFF 防伪造） | 35/35 |
| `tools/check-client-leak.js` | 付费文案不在客户端 + 免费层数据仍在 | 22/22 |
| `tools/e2e-api.js` | 接口：用户/订单/402/403/幂等/会员/同步/打赏/寻物/英文 | 69/69 |
| `tools/e2e-web.js` | 中文页 jsdom 全流程（起盘→付费墙→下单→核销→解锁→打赏→我的） | 69/69 |
| `tools/e2e-web-en.js` | 英文页 DOM 冒烟 | 15/15 |
| `tools/e2e-admin.js` | 后台登录/概览/待核销横幅/核销/改价/文案覆盖/通知测试 | 23/23 |

**测试约定**

- 接口测试用真实 HTTP 打本地服务；前端测试用 **jsdom + 真实后端**（不是 mock）。
- e2e 脚本启动时会清空 `dist/api/storage/rate`（限流桶），避免连跑失败。
- 新增前端行为请写进 `tools/e2e-web.js`；新增接口行为写进 `tools/e2e-api.js`。
- 断言名用中文短句，失败时能直接看出哪条业务规则被破坏。
- `tools/notify-probe.js` 是本地 webhook 接收端（`node tools/notify-probe.js 8123`），配合临时打开 `notify` 用来实测推送。

**手动验证推送链路的完整步骤**（本环境实测过一次，可复现）

1. `node tools/notify-probe.js 8123`（后台常驻）
2. `config.local.php` 的 `notify` 改成 `enabled=true`、`type='generic'`、`url='http://127.0.0.1:8123/hook'`
3. 走一遍「起盘 → 下单 → 点我已付款」，接收端应打印订单号与金额的 JSON
4. 后台点「发送测试通知」应再收到一条
5. 验证完把 `notify.enabled` 改回 false

---

## 10. 环境陷阱（踩过的坑，按痛苦程度排序）

1. **PowerShell 5.1 读 `.ps1` 必须 UTF-8 with BOM**。没有 BOM 时中文变乱码，报「字符串缺少终止符」这类莫名其妙的语法错。
   改完 `tools/*.ps1` 后跑一次 `node tools/fix-bom.js`（仓库自带），它会检查并补上 BOM。
2. **PHP 警告会污染 JSON**。已加固：`bootstrap.php` 开输出缓冲，`lfun_json()` / `lfun_raw()` 先清缓冲。
   新增任何直出响应的出口（尤其是支付回调）请走这两个函数，不要裸 `echo`。
   同时：`curl_close()` 在 PHP 8.5 已废弃（会打印告警），**不要加回去**。
3. **顶层 `let/const` 不在 `window` 上**。`lastRenderContext`、`lastReading`、`history`、`escapeHtml`、`PALACES` 都是全局词法绑定，
   跨 `<script>` 用裸标识符能读到，`window.xxx` 是 undefined。读的时候用 `typeof x !== 'undefined'` 保护。
4. **`route_path()` 不要用 `PATH_INFO` / `SCRIPT_NAME`**（见第 6 节）。
5. **`api/.htaccess` 只管 `/api` 下的重写**；`/api/admin/` 是真实目录，走 DirectoryIndex。
6. **Chrome 在本沙箱起不来**（Mojo 用命名管道，被沙箱拒绝）。别浪费时间调 `--headless`，用 jsdom 或真机。
7. **git 的 CRLF 告警是正常的**（Windows `core.autocrlf`），不影响运行。
8. **`tools/strip-client-paid-content.js` 依赖 `dist/app.js` 的行号锚点**。它每次跑都会先做前置校验，对不上直接报错（不会静默改坏）。
   如果 `app.js` 被重新生成过，先 `git checkout c1d7625 -- dist/app.js dist/en/app.js` 再跑，然后人工复核 diff。
9. **`npm` 在本机是 `npm.cmd`**（`npm.ps1` 被执行策略拦）。用 `npm.cmd install`。
10. **`tools/package.ps1` 的打包排除项**：`config.local.php`、`storage/*`、`*.sqlite`、`*.log`。
    如果新增了敏感文件类型，记得同步排除规则。

---

## 11. 未完成清单与建议优先级

**P0（上线前必须）**
- [ ] 在真 MySQL 上跑 `install.php` 并走一遍全流程（本次只在 SQLite 上验证过）
- [ ] 上传部署包到 Hostinger `public_html`，确认 `index.html` 与 `api/index.php` 在同一层
- [ ] 配置 `config.local.php`（`install_token` / 数据库 / 后台密码 / 客服联系方式 / 收款码路径 / notify）
- [ ] 跑 `api/install.php?token=…` → **立刻删掉 install.php**
- [ ] 按 `docs/验收清单.md` 真机走查（重点：手机宽度、分享卡片下载、英文站、支付全流程）
- [ ] 后台点一次「发送测试通知」，确认手机能收到
- [ ] **开启后台访问限制**（hPanel 目录隐私 或 `admin.ip_allowlist`），并用非白名单 IP 验证返回 403
- [ ] **开启后台访问限制**（hPanel 目录隐私 或 `admin.ip_allowlist`），并用非白名单 IP 验证返回 403

**P1（上线后一周内）**
- [ ] 配 Cron：`curl -s -X POST "https://lfun.cloud/api/tasks/reconcile?token=…"`（每 10 分钟）
- [ ] 数据库定期备份
- [ ] 观察转化漏斗（后台「数据总览」的起盘→下单→付费→解锁），按数据调价
- [ ] 真机确认免费层视觉（客户端剥离后没动 CSS，但值得肉眼过一遍）

**P2（有商户号之后）**
- [ ] 用真实商户号联调微信/支付宝回调（验签、金额校验、幂等、掉单补偿）
- [ ] 退款走通（后台点退款会同时收回解锁）

**P3（产品增强，未做）**
- [ ] 后台数据看板：付费转化率、ARPU、分享回流 Top、按日趋势
- [ ] 手机号/微信登录（现在是匿名设备 ID，换设备靠 cookie 尽力找回）
- [ ] 退款/客服流程沉淀（现在是 v1 人工退款）
- [ ] 首屏改成滚动叙事（PRD 提过，当时判断首屏 CTA 更重要，未做）

---

## 12. 红线（不可违反，改任何文案前先看这段）

1. **全站口径统一为「传统文化娱乐」**。页脚、使用声明弹窗、支付前确认页三处必须保留「仅供娱乐，不构成任何建议」。
2. **不得出现承诺性用语**：改运、化解、注定、一定能、成功率、必应、百分百、保证准确、预测准确、包过。
   （`tests/verify-content.php` 会自动扫，但那只覆盖内容库，前端文案要人工把关。）
3. **不给成功率、精确应期或结果保证**；不推断他人内心。
4. **健康类必须落到「以医生/检查为准」**；不得提示停药、拒绝就医。
5. **财务类不得提示加仓、借贷**；只能依据已确认到账与可退出条件。
6. **出行类不对交通安全下结论**，以官方信息为准。
7. **支付相关必须保留**：未成年人监护人提示、退款政策（购买须知）、人工核销的等待时长说明。
8. **隐私口径必须与实际行为一致**：免费起盘不上传问题；只在付费解锁/开会员/主动同步历史时才发到服务端。
9. **付费墙两条铁律**（见第 5 节）：客户端不含付费文案；未付费 402 不泄露文本。

---

## 13. 术语表

| 术语 | 含义 |
| --- | --- |
| 小六壬 / 六宫 | 大安、留连、速喜、赤口、小吉、空亡，索引 0–5 |
| 月宫 / 日宫 / 时宫 | `m` / `d` / `t`。公式：`m=(月−1)%6`，`d=(m+日−1)%6`，`t=(d+时序)%6` |
| 时辰 | 子时 23:00–00:59 为第 1 个，两小时一个。代码里叫 `hourIndex` |
| pan / 盘 | 一次起盘的结果（三宫 + 时间 + 问题），唯一键 `pan_key` |
| 免费层 / 付费层 | 见第 5 节 |
| 组合断语 | 日宫 × 时宫的 36 组掌诀断语，按问题类别分写 —— **付费层核心增量** |
| `detail.sections` | 服务端下发、前端渲染的语言无关区块数组 |
| `pattern_key` | `day:{d}|time:{t}`，后台覆盖文案用的键 |
| 人工收款 / claim | 默认支付渠道：用户点「我已付款」→ 站长收到推送 → 后台核销 |
| `device_id` / token | 匿名身份双备份：localStorage + cookie，用于换设备尽力找回 |

---

## 14. 接手自检清单

做完这几步，你就完全接管了：

- [ ] `git log --oneline -n 8` 能看到 8 个新提交，`git status` 干净
- [ ] `tools\run-all-tests.ps1` 全部通过（8 个套件）
- [ ] 本地打开 `http://127.0.0.1:8099/` 起一盘，能看到付费墙 ¥9.9
- [ ] 在后台改一次价 → 刷新首页看到新价 → 改回去
- [ ] 在后台写一条文案覆盖 → 解锁该盘看到覆盖生效 → 删掉覆盖
- [ ] 读一遍 `dist/lfun-pay.js` 的 `keepNodes` / `lastCtx` / `buildDetailHtml` 三个函数
- [ ] 读一遍 `docs/交付说明-盈利化改造-20260929.md` 的「已知限制」一节
- [ ] 确认自己知道：**线上还是旧版，没部署**

有疑问先看 `docs/API接口文档.md` 和 `docs/部署指南-Hostinger.md`；
这两个文档和 `docs/交付说明` 是上一手留下的完整上下文。
