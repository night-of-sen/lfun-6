<?php
declare(strict_types=1);

const LFUN_ORDER_TYPES = ['single_unlock', 'member_monthly', 'donation'];
const LFUN_ORDER_STATUS = ['pending', 'paid', 'failed', 'refunded'];

function user_public(array $u): array
{
    $expire = $u['member_expire_at'] ?? null;
    $active = $expire && !lfun_is_past((string) $expire);
    return [
        'id'          => (int) $u['id'],
        'device_id'   => $u['device_id'],
        'is_member'   => (bool) $active,
        'member_expire_at' => $expire,
        'member_days_left' => $active ? (int) ceil((strtotime($expire . ' UTC') - time()) / 86400) : 0,
        'created_at'  => $u['created_at'],
    ];
}

function user_by_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $row = db_one('SELECT * FROM users WHERE token_hash = ?', [lfun_hash_token($token)]);
    return $row ?: null;
}

function user_by_device(string $deviceId): ?array
{
    if ($deviceId === '') {
        return null;
    }
    $row = db_one('SELECT * FROM users WHERE device_id = ?', [$deviceId]);
    return $row ?: null;
}

function user_create(string $deviceId, string $token): array
{
    $now = lfun_now();
    db_exec(
        'INSERT INTO users (device_id, token_hash, created_at, last_seen_at) VALUES (?, ?, ?, ?)',
        [$deviceId, lfun_hash_token($token), $now, $now]
    );
    return db_one('SELECT * FROM users WHERE id = ?', [db_last_id()]);
}

/** 登录/识别：优先令牌，其次设备 ID，最后新建（PRD 模块一 1.1）。 */
function user_resolve(?string $token, ?string $deviceId, bool $create = true): ?array
{
    $user = $token ? user_by_token($token) : null;
    if ($user) {
        db_exec('UPDATE users SET last_seen_at = ? WHERE id = ?', [lfun_now(), $user['id']]);
        return $user;
    }
    if ($deviceId) {
        $user = user_by_device($deviceId);
        if ($user) {
            if ($token) {
                db_exec('UPDATE users SET token_hash = ?, last_seen_at = ? WHERE id = ?', [lfun_hash_token($token), lfun_now(), $user['id']]);
                $user['token_hash'] = lfun_hash_token($token);
            } else {
                db_exec('UPDATE users SET last_seen_at = ? WHERE id = ?', [lfun_now(), $user['id']]);
            }
            return $user;
        }
        if ($create) {
            $newToken = $token ?: lfun_token();
            return user_create($deviceId, $newToken);
        }
    }
    return null;
}

/** 从请求中取当前用户（不创建）。 */
function current_user(): ?array
{
    $token = lfun_header('X-Auth-Token');
    if ($token === '') {
        $cookieName = (string) cfg('security.token_cookie_name', 'lfun_tok');
        $token = (string) ($_COOKIE[$cookieName] ?? '');
    }
    if ($token === '') {
        $token = lfun_str_param('token', '');
    }
    $deviceId = lfun_header('X-Device-Id');
    if ($deviceId === '') {
        $deviceId = lfun_str_param('device_id', '');
    }
    return user_resolve($token !== '' ? $token : null, $deviceId !== '' ? $deviceId : null, false);
}

/** 需要登录的接口用这个；没有身份时返回 401。 */
function require_user(): array
{
    $u = current_user();
    if (!$u) {
        lfun_fail('unauthenticated', '会话已失效，请刷新页面后重试。', 401);
    }
    return $u;
}

function user_is_member(array $u): bool
{
    return !empty($u['member_expire_at']) && !lfun_is_past((string) $u['member_expire_at']);
}

/** 会员叠加：从当前到期时间与现在的较晚者开始顺延。 */
function user_extend_membership(int $userId, int $days = 30): array
{
    $u = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$u) {
        throw new RuntimeException('用户不存在');
    }
    $base = time();
    if (!empty($u['member_expire_at'])) {
        $ts = strtotime((string) $u['member_expire_at'] . ' UTC');
        if ($ts && $ts > $base) {
            $base = $ts;
        }
    }
    $newExpire = gmdate('Y-m-d H:i:s', $base + $days * 86400);
    db_exec('UPDATE users SET member_expire_at = ? WHERE id = ?', [$newExpire, $userId]);
    return db_one('SELECT * FROM users WHERE id = ?', [$userId]);
}

function user_set_cookie(string $token): void
{
    $name = (string) cfg('security.token_cookie_name', 'lfun_tok');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (PHP_SAPI === 'cli') {
        return;
    }
    setcookie($name, $token, [
        'expires'  => time() + 86400 * 400,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
