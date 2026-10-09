<?php
declare(strict_types=1);

/**
 * 定价与功能开关：以 config.local.php 为默认值，允许后台在不改代码的情况下覆盖（PRD §7）。
 */
function setting_cache(?string $reset = null): array
{
    if ($reset !== null) {
        $GLOBALS['LFUN_SETTINGS_CACHE'] = [];
        return [];
    }
    if (!isset($GLOBALS['LFUN_SETTINGS_CACHE']) || !is_array($GLOBALS['LFUN_SETTINGS_CACHE'])) {
        $GLOBALS['LFUN_SETTINGS_CACHE'] = [];
        try {
            foreach (db_all('SELECT k, v FROM settings') as $row) {
                $GLOBALS['LFUN_SETTINGS_CACHE'][$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            $GLOBALS['LFUN_SETTINGS_CACHE'] = [];
        }
    }
    return $GLOBALS['LFUN_SETTINGS_CACHE'];
}

function setting_get(string $key, $default = null)
{
    $cache = setting_cache();
    if (array_key_exists($key, $cache)) {
        $decoded = json_decode((string) $cache[$key], true);
        return $decoded === null && trim((string) $cache[$key]) !== 'null' ? $cache[$key] : $decoded;
    }
    return $default;
}

function setting_set(string $key, $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    $existing = null;
    try {
        $existing = db_value('SELECT k FROM settings WHERE k = ?', [$key]);
    } catch (Throwable $e) {
        $existing = null;
    }
    if ($existing === null) {
        db_exec('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, ?)', [$key, $json, lfun_now()]);
    } else {
        db_exec('UPDATE settings SET v = ?, updated_at = ? WHERE k = ?', [$json, lfun_now(), $key]);
    }
    // 立即失效缓存，保证同一请求内后续读取拿到新值。
    setting_cache('reset');
    $GLOBALS['LFUN_SETTINGS_CACHE'][$key] = $json;
}

/** 合并后的定价（后台覆盖 > 配置文件默认值 > 内置兜底）。 */
function pricing(): array
{
    $defaults = (array) cfg('pricing', []);
    $overrides = setting_get('pricing', []);
    if (!is_array($overrides)) {
        $overrides = [];
    }
    $p = array_merge([
        'single_unlock_cny'    => 9.9,
        'member_monthly_cny'   => 29,
        'donation_presets_cny' => [2, 5, 10],
        'donation_min_cny'     => 1,
        'donation_max_cny'     => 200,
    ], $defaults, $overrides);

    $p['single_unlock_cents']  = lfun_cny_to_cents($p['single_unlock_cny']);
    $p['member_monthly_cents'] = lfun_cny_to_cents($p['member_monthly_cny']);
    $p['donation_min_cents']   = lfun_cny_to_cents($p['donation_min_cny']);
    $p['donation_max_cents']   = lfun_cny_to_cents($p['donation_max_cny']);
    if (!is_array($p['donation_presets_cny']) || !$p['donation_presets_cny']) {
        $p['donation_presets_cny'] = [2, 5, 10];
    }
    $p['donation_presets_cny'] = array_values(array_map(fn($v) => (float) $v, $p['donation_presets_cny']));
    return $p;
}

function feature_enabled(string $name): bool
{
    $override = setting_get('features', []);
    if (is_array($override) && array_key_exists($name, $override)) {
        return (bool) $override[$name];
    }
    return (bool) cfg('features.' . $name, true);
}

/** PayPal 人工收款配置：配置文件默认值 < 后台覆盖（settings 表的 paypal_manual）。 */
function paypal_manual_cfg(): array
{
    $base = (array) cfg('pay.manual.paypal', []);
    $over = setting_get('paypal_manual', []);
    if (!is_array($over)) {
        $over = [];
    }
    $p = array_merge([
        'enabled'     => true,
        'me'          => '',
        'currency'    => 'USD',
        'usd_per_cny' => 0.14,
    ], $base, $over);
    $p['enabled'] = (bool) $p['enabled'];
    $p['me'] = trim((string) $p['me']);
    $p['currency'] = strtoupper(trim((string) $p['currency'])) ?: 'USD';
    $p['usd_per_cny'] = max(0.0, (float) $p['usd_per_cny']);
    return $p;
}

/** 对外公开的配置（前端启动时拉取，价格与文案集中下发）。 */
function public_config(): array
{
    $p = pricing();
    $manual = (array) cfg('pay.manual', []);
    $defaultChannel = (string) cfg('pay.default_channel', 'manual');
    $channels = [];
    if (!empty($manual['enabled'])) {
        $channels[] = 'manual';
    }
    foreach (['wxpay', 'alipay'] as $c) {
        if (!empty(cfg('pay.' . $c . '.enabled', false))) {
            $channels[] = $c;
        }
    }
    if (!in_array($defaultChannel, $channels, true)) {
        $defaultChannel = $channels[0] ?? 'manual';
    }
    $pp = paypal_manual_cfg();
    return [
        'name'     => (string) cfg('app.name', '问时 · 小六壬'),
        'version'  => '2026.09.29-monetization',
        'pricing'  => [
            'single_unlock'    => lfun_cents_to_cny($p['single_unlock_cents']),
            'single_unlock_cny' => $p['single_unlock_cny'],
            'member_monthly'   => lfun_cents_to_cny($p['member_monthly_cents']),
            'member_monthly_cny' => $p['member_monthly_cny'],
            'donation_presets' => $p['donation_presets_cny'],
            'donation_min'     => lfun_cents_to_cny($p['donation_min_cents']),
            'donation_max'     => lfun_cents_to_cny($p['donation_max_cents']),
        ],
        'features' => [
            'paywall'    => feature_enabled('paywall'),
            'membership' => feature_enabled('membership'),
            'cloud_sync' => feature_enabled('cloud_sync'),
            'donation'   => feature_enabled('donation'),
            'referral'   => feature_enabled('referral'),
        ],
        'pay' => [
            'default_channel' => $defaultChannel,
            'channels'        => $channels,
            'manual'          => [
                'wx_qr'       => (string) ($manual['wx_qr'] ?? ''),
                'alipay_qr'   => (string) ($manual['alipay_qr'] ?? ''),
                'instructions' => (string) ($manual['instructions'] ?? ''),
                'auto_grant'  => (bool) ($manual['auto_grant'] ?? false),
                'paypal'      => [
                    'enabled'  => $pp['enabled'] && $pp['me'] !== '',
                    'currency' => $pp['currency'],
                ],
            ],
        ],
        'compliance' => (array) cfg('compliance', []),
        'support'    => [
            'contact' => (string) cfg('support.contact', ''),
            'manual_wait_minutes' => max(1, (int) cfg('support.manual_wait_minutes', 30)),
        ],
    ];
}
