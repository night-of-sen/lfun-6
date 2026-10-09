# 问时 · 小六壬（lfun.cloud）

一个中英双语的小六壬实时起盘网站。北京时间 + 中国农历按月、日、时依法起盘，
自动识别 9 类问题，给出可复查的计算路径、分类解读、心理提示与行动建议。

本仓库是 **2026-09-29 盈利化改造** 后的完整版本：在保持起盘算法与视觉不变的前提下，
新增了付费详细解读、订单与支付、会员、云同步、打赏与分享回流。

## 快速开始（本地）

需要 PHP 8.1+（含 pdo_mysql / pdo_sqlite / intl / curl 扩展）与 Node 18+（仅测试用）。

```bash
# 1) 配置
cp dist/api/config.local.php.example dist/api/config.local.php
#    填写 app.install_token、db、admin 账号；本地可用默认 SQLite

# 2) 初始化数据库
php dist/api/install.php

# 3) 启动（Windows 一键脚本模拟 /api 重写）
tools\serve.ps1 -Port 8099
#    打开 http://127.0.0.1:8099/        中文站
#         http://127.0.0.1:8099/en/     英文站
#         http://127.0.0.1:8099/api/admin/  管理后台

# 4) 测试
tools\run-all-tests.ps1          # Windows
bash tools/ci.sh                # Linux / macOS / GitHub Actions（同一套断言）

## GitHub Actions

- **CI**（`.github/workflows/ci.yml`）：每次 push / PR 自动跑全量测试
  （PHP 语法、内容库自检、通知载荷、接口/中文页/英文页/后台 e2e、客户端泄漏检查）。
- **自动部署**（`.github/workflows/deploy.yml`）：`main` 分支每次推送后，
  把 `dist/` 经 FTP 同步到 Hostinger。需在仓库 Settings → Secrets → Actions 配置：
  `FTP_HOST` / `FTP_USERNAME` / `FTP_PASSWORD` / `FTP_DIR`
  （网站根目录的 FTP 路径，如 `/public_html`）。
  首次部署后需手动创建 `dist/api/config.local.php`（见部署指南），它不在仓库里，
  后续部署不会覆盖；`dist/api/storage/` 不会被同步。
```

## 文档

- `docs/交付说明-盈利化改造-20260929.md` —— 本次改造做了什么、验证结果、已知限制、回滚方案
- `docs/API接口文档.md` —— 全部接口
- `docs/部署指南-Hostinger.md` —— 上线步骤
- `docs/验收清单.md` —— 人工验收 checklist
- `docs/DEEP-CONTENT-SPEC.md` —— 付费组合断语文案库的编写规范

## 收款方式

当前使用**人工收款**（无商户号）：用户付款 → 点「我已付款」→ 站长收到推送 → 后台一键开通。
支持三种人工收款方式（可并存，前台按配置显示）：

- **微信 / 支付宝**：在 `dist/api/config.local.php` 的 `pay.manual` 段填写
  `wx_qr` / `alipay_qr`（收款码图片路径）。
- **PayPal**：在 `pay.manual.paypal` 段填写 `me`（你的 paypal.me 用户名）。
  前台会显示「用 PayPal 付款 $X」按钮，链接形如
  `https://www.paypal.com/paypalme/你的用户名/1.39`，
  美元金额 = 订单人民币金额 × `usd_per_cny`（默认 0.14，可在后台调整）。
  用户付完同样点「我已付款」，你在后台核销开通。
  `me` 留空则前台不显示 PayPal。后台「定价与开关」页可直接改开关、用户名与汇率。

请在 `dist/api/config.local.php` 的 `notify` 段配置企业微信/钉钉/飞书机器人，否则只能靠后台横幅发现待核销订单。

## 结构

- `dist/` 部署根目录（上传到 Hostinger 的 public_html）
- `dist/api/` PHP 服务端（订单、支付、解锁、会员、云同步、后台）
- `dist/api/data/` 内容库与付费文案库（可人工修改）
- `tools/` 开发与测试工具（含 `strip-client-paid-content.js`：付费内容从客户端剥离的手术脚本）
- `tests/` 内容库自检

## 许可与合规

本项目为传统民俗文化娱乐产品。所有解读**仅供娱乐**，不构成医疗、法律、财务或安全建议，
也不保证任何结果发生。详见站内「使用声明」与「购买须知」。
