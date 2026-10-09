<?php
declare(strict_types=1);

/**
 * 站长通知：用户点击「我已付款」时立刻推送到你的手机，避免订单堆在后台没人核销。
 * 支持企业微信 / 钉钉 / 飞书 / Server酱 / Bark / 通用 Webhook。默认关闭。
 */
function notify_config(): array
{
    $c = (array) cfg('notify', []);
    return array_merge([
        'enabled' => false,
        'type'    => 'generic',   // generic | wecom | dingtalk | feishu | serverchan | bark
        'url'     => '',
        'secret'  => '',          // 钉钉加签用
        'on'      => ['claim' => true, 'paid' => false, 'donation' => true],
    ], $c);
}

function notify_enabled(string $event = 'claim'): bool
{
    $c = notify_config();
    if (empty($c['enabled']) || empty($c['url'])) {
        return false;
    }
    return !empty($c['on'][$event]);
}

function notify_build_payload(string $type, string $title, string $text, string $secret): array
{
    switch ($type) {
        case 'wecom':
            return ['url' => '', 'body' => json_encode([
                'msgtype' => 'text',
                'text'    => ['content' => $title . "\n" . $text],
            ], JSON_UNESCAPED_UNICODE)];
        case 'dingtalk':
            return ['url' => '', 'body' => json_encode([
                'msgtype' => 'text',
                'text'    => ['content' => $title . "\n" . $text],
            ], JSON_UNESCAPED_UNICODE)];
        case 'feishu':
            return ['url' => '', 'body' => json_encode([
                'msg_type' => 'text',
                'content'  => ['text' => $title . "\n" . $text],
            ], JSON_UNESCAPED_UNICODE)];
        case 'serverchan':
            return ['url' => '', 'body' => json_encode([
                'title' => $title,
                'desp'  => $text,
            ], JSON_UNESCAPED_UNICODE)];
        case 'bark':
            return ['url' => '', 'body' => json_encode([
                'title' => $title,
                'body'  => $text,
                'group' => 'lfun',
            ], JSON_UNESCAPED_UNICODE)];
        default:
            return ['url' => '', 'body' => json_encode([
                'title' => $title,
                'text'  => $text,
                'source' => 'lfun.cloud',
                'time'   => lfun_iso8601(),
            ], JSON_UNESCAPED_UNICODE)];
    }
}

function lfun_notify_admin(string $title, string $text, array $event = []): bool
{
    $c = notify_config();
    if (empty($c['enabled']) || empty($c['url'])) {
        return false;
    }
    $url = (string) $c['url'];
    $type = (string) $c['type'];
    $payload = notify_build_payload($type, $title, $text, (string) ($c['secret'] ?? ''));

    if ($type === 'dingtalk' && $c['secret'] !== '') {
        $ts = (string) round(microtime(true) * 1000);
        $sign = urlencode(base64_encode(hash_hmac('sha256', $ts . "\n" . $c['secret'], $c['secret'], true)));
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'timestamp=' . $ts . '&sign=' . $sign;
    }
    if ($type === 'bark') {
        // Bark 用路径传标题与正文
        $url = rtrim($url, '/') . '/' . rawurlencode($title) . '/' . rawurlencode($text);
        $payload['body'] = '';
    }

    try {
        $res = payment_http('POST', $url, ['Content-Type: application/json'], $payload['body']);
        $ok = $res['status'] >= 200 && $res['status'] < 300;
        db_audit('system', $ok ? 'notify.sent' : 'notify.failed', $type, $event + ['status' => $res['status'], 'error' => $res['error'] ?? '']);
        return $ok;
    } catch (Throwable $e) {
        db_audit('system', 'notify.failed', $type, ['error' => $e->getMessage()]);
        return false;
    }
}

/** 用户点「我已付款」时通知站长。 */
function notify_order_claim(array $order): void
{
    $type = notify_enabled('claim');
    if ($type) {
        $p = pricing();
        $isDonation = ($order['type'] === 'donation');
        if ($isDonation && empty(notify_config()['on']['donation'])) {
            return;
        }
        $label = ['single_unlock' => '单次详细解读', 'member_monthly' => '会员包月', 'donation' => '打赏'][$order['type']] ?? $order['type'];
        $lines = [
            '金额：¥' . lfun_cents_to_cny((int) $order['amount_cents']),
            '类型：' . $label,
            '订单号：' . $order['out_trade_no'],
            '时间：' . lfun_now() . ' UTC',
            '',
            $isDonation ? '这是打赏，不需要开通任何内容。' : '请到后台点「确认收款并开通」：' . rtrim((string) cfg('app.base_url', ''), '/') . '/api/admin/',
        ];
        lfun_notify_admin(
            $isDonation ? '【问时】收到一笔打赏' : '【问时】有人付款了，待核销',
            implode("\n", $lines),
            ['order_id' => (int) $order['id'], 'out_trade_no' => $order['out_trade_no']]
        );
    }
}

/** 支付平台回调成功时通知（可选）。 */
function notify_order_paid(array $order): void
{
    if (!notify_enabled('paid')) {
        return;
    }
    lfun_notify_admin('【问时】支付已到账', '订单 ' . $order['out_trade_no'] . ' ¥' . lfun_cents_to_cny((int) $order['amount_cents']), ['order_id' => (int) $order['id']]);
}
