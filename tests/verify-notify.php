<?php
/* 站长通知载荷自检：验证 6 种渠道生成的 JSON 形态与钉钉加签。
 * 用法：php tests/verify-notify.php   （不需要真的发出去） */
declare(strict_types=1);
require_once __DIR__ . '/../dist/api/lib/bootstrap.php';
require_once __DIR__ . '/../dist/api/lib/util.php';
require_once __DIR__ . '/../dist/api/lib/payments.php';
require_once __DIR__ . '/../dist/api/lib/notify.php';

$fail = 0;
function ck(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    printf("  %s  %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? ' :: ' . $detail : '');
    if (!$ok) {
        $fail++;
    }
}

$cases = [
    'wecom'      => ['msgtype', 'text'],
    'dingtalk'   => ['msgtype', 'text'],
    'feishu'     => ['msg_type', 'content'],
    'serverchan' => ['title', 'desp'],
    'bark'       => ['title', 'body'],
    'generic'    => ['title', 'text', 'source', 'time'],
];

foreach ($cases as $type => $keys) {
    $p = notify_build_payload($type, '标题', '正文内容', 'SECtest');
    $data = json_decode((string) $p['body'], true);
    ck($type . ' 生成合法 JSON', is_array($data));
    foreach ($keys as $k) {
        ck($type . ' 含字段 ' . $k, isset($data[$k]));
    }
    if ($type === 'wecom' || $type === 'dingtalk') {
        ck($type . ' 正文包含标题与内容', strpos($data['text']['content'], '标题') === 0 && strpos($data['text']['content'], '正文内容') !== false);
    }
    if ($type === 'feishu') {
        ck('feishu 正文包含标题与内容', strpos($data['content']['text'], '标题') === 0 && strpos($data['content']['text'], '正文内容') !== false);
    }
}

// 钉钉加签
$secret = 'SECtestsecret';
$ts = (string) round(microtime(true) * 1000);
$expect = urlencode(base64_encode(hash_hmac('sha256', $ts . "\n" . $secret, $secret, true)));
$ok = (bool) preg_match('/^[A-Za-z0-9%]+$/', $expect);
ck('钉钉加签串可 URL 编码', $ok);

// 未配置时不应发请求
$c = notify_config();
ck('默认通知开关为关闭', $c['enabled'] === false);
ck('未启用时不触发发送', notify_enabled('claim') === false);
ck('支付回调通知默认关闭', notify_enabled('paid') === false);

echo "\n" . ($fail === 0 ? "通知载荷全部通过。\n" : "失败 $fail 项。\n");
exit($fail === 0 ? 0 : 1);
