<?php
declare(strict_types=1);

/**
 * 引导文件：加载配置、注册错误处理、准备数据库。
 * 任何入口（index.php / admin / install.php）都应先 require 本文件。
 */

define('LFUN_API_DIR', dirname(__DIR__));   // .../dist/api
define('LFUN_APP_DIR', dirname(LFUN_API_DIR)); // .../dist

/** 深度合并配置（后者覆盖前者）。 */
function lfun_deep_merge(array $base, array $override): array
{
    foreach ($override as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = lfun_deep_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function lfun_load_config(): array
{
    $cfg = require LFUN_API_DIR . '/config.defaults.php';
    $local = LFUN_API_DIR . '/config.local.php';
    if (is_file($local)) {
        $extra = require $local;
        if (is_array($extra)) {
            $cfg = lfun_deep_merge($cfg, $extra);
        }
    }
    return $cfg;
}

$GLOBALS['LFUN_CONFIG'] = lfun_load_config();

function cfg(?string $path = null, $default = null)
{
    $c = $GLOBALS['LFUN_CONFIG'];
    if ($path === null) {
        return $c;
    }
    foreach (explode('.', $path) as $seg) {
        if (!is_array($c) || !array_key_exists($seg, $c)) {
            return $default;
        }
        $c = $c[$seg];
    }
    return $c;
}

$__debug = (bool) cfg('app.debug', false);
error_reporting($__debug ? E_ALL : E_ALL & ~E_DEPRECATED);
ini_set('display_errors', $__debug ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set((string) cfg('app.timezone', 'Asia/Shanghai'));

// 输出缓冲：任何 PHP 警告/废弃告警都会被丢弃，绝不会污染 JSON / 支付回调响应。
if (PHP_SAPI !== 'cli') {
    ob_start();
}

set_exception_handler(function ($e) {
    lfun_log_error($e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, '[fatal] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
    lfun_json([
        'ok'    => false,
        'error' => 'server_error',
        'message' => cfg('app.debug', false) ? $e->getMessage() : '服务器内部错误，请稍后重试。',
    ], 500);
});

require_once LFUN_API_DIR . '/lib/util.php';
require_once LFUN_API_DIR . '/lib/db.php';

function lfun_log_error($e): void
{
    $dir = LFUN_API_DIR . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n",
        gmdate('c'),
        is_object($e) ? get_class($e) : 'Error',
        is_object($e) && method_exists($e, 'getMessage') ? $e->getMessage() : (string) $e,
        is_object($e) && method_exists($e, 'getFile') ? $e->getFile() : '-',
        is_object($e) && method_exists($e, 'getLine') ? $e->getLine() : 0
    );
    @file_put_contents($dir . '/error.log', $line, FILE_APPEND | LOCK_EX);
}
