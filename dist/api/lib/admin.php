<?php
declare(strict_types=1);

/** 管理后台：登录、订单核销、退款、文案库与定价维护。 */

require_once LFUN_API_DIR . '/lib/settings.php';
require_once LFUN_API_DIR . '/lib/notify.php';

const LFUN_ADMIN_COOKIE = 'lfun_admin';

function admin_secret(): string
{
    $s = (string) cfg('app.install_token', '');
    if ($s === '') {
        // 绝不回退到硬编码常量：否则任何人都能伪造后台会话。缺 token 时后台整体不可用。
        throw new RuntimeException('app.install_token 未配置，后台会话无法签名');
    }
    return $s;
}

/* ----------------------------- 后台访问闸门 ----------------------------- */
/*
 * 三层防护，按建议顺序：
 *   1) 服务器层：Hostinger hPanel「目录隐私」加 Basic Auth（最稳，PHP 之前就拦掉）
 *   2) 本层：IP 允许列表 + 强制 HTTPS
 *   3) 应用层：强随机 install_token 签名的后台 Cookie（SameSite=Strict / HttpOnly）
 */

/** 判断 IP 是否命中允许列表（支持 IPv4/IPv6 与 CIDR）。空列表 = 不限制。 */
function admin_ip_in_allowlist(string $ip, array $list): bool
{
    if (!$list) {
        return true;
    }
    $ip = trim($ip);
    if ($ip === '') {
        return false;
    }
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($list as $entry) {
        $entry = trim((string) $entry);
        if ($entry === '') {
            continue;
        }
        if (strpos($entry, '/') === false) {
            $one = @inet_pton($entry);
            if ($one !== false && $one === $bin) {
                return true;
            }
            continue;
        }
        $parts = explode('/', $entry, 2);
        $net = @inet_pton(trim($parts[0]));
        if ($net === false || strlen($net) !== strlen($bin)) {
            continue;
        }
        $bits = (int) trim($parts[1]);
        $max = strlen($net) * 8;
        if ($bits < 0 || $bits > $max) {
            continue;
        }
        $whole = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($whole > 0 && substr($bin, 0, $whole) !== substr($net, 0, $whole)) {
            continue;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        if ((ord($bin[$whole]) & $mask) === (ord($net[$whole]) & $mask)) {
            return true;
        }
    }
    return false;
}

function admin_is_loopback(string $ip): bool
{
    $bin = @inet_pton(trim($ip));
    if ($bin === false) {
        return false;
    }
    return $bin === inet_pton('127.0.0.1') || $bin === inet_pton('::1');
}

/**
 * 客户端 IP。默认只看 REMOTE_ADDR（不可伪造）；
 * 只有当 admin.trust_proxy = true（前面确实有可信代理/CDN）时才采信转发头。
 */
function admin_client_ip(): string
{
    if ((bool) cfg('admin.trust_proxy', false)) {
        return lfun_client_ip();
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** 是否通过 HTTPS 访问。CDN 终止 TLS 时会由 X-Forwarded-Proto 反映出来。 */
function admin_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/** 返回 null 表示放行；否则返回 [错误码, 提示]。 */
function admin_guard_check(): ?array
{
    if ((string) cfg('app.install_token', '') === '') {
        return ['admin_not_configured', '后台未完成配置：请在 api/config.local.php 设置 app.install_token（用于签名后台会话）。缺失时后台拒绝服务，以免会话被伪造。'];
    }
    $ip = admin_client_ip();
    if ((bool) cfg('admin.require_https', true) && !admin_is_loopback($ip) && !admin_is_https()) {
        return ['admin_https_required', '后台仅允许通过 HTTPS 访问，请改用 https:// 打开。若本站由 CDN 终止 TLS 仍被拒绝，可把 admin.require_https 设为 false。'];
    }
    $list = (array) cfg('admin.ip_allowlist', []);
    if (!admin_ip_in_allowlist($ip, $list)) {
        return ['admin_ip_blocked', '当前 IP（' . $ip . '）不在后台允许列表中。若你的公网 IP 变了，请在 api/config.local.php 的 admin.ip_allowlist 里更新。'];
    }
    return null;
}

/** 闸门不通过时直接返回 403（HTML 或 JSON 自适应）并结束请求。 */
function admin_guard_enforce(): void
{
    $deny = admin_guard_check();
    if ($deny === null) {
        return;
    }
    [$code, $message] = $deny;
    db_audit('guard', 'admin.' . $code, admin_client_ip());
    $wantsHtml = strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false;
    if (!$wantsHtml) {
        lfun_fail($code, $message, 403);
    }
    lfun_flush_buffers();
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="zh-CN"><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>403 · 问时后台</title>'
        . '<div style="max-width:660px;margin:12vh auto;padding:28px;border:1px solid #e5e5e7;border-radius:20px;'
        . 'font:15px/1.75 -apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;color:#1d1d1f">'
        . '<h1 style="margin:0 0 12px;font-size:20px">403 · 拒绝访问管理后台</h1>'
        . '<p style="margin:0;color:#4a4a4f">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:18px 0 0;color:#8a8a8e;font-size:13px">错误码：' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div></html>';
    exit;
}

function admin_token_issue(int $adminId, int $ttlSeconds = 43200): string
{
    $payload = json_encode(['id' => $adminId, 'exp' => time() + $ttlSeconds], JSON_UNESCAPED_SLASHES);
    $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    return $b64 . '.' . hash_hmac('sha256', $b64, admin_secret());
}

function admin_token_verify(?string $token): ?array
{
    if (!$token || strpos($token, '.') === false) {
        return null;
    }
    $parts = explode('.', $token, 2);
    try {
        $expected = hash_hmac('sha256', $parts[0], admin_secret());
    } catch (Throwable $e) {
        return null;   // install_token 未配置：一律视为未登录
    }
    if (!hash_equals($expected, $parts[1])) {
        return null;
    }
    $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['id']) || empty($data['exp']) || (int) $data['exp'] < time()) {
        return null;
    }
    return $data;
}

function admin_current(): ?array
{
    if (PHP_SAPI === 'cli') {
        return null;
    }
    $cookieName = (string) cfg('security.admin_cookie_name', LFUN_ADMIN_COOKIE);
    $token = (string) ($_COOKIE[$cookieName] ?? '');
    if ($token === '') {
        $token = lfun_header('X-Admin-Token');
    }
    $data = admin_token_verify($token !== '' ? $token : null);
    if (!$data) {
        return null;
    }
    $row = db_one('SELECT * FROM admin_users WHERE id = ?', [(int) $data['id']]);
    return $row ?: null;
}

function admin_require(): array
{
    $a = admin_current();
    if (!$a) {
        lfun_fail('unauthenticated', '请先登录管理后台。', 401);
    }
    return $a;
}

function admin_set_cookie(string $token): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie((string) cfg('security.admin_cookie_name', LFUN_ADMIN_COOKIE), $token, [
        'expires'  => time() + 43200,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/** 路由表：pattern => [method, handler] */
function admin_routes(): array
{
    return [
        '#^login$#'                  => ['POST', 'admin_route_login'],
        '#^logout$#'                 => ['POST', 'admin_route_logout'],
        '#^session$#'                => ['GET',  'admin_route_session'],
        '#^stats$#'                  => ['GET',  'admin_route_stats'],
        '#^orders$#'                 => ['GET',  'admin_route_orders'],
        '#^orders/(\d+)/confirm$#'   => ['POST', 'admin_route_confirm'],
        '#^orders/(\d+)/refund$#'    => ['POST', 'admin_route_refund'],
        '#^orders/(\d+)/fail$#'      => ['POST', 'admin_route_fail'],
        '#^pans$#'                   => ['GET',  'admin_route_pans'],
        '#^users$#'                  => ['GET',  'admin_route_users'],
        '#^pricing$#'                => ['GET',  'admin_route_pricing_get'],
        '#^pricing/save$#'           => ['POST', 'admin_route_pricing_save'],
        '#^features/save$#'          => ['POST', 'admin_route_features_save'],
        '#^interpretations$#'        => ['GET',  'admin_route_interp_list'],
        '#^interpretations/save$#'   => ['POST', 'admin_route_interp_save'],
        '#^interpretations/delete$#' => ['POST', 'admin_route_interp_delete'],
        '#^share$#'                  => ['GET',  'admin_route_share'],
        '#^sync/run$#'               => ['POST', 'admin_route_reconcile'],
        '#^notify/test$#'            => ['POST', 'admin_route_notify_test'],
    ];
}

function admin_dispatch(string $path, string $method): void
{
    admin_guard_enforce();
    $sub = trim(substr($path, strlen('admin')), '/');
    foreach (admin_routes() as $pattern => $def) {
        if (preg_match($pattern, $sub, $m)) {
            if ($def[0] !== $method) {
                lfun_fail('method_not_allowed', '请求方法不正确。', 405);
            }
            array_shift($m);
            // 正则捕获到的是字符串；strict_types 下需要转成 int 才能匹配 handler 的类型声明。
            $args = array_map(function ($v) {
                return is_string($v) && ctype_digit($v) ? (int) $v : $v;
            }, $m);
            $def[1](...$args);
            return;
        }
    }
    lfun_fail('not_found', '管理接口不存在：' . $sub, 404);
}

/* --------------------------- handlers --------------------------- */

function admin_route_login(): void
{
    lfun_rate_limit('admin_login', 10, 300);
    $username = lfun_str_param('username', '');
    $password = (string) lfun_param('password', '');
    $row = db_one('SELECT * FROM admin_users WHERE username = ?', [$username]);
    if (!$row || !password_verify($password, (string) $row['pass_hash'])) {
        db_audit('admin', 'login.fail', $username);
        lfun_fail('bad_credentials', '用户名或密码错误。', 401);
    }
    admin_set_cookie(admin_token_issue((int) $row['id']));
    db_audit('admin:' . $row['username'], 'login.ok');
    lfun_ok(['admin' => ['id' => (int) $row['id'], 'username' => $row['username']]]);
}

function admin_route_logout(): void
{
    if (PHP_SAPI !== 'cli') {
        setcookie((string) cfg('security.admin_cookie_name', LFUN_ADMIN_COOKIE), '', ['expires' => time() - 3600, 'path' => '/']);
    }
    lfun_ok();
}

function admin_route_session(): void
{
    $a = admin_current();
    lfun_ok([
        'authenticated' => (bool) $a,
        'admin' => $a ? ['id' => (int) $a['id'], 'username' => $a['username']] : null,
    ]);
}

function admin_route_stats(): void
{
    admin_require();
    $paid = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'paid'", [], 0);
    $pending = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0);
    $claimed = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND claimed_at IS NOT NULL", [], 0);
    $refunded = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'refunded'", [], 0);
    $revenue = (int) db_value("SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE status = 'paid' AND type <> 'donation'", [], 0);
    $donation = (int) db_value("SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE status = 'paid' AND type = 'donation'", [], 0);
    $users = (int) db_value('SELECT COUNT(*) FROM users', [], 0);
    $members = (int) db_value('SELECT COUNT(*) FROM users WHERE member_expire_at IS NOT NULL AND member_expire_at > ?', [lfun_now()], 0);
    $pans = (int) db_value('SELECT COUNT(*) FROM divination_pans', [], 0);
    $unlocks = (int) db_value('SELECT COUNT(*) FROM unlocks', [], 0);
    $shares = (int) db_value('SELECT COUNT(*) FROM share_clicks', [], 0);
    lfun_ok([
        'stats' => [
            'orders_paid' => $paid, 'orders_pending' => $pending, 'orders_claimed' => $claimed,
            'orders_refunded' => $refunded,
            'revenue_cents' => $revenue, 'revenue_cny' => lfun_cents_to_cny($revenue),
            'donation_cents' => $donation, 'donation_cny' => lfun_cents_to_cny($donation),
            'users' => $users, 'members' => $members, 'pans' => $pans, 'unlocks' => $unlocks,
            'share_clicks' => $shares,
            'unlock_rate' => $pans > 0 ? round($unlocks / $pans * 100, 2) : 0.0,
        ],
        'public_config' => public_config(),
        'notify' => [
            'enabled' => !empty(notify_config()['enabled']) && notify_config()['url'] !== '',
            'type'    => notify_config()['type'],
            'support' => (string) cfg('support.contact', ''),
            'manual_wait_minutes' => max(1, (int) cfg('support.manual_wait_minutes', 30)),
        ],
        'admin_guard' => [
            'ip_allowlist'  => array_values((array) cfg('admin.ip_allowlist', [])),
            'require_https' => (bool) cfg('admin.require_https', true),
            'trust_proxy'   => (bool) cfg('admin.trust_proxy', false),
            'client_ip'     => admin_client_ip(),
        ],
        'recent_audit' => db_all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 25'),
    ]);
}

function admin_route_notify_test(): void
{
    admin_require();
    if (notify_config()['url'] === '') {
        lfun_fail('not_configured', '还没有配置 notify.url（见 config.local.php.example 里的说明）。', 400);
    }
    $ok = lfun_notify_admin('【问时】测试通知', '如果你收到这条消息，说明「用户已付款」的推送已经配好了。');
    lfun_ok(['sent' => $ok, 'type' => notify_config()['type']]);
}

function admin_route_orders(): void
{
    admin_require();
    $status = lfun_str_param('status', 'all');
    $conds = [];
    $params = [];
    if ($status !== 'all') {
        $conds[] = 'o.status = ?';
        $params[] = $status;
    }
    if (lfun_str_param('claimed_only', '') === '1') {
        $conds[] = 'o.claimed_at IS NOT NULL';
    }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    $rows = db_all(
        'SELECT o.*, p.question, p.question_type, p.iso, u.device_id FROM orders o
         LEFT JOIN divination_pans p ON p.id = o.pan_id
         LEFT JOIN users u ON u.id = o.user_id '
        . $where . ' ORDER BY (o.status = \'pending\' AND o.claimed_at IS NOT NULL) DESC, o.id DESC LIMIT 200',
        $params
    );
    $out = [];
    foreach ($rows as $o) {
        $out[] = array_merge(order_public($o), [
            'question' => $o['question'], 'question_type' => $o['question_type'], 'iso' => $o['iso'],
            'device_id' => $o['device_id'],
        ]);
    }
    lfun_ok(['orders' => $out]);
}

function admin_route_confirm(int $id): void
{
    $admin = admin_require();
    $order = order_by_id($id);
    if (!$order) {
        lfun_fail('not_found', '订单不存在。', 404);
    }
    $result = order_mark_paid($order, null, 'admin:' . $admin['username']);
    lfun_ok(['order' => order_public($result['order']), 'first_time' => $result['first_time']]);
}

function admin_route_refund(int $id): void
{
    $admin = admin_require();
    $order = order_by_id($id);
    if (!$order) {
        lfun_fail('not_found', '订单不存在。', 404);
    }
    lfun_ok(['order' => order_public(order_refund($order, 'admin:' . $admin['username']))]);
}

function admin_route_fail(int $id): void
{
    $admin = admin_require();
    $order = order_by_id($id);
    if (!$order) {
        lfun_fail('not_found', '订单不存在。', 404);
    }
    order_mark_failed($order, 'admin:' . $admin['username']);
    lfun_ok(['order' => order_public(order_by_id($id))]);
}

function admin_route_pans(): void
{
    admin_require();
    $limit = max(1, min(200, lfun_int_param('limit', 50)));
    $rows = db_all('SELECT * FROM divination_pans ORDER BY id DESC LIMIT ' . $limit);
    lfun_ok(['pans' => array_map(function ($p) {
        return array_merge(pan_public($p, (bool) $p['unlocked']), ['user_id' => (int) $p['user_id']]);
    }, $rows)]);
}

function admin_route_users(): void
{
    admin_require();
    $limit = max(1, min(200, lfun_int_param('limit', 50)));
    $rows = db_all('SELECT * FROM users ORDER BY id DESC LIMIT ' . $limit);
    lfun_ok(['users' => array_map('user_public', $rows)]);
}

function admin_route_pricing_get(): void
{
    admin_require();
    lfun_ok([
        'pricing' => pricing(),
        'features' => [
            'paywall' => feature_enabled('paywall'),
            'membership' => feature_enabled('membership'),
            'cloud_sync' => feature_enabled('cloud_sync'),
            'donation' => feature_enabled('donation'),
            'referral' => feature_enabled('referral'),
        ],
    ]);
}

function admin_route_pricing_save(): void
{
    $admin = admin_require();
    $data = lfun_param('pricing', []);
    if (!is_array($data)) {
        lfun_fail('invalid_request', 'pricing 必须是对象。', 400);
    }
    $current = pricing();
    $next = [
        'single_unlock_cny'  => isset($data['single_unlock_cny']) ? max(0.01, (float) $data['single_unlock_cny']) : $current['single_unlock_cny'],
        'member_monthly_cny' => isset($data['member_monthly_cny']) ? max(0.01, (float) $data['member_monthly_cny']) : $current['member_monthly_cny'],
        'donation_min_cny'   => isset($data['donation_min_cny']) ? max(0.01, (float) $data['donation_min_cny']) : $current['donation_min_cny'],
        'donation_max_cny'   => isset($data['donation_max_cny']) ? max(0.01, (float) $data['donation_max_cny']) : $current['donation_max_cny'],
        'donation_presets_cny' => is_array($data['donation_presets_cny'] ?? null)
            ? array_values(array_map(fn($v) => (float) $v, $data['donation_presets_cny']))
            : $current['donation_presets_cny'],
    ];
    if ($next['donation_min_cny'] > $next['donation_max_cny']) {
        lfun_fail('invalid_request', '打赏下限不能大于上限。', 400);
    }
    setting_set('pricing', $next);
    db_audit('admin:' . $admin['username'], 'pricing.save', null, $next);
    lfun_ok(['pricing' => pricing()]);
}

function admin_route_features_save(): void
{
    $admin = admin_require();
    $data = lfun_param('features', []);
    if (!is_array($data)) {
        lfun_fail('invalid_request', 'features 必须是对象。', 400);
    }
    $allowed = ['paywall', 'membership', 'cloud_sync', 'donation', 'referral'];
    $next = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $data)) {
            $next[$k] = (bool) $data[$k];
        }
    }
    setting_set('features', $next);
    db_audit('admin:' . $admin['username'], 'features.save', null, $next);
    lfun_ok(['features' => $next]);
}

