<?php
declare(strict_types=1);

/** 支付宝（当面付/手机网站支付）。RSA2 签名与异步通知验签按官方规范实现。 */

function alipay_cfg(string $key): string
{
    $v = cfg('pay.alipay.' . $key, '');
    return is_string($v) ? $v : '';
}

function alipay_load_key(string $pemOrPath): string
{
    if ($pemOrPath === '') {
        return '';
    }
    if (strpos($pemOrPath, '-----BEGIN') !== false) {
        return $pemOrPath;
    }
    if (is_file($pemOrPath)) {
        return (string) file_get_contents($pemOrPath);
    }
    $p = LFUN_API_DIR . '/storage/' . ltrim($pemOrPath, '/\\');
    return is_file($p) ? (string) file_get_contents($p) : '';
}

/** 待签名串：按 key 升序，去掉 sign / sign_type / 空值。 */
function alipay_sign_content(array $params): string
{
    unset($params['sign'], $params['sign_type']);
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $filtered[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v;
    }
    ksort($filtered);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return implode('&', $pairs);
}

function alipay_sign(array $params): string
{
    $key = openssl_pkey_get_private(alipay_load_key(alipay_cfg('private_key')));
    if ($key === false) {
        throw new RuntimeException('支付宝应用私钥无效');
    }
    $sig = '';
    if (!openssl_sign(alipay_sign_content($params), $sig, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('支付宝签名失败');
    }
    return base64_encode($sig);
}

function alipay_verify(array $params): bool
{
    if (empty($params['sign'])) {
        return false;
    }
    $sign = (string) $params['sign'];
    $content = alipay_sign_content($params);
    $pub = openssl_pkey_get_public(alipay_load_key(alipay_cfg('alipay_public_key')));
    if ($pub === false) {
        return false;
    }
    return openssl_verify($content, base64_decode($sign), $pub, OPENSSL_ALGO_SHA256) === 1;
}

function alipay_request(string $method, array $bizContent): array
{
    $params = [
        'app_id'      => alipay_cfg('app_id'),
        'method'      => $method,
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => date('Y-m-d H:i:s'),
        'version'     => '1.0',
        'notify_url'  => alipay_cfg('notify_url'),
        'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    $params['sign'] = alipay_sign($params);
    $res = payment_http('POST', alipay_cfg('gateway'), ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'], http_build_query($params));
    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        throw new RuntimeException('支付宝网关无有效响应');
    }
    $respKey = str_replace('.', '_', $method) . '_response';
    if (!isset($data[$respKey])) {
        throw new RuntimeException('支付宝返回结构异常');
    }
    return (array) $data[$respKey];
}

function alipay_create(array $order): array
{
    $resp = alipay_request('alipay.trade.precreate', [
        'out_trade_no' => (string) $order['out_trade_no'],
        'total_amount' => number_format(lfun_cents_to_cny((int) $order['amount_cents']), 2, '.', ''),
        'subject'      => mb_substr((string) cfg('app.name', '问时') . ' 详细解读', 0, 100, 'UTF-8'),
    ]);
    if (empty($resp['qr_code'])) {
        throw new RuntimeException('支付宝下单失败：' . (string) ($resp['sub_msg'] ?? $resp['msg'] ?? ''));
    }
    return [
        'type'         => 'alipay_qr',
        'code_url'     => (string) $resp['qr_code'],
        'out_trade_no' => (string) $order['out_trade_no'],
        'amount_cny'   => lfun_cents_to_cny((int) $order['amount_cents']),
    ];
}

function alipay_handle_notify(): void
{
    $post = $_POST;
    if (!$post) {
        $raw = file_get_contents('php://input');
        parse_str((string) $raw, $post);
    }
    if (!alipay_verify($post)) {
        db_audit('alipay', 'notify.reject', null, 'signature verify failed');
        lfun_raw('failure', 400);
    }
    $tradeStatus = (string) ($post['trade_status'] ?? '');
    $tradeNo = (string) ($post['out_trade_no'] ?? '');
    $order = $tradeNo !== '' ? order_by_trade_no($tradeNo) : null;
    if (!$order) {
        lfun_raw('failure', 404);
    }
    if (in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
        $paidCents = lfun_cny_to_cents($post['total_amount'] ?? 0);
        if ($paidCents !== (int) $order['amount_cents']) {
            db_audit('alipay', 'notify.amount_mismatch', $tradeNo, ['paid' => $paidCents, 'expected' => (int) $order['amount_cents']]);
            lfun_raw('failure', 400);
        }
        order_mark_paid($order, (string) ($trade_no ?? ''), 'alipay_notify');
    }
    lfun_raw('success');
}

function alipay_query(array $order): array
{
    try {
        $resp = alipay_request('alipay.trade.query', ['out_trade_no' => (string) $order['out_trade_no']]);
    } catch (Throwable $e) {
        return ['status' => 'unknown', 'channel' => 'alipay', 'error' => $e->getMessage()];
    }
    $state = (string) ($resp['trade_status'] ?? '');
    if (in_array($state, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
        $paidCents = lfun_cny_to_cents($resp['total_amount'] ?? 0);
        if ($paidCents === (int) $order['amount_cents']) {
            order_mark_paid($order, (string) ($resp['trade_no'] ?? ''), 'alipay_query');
            return ['status' => 'paid', 'channel' => 'alipay'];
        }
        return ['status' => 'amount_mismatch', 'channel' => 'alipay'];
    }
    if (in_array($state, ['TRADE_CLOSED'], true)) {
        order_mark_failed($order, 'alipay:' . $state);
        return ['status' => 'failed', 'channel' => 'alipay', 'state' => $state];
    }
    return ['status' => 'pending', 'channel' => 'alipay', 'state' => $state];
}
