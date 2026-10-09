<?php
declare(strict_types=1);

/**
 * 微信支付 APIv3（Native 扫码）。签名与回调解密按官方规范实现；
 * 商户号、私钥、APIv3 密钥、平台证书在 config.local.php 中配置后即可启用。
 */

function wxpay_cfg(string $key): string
{
    $v = cfg('pay.wxpay.' . $key, '');
    return is_string($v) ? $v : '';
}

function wxpay_load_key(string $pemOrPath): string
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
    // 允许放在 api/storage/ 下
    $p = LFUN_API_DIR . '/storage/' . ltrim($pemOrPath, '/\\');
    return is_file($p) ? (string) file_get_contents($p) : '';
}

/** 商户请求签名：method\nurl\ntimestamp\nnonce\nbody\n */
function wxpay_signature(string $method, string $urlPath, string $timestamp, string $nonce, string $body, string $privateKeyPem): string
{
    $message = strtoupper($method) . "\n" . $urlPath . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";
    $key = openssl_pkey_get_private($privateKeyPem);
    if ($key === false) {
        throw new RuntimeException('微信支付商户私钥无效');
    }
    $sig = '';
    if (!openssl_sign($message, $sig, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('微信支付签名失败');
    }
    return base64_encode($sig);
}

function wxpay_authorization(string $method, string $urlPath, string $body): string
{
    $mchid = wxpay_cfg('mchid');
    $serial = wxpay_cfg('serial_no');
    $key = wxpay_load_key(wxpay_cfg('private_key'));
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $signature = wxpay_signature($method, $urlPath, $timestamp, $nonce, $body, $key);
    return 'WECHATPAY2-SHA256-RSA2048 '
        . 'mchid="' . $mchid . '",'
        . 'nonce_str="' . $nonce . '",'
        . 'signature="' . $signature . '",'
        . 'timestamp="' . $timestamp . '",'
        . 'serial_no="' . $serial . '"';
}

function wxpay_create(array $order): array
{
    $host = 'https://api.mch.weixin.qq.com';
    $urlPath = '/v3/pay/transactions/native';
    $body = json_encode([
        'appid'        => wxpay_cfg('appid'),
        'mchid'        => wxpay_cfg('mchid'),
        'description'  => mb_substr((string) cfg('app.name', '问时') . ' 详细解读', 0, 120, 'UTF-8'),
        'out_trade_no' => (string) $order['out_trade_no'],
        'notify_url'   => wxpay_cfg('notify_url'),
        'amount'       => ['total' => (int) $order['amount_cents'], 'currency' => 'CNY'],
        'attach'       => json_encode(['order_id' => (int) $order['id']], JSON_UNESCAPED_UNICODE),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $res = payment_http('POST', $host . $urlPath, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: ' . wxpay_authorization('POST', $urlPath, $body),
        'User-Agent: lfun-cloud/1.0',
    ], $body);
    $data = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !is_array($data) || empty($data['code_url'])) {
        $msg = is_array($data) ? (string) ($data['message'] ?? $data['code'] ?? '') : ('HTTP ' . $res['status'] . ' ' . $res['error']);
        throw new RuntimeException('微信支付下单失败：' . $msg);
    }
    return [
        'type'         => 'wxpay_native',
        'code_url'     => (string) $data['code_url'],
        'out_trade_no' => (string) $order['out_trade_no'],
        'amount_cny'   => lfun_cents_to_cny((int) $order['amount_cents']),
    ];
}

/** 回调验签：使用微信支付平台证书公钥校验 Wechatpay-* 头。 */
function wxpay_verify_notify(string $timestamp, string $nonce, string $body, string $signature): bool
{
    $cert = wxpay_load_key(wxpay_cfg('platform_cert'));
    if ($cert === '') {
        return false;
    }
    $pub = openssl_pkey_get_public($cert);
    if ($pub === false) {
        return false;
    }
    $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
    $ok = openssl_verify($message, base64_decode($signature), $pub, OPENSSL_ALGO_SHA256);
    return $ok === 1;
}

/** AES-256-GCM 解密回调 resource。 */
function wxpay_decrypt_resource(array $resource): ?array
{
    $key = wxpay_cfg('apiv3_key');
    if ($key === '' || empty($resource['ciphertext'])) {
        return null;
    }
    $ciphertext = base64_decode((string) $resource['ciphertext'], true);
    if ($ciphertext === false || strlen($ciphertext) <= 16) {
        return null;
    }
    $tag = substr($ciphertext, -16);
    $data = substr($ciphertext, 0, -16);
    $plain = openssl_decrypt(
        $data,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        (string) ($resource['nonce'] ?? ''),
        $tag,
        (string) ($resource['associated_data'] ?? '')
    );
    if ($plain === false) {
        return null;
    }
    $decoded = json_decode($plain, true);
    return is_array($decoded) ? $decoded : null;
}

function wxpay_handle_notify(): void
{
    $body = file_get_contents('php://input');
    $timestamp = lfun_header('Wechatpay-Timestamp');
    $nonce = lfun_header('Wechatpay-Nonce');
    $signature = lfun_header('Wechatpay-Signature');
    if ($timestamp === '' || $nonce === '' || $signature === '') {
        lfun_json(['code' => 'FAIL', 'message' => 'missing signature headers'], 401);
    }
    if (!wxpay_verify_notify($timestamp, $nonce, (string) $body, $signature)) {
        db_audit('wxpay', 'notify.reject', null, 'signature verify failed');
        lfun_json(['code' => 'FAIL', 'message' => 'signature verify failed'], 401);
    }
    $payload = json_decode((string) $body, true);
    if (!is_array($payload) || empty($payload['resource'])) {
        lfun_json(['code' => 'FAIL', 'message' => 'bad payload'], 400);
    }
    $data = wxpay_decrypt_resource((array) $payload['resource']);
    if (!$data) {
        lfun_json(['code' => 'FAIL', 'message' => 'decrypt failed'], 400);
    }
    $tradeState = (string) ($data['trade_state'] ?? '');
    $tradeNo = (string) ($data['out_trade_no'] ?? '');
    $order = $tradeNo !== '' ? order_by_trade_no($tradeNo) : null;
    if (!$order) {
        lfun_json(['code' => 'FAIL', 'message' => 'unknown order'], 404);
    }
    if ($tradeState === 'SUCCESS') {
        // 金额校验：回调金额必须与订单一致
        $paidCents = (int) ($data['amount']['total'] ?? 0);
        if ($paidCents !== (int) $order['amount_cents']) {
            db_audit('wxpay', 'notify.amount_mismatch', $tradeNo, ['paid' => $paidCents, 'expected' => (int) $order['amount_cents']]);
            lfun_json(['code' => 'FAIL', 'message' => 'amount mismatch'], 400);
        }
        order_mark_paid($order, (string) ($data['transaction_id'] ?? ''), 'wxpay_notify');
    }
    lfun_json(['code' => 'SUCCESS', 'message' => 'OK']);
}

function wxpay_query(array $order): array
{
    $host = 'https://api.mch.weixin.qq.com';
    $urlPath = '/v3/pay/transactions/out-trade-no/' . rawurlencode((string) $order['out_trade_no']) . '?mchid=' . rawurlencode(wxpay_cfg('mchid'));
    $res = payment_http('GET', $host . $urlPath, [
        'Accept: application/json',
        'Authorization: ' . wxpay_authorization('GET', $urlPath, ''),
        'User-Agent: lfun-cloud/1.0',
    ]);
    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['status' => 'unknown', 'channel' => 'wxpay', 'error' => 'bad response'];
    }
    $state = (string) ($data['trade_state'] ?? '');
    if ($state === 'SUCCESS') {
        $paidCents = (int) ($data['amount']['total'] ?? 0);
        if ($paidCents === (int) $order['amount_cents']) {
            order_mark_paid($order, (string) ($data['transaction_id'] ?? ''), 'wxpay_query');
            return ['status' => 'paid', 'channel' => 'wxpay'];
        }
        return ['status' => 'amount_mismatch', 'channel' => 'wxpay'];
    }
    if ($state === 'CLOSED' || $state === 'REVOKED' || $state === 'PAYERROR') {
        order_mark_failed($order, 'wxpay:' . $state);
        return ['status' => 'failed', 'channel' => 'wxpay', 'state' => $state];
    }
    return ['status' => 'pending', 'channel' => 'wxpay', 'state' => $state];
}
