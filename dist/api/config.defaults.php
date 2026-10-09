<?php
/**
 * 默认配置。不要直接修改本文件：请复制 config.local.php.example 为 config.local.php 并填写。
 * 所有价格与开关都在这里集中管理（PRD §7：定价全部可配置，不 hardcode）。
 */
return [
    'app' => [
        'name'        => '问时 · 小六壬',
        'env'         => 'production',          // production | staging | local
        'debug'       => false,
        'timezone'    => 'Asia/Shanghai',
        'install_token' => '',                  // 部署后必须在 config.local.php 中改成一段随机字符串
        'cors_origin' => '',                    // 留空表示同域部署；填写则只允许该来源
    ],

    'db' => [
        'driver'  => 'sqlite',                  // mysql | sqlite
        'path'    => __DIR__ . '/storage/lfun.sqlite',
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'pay' => [
        'default_channel' => 'manual',          // manual | wxpay | alipay
        'manual' => [
            'enabled'       => true,
            'wx_qr'         => '',              // 微信收款码图片地址，例如 media/wxpay.png
            'alipay_qr'     => '',
            'instructions'  => '扫码付款后，点击下方“我已完成付款”。管理员核对到账后即刻开通。',
            'auto_grant'    => false,           // true = 用户点击“我已付款”即自动开通（不推荐，默认需管理员核验）
            'claim_window_minutes' => 120,
        ],
        'wxpay' => [
            'enabled'    => false,
            'appid'      => '',
            'mchid'      => '',
            'serial_no'  => '',                 // 商户 API 证书序列号
            'private_key' => '',                // 商户私钥（PEM 文件路径或 PEM 文本）
            'apiv3_key'  => '',                 // APIv3 密钥，用于回调解密
            'platform_cert' => '',              // 微信支付平台证书（PEM），用于回调验签
            'notify_url' => '',                 // 必须为 https 公网地址
        ],
        'alipay' => [
            'enabled'    => false,
            'app_id'     => '',
            'private_key' => '',                // 应用私钥（PEM）
            'alipay_public_key' => '',          // 支付宝公钥（PEM），用于回调验签
            'notify_url' => '',
            'gateway'    => 'https://openapi.alipay.com/gateway.do',
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ],
    ],

    // PRD §7 定价配置表
    'pricing' => [
        'single_unlock_cny'    => 9.9,
        'member_monthly_cny'   => 29,
        'donation_presets_cny' => [2, 5, 10],
        'donation_min_cny'     => 1,
        'donation_max_cny'     => 200,
    ],

    'features' => [
        'paywall'    => true,                   // 付费解盘开关（关闭后详细解读免费，用于灰度与回滚）
        'membership' => true,
        'cloud_sync' => true,
        'donation'   => true,
        'referral'   => true,
    ],

    'security' => [
        'order_pending_minutes' => 30,          // 未支付订单过期时间
        'order_query_before_paid_minutes' => 10, // 掉单补偿：pending 超过该时长主动向渠道查询（PRD 模块一）
        'token_cookie_name' => 'lfun_tok',
        'admin_cookie_name' => 'lfun_admin',
        'max_pans_per_user' => 500,
    ],

    'compliance' => [
        'notice_version' => '2026-09-29',
        'footer_notice'  => '传统文化娱乐体验，仅供娱乐，不构成任何建议。',
        'pay_notice'     => '付费内容为传统文化娱乐解读，仅供娱乐，不构成任何决策、医疗、法律、财务或安全建议。',
        'minor_notice'   => '未成年人请在监护人陪同下使用并完成支付。',
        'refund_notice'  => '虚拟内容一经解锁不退款；重复扣款或支付失败全额退款。',
    ],

    'support' => [
        'contact' => '',                        // 客服邮箱或微信，展示在购买须知、支付等待页与我的页面
        'manual_wait_minutes' => 30,            // 人工核销的承诺等待时间（分钟），用于页面提示
    ],

    // 站长通知：用户点「我已付款」时立刻推送到你的手机（没有商户号的场景下非常重要）
    'notify' => [
        'enabled' => false,
        'type'    => 'generic',                 // generic | wecom | dingtalk | feishu | serverchan | bark
        'url'     => '',                        // 企业微信/钉钉/飞书机器人 Webhook，或 Server酱/Bark 地址
        'secret'  => '',                        // 钉钉加签密钥（可选）
        'on'      => ['claim' => true, 'paid' => false, 'donation' => true],
    ],
];
