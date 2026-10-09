<?php
declare(strict_types=1);

/** PayPal 人工收款信息：按订单人民币金额 × 展示汇率生成 paypal.me 付款链接。 */
function paypal_manual_block(array $order): array
{
    $pp = paypal_manual_cfg();
    $amountUsd = $pp['usd_per_cny'] > 0
        ? round(lfun_cents_to_cny((int) $order['amount_cents']) * $pp['usd_per_cny'], 2)
        : 0.0;
    $enabled = $pp['enabled'] && $pp['me'] !== '' && $amountUsd > 0;
    return [
        'enabled'  => $enabled,
        'pay_url'  => $enabled
            ? 'https://www.paypal.com/paypalme/' . rawurlencode($pp['me']) . '/' . number_format($amountUsd, 2, '.', '')
            : '',
        'amount'   => number_format(max(0, $amountUsd), 2, '.', ''),
        'currency' => $pp['currency'],
    ];
}

/** 人工收款渠道：展示收款码，用户点击“我已付款”，管理员核对后开通。 */
function manual_params(array $order): array
{
    $manual = (array) cfg('pay.manual', []);
    $base = rtrim((string) cfg('app.base_url', ''), '/');
    $qr = (string) ($manual['wx_qr'] ?? '');
    $alipayQr = (string) ($manual['alipay_qr'] ?? '');
    $abs = function (string $p) use ($base): string {
        if ($p === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        return $base !== '' ? $base . '/' . ltrim($p, '/') : '/' . ltrim($p, '/');
    };
    return [
        'type'         => 'manual',
        'out_trade_no' => (string) $order['out_trade_no'],
        'amount_cny'   => lfun_cents_to_cny((int) $order['amount_cents']),
        'wx_qr'        => $abs($qr),
        'alipay_qr'    => $abs($alipayQr),
        'paypal'       => paypal_manual_block($order),
        'instructions' => (string) ($manual['instructions'] ?? ''),
        'auto_grant'   => (bool) ($manual['auto_grant'] ?? false),
    ];
}
