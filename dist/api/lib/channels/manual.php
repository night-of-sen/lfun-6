<?php
declare(strict_types=1);

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
        'instructions' => (string) ($manual['instructions'] ?? ''),
        'auto_grant'   => (bool) ($manual['auto_grant'] ?? false),
    ];
}
