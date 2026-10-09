# Hostinger 部署指南

## 0. 部署前准备

- 域名 `lfun.cloud` 已解析到 Hostinger（现状即如此，hPanel + hcdn）。
- 建议在 hPanel 里创建 MySQL 数据库（MySQL Databases），记下
  库名 / 用户名 / 密码 / 主机（通常 `localhost`）。
- 收款方式三选一（可并存）：
  - 微信与支付宝**收款码图片**（个人收款码即可），一张各一个；或
  - **PayPal**：记下你的 paypal.me 用户名（`https://www.paypal.com/paypalme/` 后面的那段）。

## 0b. 部署方式（二选一）

- **方法 A（手动）**：按下面 §1–§4 把 `dist/` 上传到 `public_html`。
- **方法 B（推荐，GitHub Actions 自动部署）**：
  1. 在 GitHub 仓库 Settings → Secrets and variables → Actions → New repository secret，
     添加 `FTP_HOST`（hPanel → 托管 → FTP 账户里查看主机）、`FTP_USERNAME`、
     `FTP_PASSWORD`、`FTP_DIR`（网站根目录的 FTP 路径，主站一般为 `/public_html`）。
  2. 以后每次往 `main` 分支推送，Action 会自动把 `dist/` 同步到服务器
     （也可在 Actions 页点 Run workflow 手动触发）。
  3. **首次部署后必须手动做一次**（§2–§3）：在服务器上创建
     `api/config.local.php`（它不在仓库里，Action 永远不会覆盖它）、
     跑 `api/install.php` 初始化、然后删除 `install.php`。
     之后的代码更新走推送即可，不用再碰服务器。

## 1. 上传文件

把仓库 `dist/` 目录里的**全部内容**上传到 `public_html`。目标结构必须是：

```
public_html/
├─ index.html
├─ app.js  lfun-pay.js  paywall.css  style.css  …
├─ en/
│  ├─ index.html
│  └─ app.js
├─ media/                 ← 收款码图片放这里
│  ├─ wxpay-qr.png
│  └─ alipay-qr.png
└─ api/
   ├─ index.php
   ├─ .htaccess
   ├─ install.php
   ├─ admin/index.php
   ├─ lib/  data/  storage/
```

> ⚠️ 不要出现 `public_html/lfun-hostinger-current/index.html` 这种多套一层目录的情况。

## 2. 配置

1. 复制 `api/config.local.php.example` → `api/config.local.php`。
2. 至少修改这三项：

```php
'app' => [
    'install_token' => '换成一串随机字符串（32 位以上）',
    'base_url'      => 'https://lfun.cloud',
],
'db' => [
    'driver' => 'mysql',
    'host'   => 'localhost',
    'name'   => 'uXXXXXXXX_lfun',
    'user'   => 'uXXXXXXXX_lfun',
    'pass'   => '数据库密码',
],
'admin' => ['username' => 'admin', 'password' => '换成一个强密码'],
'pay' => ['manual' => [
    'wx_qr'     => 'media/wxpay-qr.png',
    'alipay_qr' => 'media/alipay-qr.png',
    'auto_grant'=> false,   // 保持 false：需要人工核销
    // PayPal 人工收款（二选一或并存）：填 paypal.me 用户名即可启用；
    // 留空则前台不显示 PayPal。美元金额按 usd_per_cny 汇率展示。
    'paypal' => ['enabled' => true, 'me' => '你的paypalme用户名', 'currency' => 'USD', 'usd_per_cny' => 0.14],
]],
```

3. 配置站长通知（**人工收款场景强烈建议**，否则你只能靠后台横幅发现待核销订单）：

```php
// 企业微信群机器人（最简单）：群设置 → 群机器人 → 添加 → 复制 Webhook 地址
'notify' => [
    'enabled' => true,
    'type'    => 'wecom',
    'url'     => 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxxxxxxx',
],
```

也支持 `dingtalk`（可加 `secret` 加签）、`feishu`、`serverchan`、`bark`、`generic`（自建接口收 JSON）。

4. 同时填好客服联系方式，会显示在「购买须知」和「已付款等待核销」页面上：

```php
'support' => ['contact' => '微信 your-wechat-id', 'manual_wait_minutes' => 30],
```

5. 权限：`api/storage/` 需要对 PHP 可写（755 或 775）。

## 3. 初始化

浏览器访问：

```
https://lfun.cloud/api/install.php?token=你设置的 install_token
```

看到「安装完成」后**立即删除或改名 `api/install.php`**。

若 `app.install_token` 未改，安装脚本会拒绝执行（这是刻意的保护）。

## 4. 验证

1. `https://lfun.cloud/api/health` 返回 `{"ok":true,...}`
2. `https://lfun.cloud/` 起盘 → 结果页出现「解锁详细解读 ¥9.9」卡片
3. 点解锁 → 确认支付 → 出现收款码
4. 用你自己的微信扫 ¥9.9 → 点「我已完成付款」
   - 此时页面应显示「已记录，等待管理员核销。通常 30 分钟内开通」+ 客服联系方式
   - **你的手机应立刻收到机器人推送**（没收到就去后台点「发送测试通知」排查）
5. 打开 `https://lfun.cloud/api/admin/` 登录
   - 顶部应出现红色横幅「有 1 笔订单用户已点『我已付款』」
   - 点「去核销」→ 订单页 → 点「确认收款并开通」
6. 回到页面点「刷新订单状态」→ 详细解读出现
7. 关闭支付弹窗后，从页面右上角「我的」也能看到该订单状态
8. `https://lfun.cloud/en/` 重复 2–7（英文流程）

## 5. 定时任务（可选但推荐）

hPanel → Advanced → Cron Jobs，每 10 分钟执行：

```bash
curl -s -X POST "https://lfun.cloud/api/tasks/reconcile?token=你的install_token" >/dev/null
```

作用：pending 超过 10 分钟且已通过回调渠道下单的订单，主动向支付平台查询补单（PRD 模块一 1.3）。

## 6. 切换真实支付（拿到商户号后）

在 `api/config.local.php`：

```php
'pay' => [
  'default_channel' => 'wxpay',       // 或 alipay
  'wxpay' => [
    'enabled'   => true,
    'appid'     => 'wx…',
    'mchid'     => '16…',
    'serial_no' => '商户证书序列号',
    'private_key'=> 'apiclient_key.pem 的路径或内容',
    'apiv3_key' => 'APIv3 密钥（32 位）',
    'platform_cert' => '平台证书 PEM',
    'notify_url'=> 'https://lfun.cloud/api/pay/notify/wxpay',
  ],
],
```

私钥/证书建议放在 `api/storage/`（该目录已被 .htaccess 禁止直接访问）。

## 7. 回滚

1. 后台关闭「付费墙」开关（立即回到免费）。
2. 或用 Git 回退到改造前的提交重新上传 `dist/`。
3. 删除 `dist/api` 可完全恢复原静态站（原有功能不依赖后端）。
