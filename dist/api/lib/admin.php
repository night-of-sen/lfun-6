<?php
declare(strict_types=1);

/** 管理后台：登录、订单核销、退款、文案库与定价维护。 */

require_once LFUN_API_DIR . '/lib/settings.php';
require_once LFUN_API_DIR . '/lib/notify.php';

const LFUN_ADMIN_COOKIE = 'lfun_admin';

function admin_secret(): string
{
    $s = (string) cfg('app.install_token', '');
    return $s !== '' ? $s : 'lfun-insecure-secret-change-me';
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
    if (!hash_equals(hash_hmac('sha256', $parts[0], admin_secret()), $parts[1])) {
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
