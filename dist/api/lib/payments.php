<?php
declare(strict_types=1);

require_once LFUN_API_DIR . '/lib/channels/manual.php';
require_once LFUN_API_DIR . '/lib/channels/wxpay.php';
require_once LFUN_API_DIR . '/lib/channels/alipay.php';

/**
 * 支付渠道分发。PRD 模块一：创建订单 -> 前端轮询 -> 回调验签 + 幂等 -> 掉单补偿。
 * 渠道实现互相独立；默认走人工收款（PRD §0 A1 的替代方案），拿到商户号后把 enabled 打开即可。
 */
function payment_channels(): array
{
    $out = [];
    if (!empty(cfg('pay.manual.enabled', false))) {
        $out[] = 'manual';
    }
    if (!empty(cfg('pay.wxpay.enabled', false))) {
        $out[] = 'wxpay';
    }
    if (!empty(cfg('pay.alipay.enabled', false))) {
        $out[] = 'alipay';
    }
    return $out;
}

function payment_default_channel(): string
{
    $channels = payment_channels();
    $default = (string) cfg('pay.default_channel', 'manual');
    if (in_array($default, $channels, true)) {
        return $default;
    }
    return $channels[0] ?? 'manual';
}

function payment_channel_enabled(string $channel): bool
{
    return in_array($channel, payment_channels(), true);
}

/** 创建支付参数（下单后前端拿它去唤起支付或展示收款码）。 */
function payment_create(array $order): array
{
    $channel = (string) $order['pay_channel'];
    switch ($channel) {
        case 'wxpay':
            return ['channel' => $channel, 'params' => wxpay_create($order)];
        case 'alipay':
            return ['channel' => $channel, 'params' => alipay_create($order)];
        case 'manual':
        default:
            return ['channel' => 'manual', 'params' => manual_params($order)];
    }
}

/** 主动查询渠道订单状态（掉单补偿用）。 */
function payment_query(array $order): array
{
    switch ((string) $order['pay_channel']) {
        case 'wxpay':
            return wxpay_query($order);
        case 'alipay':
            return alipay_query($order);
        default:
            return ['status' => 'pending', 'channel' => 'manual'];
    }
}

/** 处理支付回调：验签失败直接拒绝，且不修改订单（PRD 模块一 1.5）。 */
function payment_handle_notify(string $channel): void
{
    if (!payment_channel_enabled($channel)) {
        lfun_json(['code' => 'FAIL', 'message' => 'channel disabled'], 400);
    }
    switch ($channel) {
        case 'wxpay':
            wxpay_handle_notify();
            break;
        case 'alipay':
            alipay_handle_notify();
            break;
        default:
            lfun_json(['code' => 'FAIL', 'message' => 'manual channel has no notify'], 400);
    }
}

/** 简易 HTTP 客户端（curl 优先，回退 file_get_contents）。 */
function payment_http(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 15): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        // 不调用 curl_close()：PHP 8.0 起它已是空操作，8.5 起触发废弃告警并污染 JSON 响应。
        return ['status' => $code, 'body' => is_string($resp) ? $resp : '', 'error' => $err];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    // 无 curl 时用流包装器；通过 stream_get_meta_data 取响应头，避免使用已废弃的 $http_response_header。
    $fp = @fopen($url, 'r', false, $ctx);
    if ($fp === false) {
        return ['status' => 0, 'body' => '', 'error' => 'request failed'];
    }
    $meta = stream_get_meta_data($fp);
    $resp = stream_get_contents($fp);
    fclose($fp);
    $status = 0;
    $wrapper = is_array($meta) && isset($meta['wrapper_data']) && is_array($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
    foreach ($wrapper as $line) {
        if (is_string($line) && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'body' => is_string($resp) ? $resp : '', 'error' => is_string($resp) ? '' : 'request failed'];
}
