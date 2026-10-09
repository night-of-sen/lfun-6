<?php
declare(strict_types=1);

/** 生成 UUID v4（设备 ID 使用）。 */
function lfun_uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** 生成随机令牌（用户会话、管理会话）。 */
function lfun_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function lfun_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function lfun_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function lfun_iso8601(): string
{
    return gmdate('c');
}

function lfun_plus_minutes(int $minutes): string
{
    return gmdate('Y-m-d H:i:s', time() + $minutes * 60);
}

function lfun_plus_days(int $days): string
{
    return gmdate('Y-m-d H:i:s', time() + $days * 86400);
}

function lfun_is_past(?string $utc): bool
{
    if (!$utc) {
        return true;
    }
    return strtotime($utc . ' UTC') <= time();
}

/** 元 -> 分，四舍五入，避免浮点误差。 */
function lfun_cny_to_cents($cny): int
{
    return (int) round(((float) $cny) * 100);
}

function lfun_cents_to_cny(int $cents): float
{
    return round($cents / 100, 2);
}

function lfun_clamp(int $v, int $min, int $max): int
{
    return max($min, min($max, $v));
}

/** 客户端 IP 的加盐哈希（不保存明文 IP，符合隐私最小化）。 */
function lfun_ip_hash(): string
{
    $ip = lfun_client_ip();
    $salt = (string) cfg('app.install_token', 'lfun');
    return substr(hash('sha256', $ip . '|' . $salt), 0, 32);
}

function lfun_client_ip(): string
{
    $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($candidates as $k) {
        if (!empty($_SERVER[$k])) {
            $v = explode(',', (string) $_SERVER[$k])[0];
            $v = trim($v);
            if ($v !== '') {
                return $v;
            }
        }
    }
    return '0.0.0.0';
}

function lfun_ua_hash(): string
{
    return substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);
}

/** 读取 JSON 请求体（兼容表单提交）。 */
function lfun_request_data(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = file_get_contents('php://input');
    $data = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    // 常见的 JSONP / 表单兼容：合并查询串
    foreach ($_GET as $k => $v) {
        if (!array_key_exists($k, $data) && $k !== 'r') {
            $data[$k] = $v;
        }
    }
    $cache = $data;
    return $data;
}

function lfun_param(string $key, $default = null)
{
    $data = lfun_request_data();
    if (array_key_exists($key, $data)) {
        return $data[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function lfun_str_param(string $key, string $default = ''): string
{
    $v = lfun_param($key, $default);
    if (is_array($v)) {
        return $default;
    }
    return trim((string) $v);
}

function lfun_int_param(string $key, int $default = 0): int
{
    $v = lfun_param($key, $default);
    if (is_array($v)) {
        return $default;
    }
    return (int) $v;
}

function lfun_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return (string) ($_SERVER[$key] ?? '');
}

/** 清空所有输出缓冲，确保响应体干净（把警告/告警彻底丢掉）。 */
function lfun_flush_buffers(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

/**
 * 返回纯文本响应（支付平台回调要求 4xx/200 + 固定字符串）。
 * 例如支付宝要求 "success" / "failure"。
 */
function lfun_raw(string $body, int $status = 200): void
{
    lfun_flush_buffers();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo $body;
    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

function lfun_json($payload, int $status = 200): void
{
    lfun_flush_buffers();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $origin = (string) cfg('app.cors_origin', '');
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, X-Device-Id, X-Auth-Token');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

/** 统一的成功响应。 */
function lfun_ok(array $data = [], int $status = 200): void
{
    lfun_json(array_merge(['ok' => true], $data), $status);
}

/** 统一的失败响应；HTTP 状态码按 PRD 要求区分（402 未付费、403 越权、404 找不到、429 频率）。 */
function lfun_fail(string $code, string $message, int $status = 400, array $extra = []): void
{
    lfun_json(array_merge([
        'ok'      => false,
        'error'   => $code,
        'message' => $message,
    ], $extra), $status);
}

function lfun_text(string $s, int $max = 500): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

/** 极简限流：同一 IP 在 $seconds 秒内最多 $limit 次（用于下单与回调接口防刷）。 */
function lfun_rate_limit(string $bucket, int $limit, int $seconds): void
{
    $dir = LFUN_API_DIR . '/storage/rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $bucket) . '_' . lfun_ip_hash() . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = array_values(array_filter($decoded, fn($t) => is_int($t) && $t > $now - $seconds));
        }
    }
    if (count($hits) >= $limit) {
        lfun_fail('rate_limited', '操作过于频繁，请稍后再试。', 429);
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
}