function admin_route_interp_list(): void
{
    admin_require();
    $type = lfun_str_param('question_type', '');
    $lang = lfun_str_param('lang', '');
    $conds = [];
    $params = [];
    if ($type !== '') {
        $conds[] = 'question_type = ?';
        $params[] = $type;
    }
    if ($lang !== '') {
        $conds[] = 'lang = ?';
        $params[] = $lang;
    }
    $sql = 'SELECT * FROM interpretations';
    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }
    $sql .= ' ORDER BY question_type, lang, pattern_key LIMIT 500';
    lfun_ok(['interpretations' => db_all($sql, $params)]);
}

function admin_route_interp_save(): void
{
    $admin = admin_require();
    $pattern = lfun_str_param('pattern_key', '');
    $type = lfun_str_param('question_type', '');
    $lang = lfun_str_param('lang', 'zh');
    $text = (string) lfun_param('rich_text', '');
    if ($pattern === '' || $type === '') {
        lfun_fail('invalid_request', 'pattern_key 与 question_type 必填。', 400);
    }
    $now = lfun_now();
    $existing = db_value(
        'SELECT id FROM interpretations WHERE pattern_key = ? AND question_type = ? AND lang = ?',
        [$pattern, $type, $lang]
    );
    if ($existing) {
        db_exec('UPDATE interpretations SET rich_text = ?, updated_at = ? WHERE id = ?', [$text, $now, (int) $existing]);
    } else {
        db_exec(
            'INSERT INTO interpretations (pattern_key, question_type, lang, rich_text, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$pattern, $type, $lang, $text, $now]
        );
    }
    db_audit('admin:' . $admin['username'], 'interpretation.save', $pattern, ['type' => $type, 'lang' => $lang]);
    lfun_ok();
}

function admin_route_interp_delete(): void
{
    $admin = admin_require();
    $pattern = lfun_str_param('pattern_key', '');
    $type = lfun_str_param('question_type', '');
    $lang = lfun_str_param('lang', 'zh');
    db_exec('DELETE FROM interpretations WHERE pattern_key = ? AND question_type = ? AND lang = ?', [$pattern, $type, $lang]);
    db_audit('admin:' . $admin['username'], 'interpretation.delete', $pattern, ['type' => $type, 'lang' => $lang]);
    lfun_ok();
}

function admin_route_share(): void
{
    admin_require();
    $rows = db_all(
        'SELECT pan_key, COUNT(*) AS clicks, MAX(created_at) AS last_at FROM share_clicks GROUP BY pan_key ORDER BY clicks DESC LIMIT 50'
    );
    lfun_ok(['share' => $rows]);
}

function admin_route_reconcile(): void
{
    admin_require();
    $results = orders_pending_reconcile(20);
    lfun_ok(['checked' => count($results), 'results' => array_map(function ($r) {
        return ['out_trade_no' => $r['order']['out_trade_no'], 'status' => $r['query']['status'] ?? 'unknown'];
    }, $results)]);
}
