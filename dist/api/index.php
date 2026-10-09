<?php
declare(strict_types=1);

/**
 * 前端控制器。所有 /api/* 请求都经过这里。
 * 路由清单见 README-API.md。全部返回 JSON。
 */

require_once __DIR__ . '/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once LFUN_API_DIR . '/lib/settings.php';
require_once LFUN_API_DIR . '/lib/users.php';
require_once LFUN_API_DIR . '/lib/content.php';
require_once LFUN_API_DIR . '/lib/panels.php';
require_once LFUN_API_DIR . '/lib/pans.php';
require_once LFUN_API_DIR . '/lib/unlocks.php';
require_once LFUN_API_DIR . '/lib/orders.php';
require_once LFUN_API_DIR . '/lib/interpret.php';
require_once LFUN_API_DIR . '/lib/payments.php';

/**
 * 解析路由路径。优先看 ?r=（没有 rewrite 时的兜底），否则从 REQUEST_URI 去掉 /api 前缀。
 * 注意：不能用 PATH_INFO / SCRIPT_NAME，因为同一目录下的其它入口脚本
 * （例如 api/admin/index.php）会改变这两个值，导致路由被截错。
 */
function route_path(): string
{
    $r = isset($_GET['r']) ? (string) $_GET['r'] : '';
    if ($r !== '') {
        return trim($r, '/');
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $uri = explode('?', $uri)[0];

    // URL 里显式带 index.php（未开启 rewrite 的部署）
    $marker = '/index.php';
    $pos = strpos($uri, $marker);
    if ($pos !== false) {
        return trim(substr($uri, $pos + strlen($marker)), '/');
    }

    // 用本文件所在目录相对站点根目录得到 API 前缀，例如 /api
    $apiDir = str_replace('\\', '/', (string) realpath(__DIR__));
    $docRoot = (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot !== '') {
        $docRoot = str_replace('\\', '/', $docRoot);
        if (strpos($apiDir, $docRoot) === 0) {
            $base = '/' . trim(substr($apiDir, strlen($docRoot)), '/');
            $base = rtrim($base, '/');
            if ($base !== '' && strpos($uri, $base) === 0) {
                $uri = substr($uri, strlen($base));
                return trim($uri, '/');
            }
        }
    }

    // 兜底：把 /api 前缀直接去掉（兼容子目录部署之外的场景）
    if (strpos($uri, '/api') === 0) {
        return trim(substr($uri, 4), '/');
    }
    return trim($uri, '/');
}

function route_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

/** 需要写权限的接口：若还没有身份，则按 device_id 懒创建（PRD 模块一 1.1）。 */
function require_or_create_user(): array
{
    $token = lfun_header('X-Auth-Token');
    $deviceId = lfun_header('X-Device-Id');
    if ($token === '') {
        $token = (string) ($_COOKIE[(string) cfg('security.token_cookie_name', 'lfun_tok')] ?? '');
    }
    if ($deviceId === '') {
        $deviceId = lfun_str_param('device_id', '');
    }
    if ($deviceId === '') {
        $deviceId = lfun_uuid4();
    }
    if ($token === '') {
        $token = lfun_token();
    }
    $user = user_resolve($token, $deviceId, true);
    if (!$user) {
        lfun_fail('server_error', '无法创建用户会话', 500);
    }
    user_set_cookie($token);
    return ['user' => $user, 'token' => $token, 'device_id' => $deviceId];
}

function auth_payload(array $ctx): array
{
    return ['auth' => ['token' => $ctx['token'], 'device_id' => $ctx['device_id']]];
}

function lang_of($value): string
{
    $v = is_string($value) ? strtolower($value) : 'zh';
    return strpos($v, 'en') === 0 ? 'en' : 'zh';
}

/** 解读语言：请求参数优先，否则用盘面创建时的语言（PRD 模块二 2.5 英文入口同样可用）。 */
function resolve_lang(array $pan): string
{
    $requested = lfun_str_param('lang', '');
    if ($requested !== '') {
        return lang_of($requested);
    }
    return lang_of($pan['lang'] ?? 'zh');
}

function pan_payload(array $pan, string $lang, array $user): array
{
    $type = (string) $pan['question_type'];
    $brief = content_brief($lang, $type, $pan);
    $state = feature_enabled('paywall')
        ? unlock_state($user, $pan)
        : ['unlocked' => true, 'reason' => 'paywall_off'];
    $sub = content_subtype($lang, $type, trim((string) $pan['question'] . ' ' . (string) $pan['details']));
    return [
        'pan'      => pan_public($pan, (bool) $state['unlocked']),
        'brief'    => $brief,
        'subtype'  => $sub,
        'unlocked' => (bool) $state['unlocked'],
        'unlock_reason' => $state['reason'],
        'issue'    => ['category' => $type, 'label' => content_category(content_library($lang), $type)['label']],
    ];
}

/* ------------------------------------------------------------------ */

$path = route_path();
$method = route_method();
$segments = $path === '' ? [] : explode('/', $path);

// 管理后台 JSON 接口（/api/admin/*）。仪表盘本身是 /api/admin/ 目录下的静态页面。
if (preg_match('#^admin(/|$)#', $path)) {
    require_once LFUN_API_DIR . '/lib/admin.php';
    admin_dispatch($path, $method);
    exit;
}

$routes = [];

$routes[] = ['GET', '#^health$#', function () {
    lfun_ok([
        'status'   => 'ok',
        'driver'   => db_driver(),
        'time'     => lfun_iso8601(),
        'features' => [
            'paywall'    => feature_enabled('paywall'),
            'membership' => feature_enabled('membership'),
            'cloud_sync' => feature_enabled('cloud_sync'),
            'donation'   => feature_enabled('donation'),
            'referral'   => feature_enabled('referral'),
        ],
    ]);
}];

$routes[] = ['GET', '#^config$#', function () {
    lfun_ok(['config' => public_config()]);
}];

/* 会话：返回/创建匿名设备用户，token 同时写入 cookie（PRD 模块一 1.2）。 */
$routes[] = ['POST', '#^session$#', function () {
    $ctx = require_or_create_user();
    lfun_ok(array_merge(auth_payload($ctx), ['user' => user_public($ctx['user'])]));
}];

$routes[] = ['GET', '#^me$#', function () {
    $ctx = require_or_create_user();
    $user = $ctx['user'];
    $orders = array_map('order_public', order_list_for_user((int) $user['id'], 30));
    $pans = [];
    foreach (pan_list($user, 30) as $p) {
        $pans[] = pan_public($p, true);
    }
    lfun_ok([
        'user'   => user_public($user),
        'orders' => $orders,
        'pans'   => $pans,
        'auth'   => ['token' => $ctx['token'], 'device_id' => $ctx['device_id']],
    ]);
}];

/* 起盘（免费，PRD 模块二 2.4）。 */
$routes[] = ['POST', '#^pan$#', function () {
    lfun_rate_limit('pan', 120, 60);
    $ctx = require_or_create_user();
    $lang = lang_of(lfun_param('lang', 'zh'));
    $question = lfun_text(lfun_str_param('question', ''), 300);
    $details = lfun_text(lfun_str_param('details', ''), 300);
    if (mb_strlen($question, 'UTF-8') < 2) {
        lfun_fail('invalid_question', '请写下至少两个字的具体问题。', 400);
    }
    $iso = lfun_str_param('iso', '');
    if ($iso === '') {
        $iso = gmdate('c');
    }
    try {
        $pan = pan_build(
            $iso,
            lfun_param('lunar_month') !== null ? (string) lfun_param('lunar_month') : null,
            lfun_param('lunar_day') !== null ? (string) lfun_param('lunar_day') : null,
            lfun_param('lunar_leap') !== null ? (bool) lfun_param('lunar_leap') : null
        );
    } catch (InvalidArgumentException $e) {
        lfun_fail('invalid_time', $e->getMessage(), 400);
    } catch (Throwable $e) {
        lfun_fail('calendar_unavailable', '服务器暂时无法换算农历，请稍后重试。', 503);
    }
    $selected = lfun_str_param('category', 'auto');
    $classified = content_classify($lang, $question . ' ' . $details, $selected);
    $type = (string) $classified['category'];
    $record = pan_store(
        $ctx['user'], $pan, $question, $details, $type, $lang,
        lfun_str_param('local_id', '') !== '' ? lfun_str_param('local_id') : null
    );
    $payload = pan_payload($record, $lang, $ctx['user']);
    $payload = array_merge($payload, auth_payload($ctx));
    $payload['classified'] = $classified;
    $payload['clarify'] = content_clarify($lang, $type);
    lfun_ok($payload);
}];

$routes[] = ['GET', '#^pan/([A-Za-z0-9]+)$#', function ($panKey) {
    $ctx = require_or_create_user();
    $pan = pan_by_key($panKey);
    if (!$pan) {
        lfun_fail('not_found', '找不到该盘面。', 404);
    }
    if ((int) $pan['user_id'] !== (int) $ctx['user']['id']) {
        lfun_fail('forbidden', '无权访问该盘面。', 403);
    }
    $lang = resolve_lang($pan);
    lfun_ok(pan_payload($pan, $lang, $ctx['user']));
}];

/* 付费解锁（PRD 模块二 2.4）。未付费返回 402，不下发文本。 */
$unlockHandler = function () {
    $ctx = require_or_create_user();
    $panKey = lfun_str_param('pan_key', '');
    if ($panKey === '') {
        lfun_fail('invalid_request', '缺少 pan_key。', 400);
    }
    $pan = pan_by_key($panKey);
    if (!$pan) {
        lfun_fail('not_found', '找不到该盘面。', 404);
    }
    if ((int) $pan['user_id'] !== (int) $ctx['user']['id']) {
        lfun_fail('forbidden', '该盘面不属于当前设备，无权查看。', 403);
    }
    $lang = resolve_lang($pan);
    $state = feature_enabled('paywall') ? unlock_state($ctx['user'], $pan) : ['unlocked' => true, 'reason' => 'paywall_off'];
    if (!$state['unlocked']) {
        $p = pricing();
        lfun_fail('payment_required', '该盘面的详细解读尚未解锁。', 402, [
            'paywall' => [
                'price_cny'   => lfun_cents_to_cny((int) $p['single_unlock_cents']),
                'price_cents' => (int) $p['single_unlock_cents'],
                'member_cny'  => lfun_cents_to_cny((int) $p['member_monthly_cents']),
                'member_cents' => (int) $p['member_monthly_cents'],
                'pan_key'     => $panKey,
            ],
        ]);
    }
    db_exec('UPDATE divination_pans SET unlocked = 1, updated_at = ? WHERE id = ?', [lfun_now(), (int) $pan['id']]);
    $record = db_one('SELECT * FROM divination_pans WHERE id = ?', [(int) $pan['id']]);
    $detail = interpret_detail($lang, (string) $record['question_type'], $record, (string) $record['question'], (string) $record['details']);
    lfun_ok(array_merge(auth_payload($ctx), [
        'unlock' => ['reason' => $state['reason']],
        'pan'    => pan_public($record, true),
        'detail' => $detail,
    ]));
};
$routes[] = ['POST', '#^unlock$#', $unlockHandler];
$routes[] = ['GET', '#^pans/([A-Za-z0-9]+)/interpretation$#', function ($panKey) use ($unlockHandler) {
    $_GET['pan_key'] = $panKey;
    unset($_GET['r']);
    $unlockHandler();
}];

/* 订单（PRD 模块一 1.4）。 */
$routes[] = ['POST', '#^orders$#', function () {
    lfun_rate_limit('order', 40, 60);
    $ctx = require_or_create_user();
    $type = lfun_str_param('type', '');
    if (!in_array($type, LFUN_ORDER_TYPES, true)) {
        lfun_fail('invalid_type', '订单类型无效。', 400);
    }
    $channel = lfun_str_param('channel', payment_default_channel());
    if (!payment_channel_enabled($channel)) {
        $channel = payment_default_channel();
    }
    $panKey = lfun_str_param('pan_key', '');
    $panId = null;
    if ($type === 'single_unlock') {
        $pan = pan_by_key($panKey);
        if (!$pan) {
            lfun_fail('not_found', '找不到该盘面，请重新起盘。', 404);
        }
        if ((int) $pan['user_id'] !== (int) $ctx['user']['id']) {
            lfun_fail('forbidden', '该盘面不属于当前设备。', 403);
        }
        $panId = (int) $pan['id'];
    }
    if ($type === 'donation' && !feature_enabled('donation')) {
        lfun_fail('disabled', '打赏功能未开启。', 400);
    }
    if ($type === 'member_monthly' && !feature_enabled('membership')) {
        lfun_fail('disabled', '会员功能未开启。', 400);
    }
    try {
        $result = order_create($ctx['user'], $type, $channel, lfun_param('amount_cents'), $panKey !== '' ? $panKey : null, $panId);
    } catch (InvalidArgumentException $e) {
        lfun_fail('invalid_request', $e->getMessage(), 400);
    }
    if (!empty($result['already_unlocked'])) {
        lfun_ok(array_merge(auth_payload($ctx), ['already_unlocked' => true, 'order' => null]));
    }
    $order = $result['order'];
    $pay = payment_create($order);
    lfun_ok(array_merge(auth_payload($ctx), [
        'already_unlocked' => false,
        'order' => order_public($order),
        'pay'   => $pay,
        'notice' => [
            'pay'    => (string) cfg('compliance.pay_notice', ''),
            'minor'  => (string) cfg('compliance.minor_notice', ''),
            'refund' => (string) cfg('compliance.refund_notice', ''),
        ],
    ]));
}];

$routes[] = ['GET', '#^orders/(\d+)$#', function ($id) {
    $ctx = require_or_create_user();
    $order = order_own($ctx['user'], (int) $id);
    if (!$order) {
        lfun_fail('not_found', '订单不存在。', 404);
    }
    $payload = ['order' => order_public($order)];
    if ($order['status'] === 'pending') {
        $payload['pay'] = payment_create($order);
    }
    if ($order['pan_key']) {
        $pan = pan_by_key((string) $order['pan_key']);
        if ($pan) {
            $payload['pan'] = pan_public($pan, (bool) $pan['unlocked']);
        }
    }
    lfun_ok(array_merge(auth_payload($ctx), $payload));
}];

$routes[] = ['POST', '#^orders/(\d+)/claim$#', function ($id) {
    $ctx = require_or_create_user();
    $order = order_own($ctx['user'], (int) $id);
    if (!$order) {
        lfun_fail('not_found', '订单不存在。', 404);
    }
    $order = order_claim($order);
    lfun_ok(array_merge(auth_payload($ctx), [
        'order' => order_public($order),
        'message' => $order['status'] === 'paid'
            ? '已确认到账，详细解读已解锁。'
            : '已记录，管理员核对到账后会立即开通，请稍后刷新。',
    ]));
}];

/* 支付回调（PRD 模块一 1.4）：验签 + 幂等。 */
$routes[] = ['POST', '#^pay/notify/([a-z]+)$#', function ($channel) {
    lfun_rate_limit('notify', 240, 60);
    payment_handle_notify($channel);
}];

/* 云同步（PRD 模块四 4.4）。 */
$routes[] = ['POST', '#^pans/sync$#', function () {
    $ctx = require_or_create_user();
    if (!feature_enabled('cloud_sync')) {
        lfun_fail('disabled', '云端同步未开启。', 400);
    }
    $items = lfun_param('items', []);
    if (!is_array($items)) {
        lfun_fail('invalid_request', 'items 必须是数组。', 400);
    }
    $result = pan_sync($ctx['user'], array_slice($items, 0, 200));
    lfun_ok(array_merge(auth_payload($ctx), $result));
}];

$routes[] = ['GET', '#^pans$#', function () {
    $ctx = require_or_create_user();
    if (!feature_enabled('cloud_sync')) {
        lfun_fail('disabled', '云端同步未开启。', 400);
    }
    $limit = lfun_int_param('limit', 50);
    $before = lfun_int_param('before_id', 0);
    $lang = lang_of(lfun_param('lang', 'zh'));
    $out = [];
    foreach (pan_list($ctx['user'], $limit, $before) as $p) {
        $out[] = pan_public($p, true);
    }
    lfun_ok(array_merge(auth_payload($ctx), ['pans' => $out]));
}];

/* 分享回流统计（PRD 模块六 6.2）。 */
$routes[] = ['POST', '#^share$#', function () {
    lfun_rate_limit('share', 60, 60);
    if (!feature_enabled('referral')) {
        lfun_ok(['recorded' => false]);
    }
    $panKey = lfun_str_param('pan_key', '');
    if ($panKey === '') {
        lfun_fail('invalid_request', '缺少 pan_key。', 400);
    }
    db_exec(
        'INSERT INTO share_clicks (pan_key, referrer, ip_hash, ua_hash, created_at) VALUES (?, ?, ?, ?, ?)',
        [$panKey, lfun_text(lfun_str_param('ref', ''), 240), lfun_ip_hash(), lfun_ua_hash(), lfun_now()]
    );
    lfun_ok(['recorded' => true]);
}];

/* 掉单补偿（可通过 cron 调用，带安装令牌）。 */
$routes[] = ['POST', '#^tasks/reconcile$#', function () {
    $token = lfun_str_param('token', '');
    $expected = (string) cfg('app.install_token', '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        lfun_fail('forbidden', '令牌无效。', 403);
    }
    $results = orders_pending_reconcile(20);
    lfun_ok(['checked' => count($results), 'results' => array_map(function ($r) {
        return ['out_trade_no' => $r['order']['out_trade_no'], 'status' => $r['query']['status'] ?? 'unknown'];
    }, $results)]);
}];

foreach ($routes as $route) {
    [$m, $pattern, $handler] = $route;
    if ($m !== $method) {
        continue;
    }
    if (preg_match($pattern, $path, $matches)) {
        array_shift($matches);
        $handler(...$matches);
        exit;
    }
}

lfun_fail('not_found', '接口不存在：' . $path, 404);
