<?php
/* 后台访问闸门自检：CIDR 匹配（纯函数）+ admin_guard_check() 的各类情形。
 * 用法：php tests/verify-admin-guard.php */
declare(strict_types=1);
require_once __DIR__ . '/../dist/api/lib/bootstrap.php';
require_once __DIR__ . '/../dist/api/lib/settings.php';
require_once __DIR__ . '/../dist/api/lib/users.php';
require_once __DIR__ . '/../dist/api/lib/notify.php';
require_once __DIR__ . '/../dist/api/lib/admin.php';

$fail = 0;
function ck(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    printf("  %s  %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? ' :: ' . $detail : '');
    if (!$ok) {
        $fail++;
    }
}

echo "-- CIDR / IP 匹配（纯函数） --\n";
$cases = [
    [[], '1.2.3.4', true, '空列表 = 不限制'],
    [['1.2.3.4'], '1.2.3.4', true, '精确 IPv4'],
    [['1.2.3.4'], '1.2.3.5', false, 'IPv4 不匹配'],
    [['1.2.3.0/24'], '1.2.3.200', true, '/24 命中'],
    [['1.2.3.0/24'], '1.2.4.1', false, '/24 不命中'],
    [['1.2.3.4/32'], '1.2.3.4', true, '/32 精确'],
    [['0.0.0.0/0'], '203.0.113.9', true, '/0 全放行'],
    [['1.2.3.0/25'], '1.2.3.127', true, '/25 上边界内'],
    [['1.2.3.0/25'], '1.2.3.128', false, '/25 下边界外'],
    [['2001:db8::/32'], '2001:db8::1', true, 'IPv6 CIDR 命中'],
    [['2001:db8::/32'], '2001:db9::1', false, 'IPv6 CIDR 不命中'],
    [['::1'], '::1', true, 'IPv6 精确'],
    [['1.2.3.4'], '2001:db8::1', false, 'v4 列表不匹配 v6'],
    [['1.2.3.0/24'], '2001:db8::1', false, 'v4 CIDR 不匹配 v6'],
    [['not-an-ip', '1.2.3.4'], '1.2.3.4', true, '非法项被忽略，其余仍生效'],
    [['not-an-ip'], '1.2.3.4', false, '全是非法项则拒绝（fail closed）'],
    [['1.2.3.0/33'], '1.2.3.4', false, '越界掩码被拒'],
    [[''], '1.2.3.4', false, '空项不算命中'],
    [['1.2.3.4'], '', false, '空 IP 拒绝'],
    [['1.2.3.4'], 'garbage', false, '非法 IP 拒绝'],
];
foreach ($cases as $c) {
    ck($c[3], admin_ip_in_allowlist($c[1], $c[0]) === $c[2]);
}

echo "-- 回环地址识别 --\n";
ck('127.0.0.1 是回环', admin_is_loopback('127.0.0.1'));
ck('::1 是回环', admin_is_loopback('::1'));
ck('1.2.3.4 不是回环', !admin_is_loopback('1.2.3.4'));

echo "-- admin_guard_check() 情形 --\n";
$savedConfig = $GLOBALS['LFUN_CONFIG'];
$savedServer = $_SERVER;

function mockReq(string $ip, bool $https = true, string $xff = '', string $proto = ''): void
{
    $_SERVER['REMOTE_ADDR'] = $ip;
    $_SERVER['HTTPS'] = $https ? 'on' : 'off';
    $_SERVER['SERVER_PORT'] = $https ? '443' : '80';
    if ($xff !== '') { $_SERVER['HTTP_X_FORWARDED_FOR'] = $xff; } else { unset($_SERVER['HTTP_X_FORWARDED_FOR']); }
    if ($proto !== '') { $_SERVER['HTTP_X_FORWARDED_PROTO'] = $proto; } else { unset($_SERVER['HTTP_X_FORWARDED_PROTO']); }
}
function setAdmin(array $admin): void
{
    $GLOBALS['LFUN_CONFIG']['admin'] = array_merge([
        'ip_allowlist' => [], 'require_https' => true, 'trust_proxy' => false,
    ], $admin);
}
function setToken(string $token): void
{
    $GLOBALS['LFUN_CONFIG']['app']['install_token'] = $token;
}

$origToken = (string) cfg('app.install_token', '');

setToken('');
setAdmin([]);
mockReq('1.2.3.4');
ck('install_token 为空 → 拒绝（fail closed）', (admin_guard_check()[0] ?? '') === 'admin_not_configured');
setToken($origToken);

setAdmin(['require_https' => true]);
mockReq('1.2.3.4', false);
ck('HTTP + 非回环 → 拒绝', (admin_guard_check()[0] ?? '') === 'admin_https_required');
mockReq('127.0.0.1', false);
ck('HTTP + 回环 → 放行（本地开发）', admin_guard_check() === null);
mockReq('1.2.3.4', false, '', 'https');
ck('HTTP 但 CDN 报 X-Forwarded-Proto=https → 放行', admin_guard_check() === null);
setAdmin(['require_https' => false]);
mockReq('1.2.3.4', false);
ck('require_https=false → 放行', admin_guard_check() === null);

setAdmin(['require_https' => false, 'ip_allowlist' => ['1.2.3.4']]);
mockReq('1.2.3.4', true);
ck('白名单命中 → 放行', admin_guard_check() === null);
mockReq('9.9.9.9', true);
ck('白名单未命中 → 拒绝', (admin_guard_check()[0] ?? '') === 'admin_ip_blocked');

setAdmin(['require_https' => false, 'ip_allowlist' => ['1.2.3.4'], 'trust_proxy' => false]);
mockReq('9.9.9.9', true, '1.2.3.4');
ck('未开 trust_proxy 时忽略 X-Forwarded-For（防伪造）', (admin_guard_check()[0] ?? '') === 'admin_ip_blocked');
setAdmin(['require_https' => false, 'ip_allowlist' => ['1.2.3.4'], 'trust_proxy' => true]);
mockReq('9.9.9.9', true, '1.2.3.4');
ck('开启 trust_proxy 后采信 X-Forwarded-For', admin_guard_check() === null);

setAdmin(['require_https' => false, 'ip_allowlist' => ['10.0.0.0/8']]);
mockReq('10.1.2.3', true);
ck('白名单 CIDR 生效 → 放行', admin_guard_check() === null);

$GLOBALS['LFUN_CONFIG'] = $savedConfig;
$_SERVER = $savedServer;

echo "-- admin_secret() 不允许退化 --\n";
setToken('');
try {
    admin_secret();
    ck('install_token 为空时 admin_secret() 抛错', false);
} catch (Throwable $e) {
    ck('install_token 为空时 admin_secret() 抛错', true);
}
setToken($origToken);
ck('token 正常时 admin_secret() 可用', admin_secret() === $origToken);

echo "\n" . ($fail === 0 ? "后台闸门全部通过。\n" : "失败 $fail 项。\n");
exit($fail === 0 ? 0 : 1);
