<?php
/** php -S 内置服务器用的路由（生产环境走 api/.htaccess）。 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$root = realpath(__DIR__ . '/../dist');

if (strpos($uri, '/api/') === 0) {
    $file = $root . $uri;
    if (is_file($file)) {
        return false; // 交给内置服务器直接返回
    }
    // 模拟 .htaccess：目录 -> index.php
    if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
        require rtrim($file, '/') . '/index.php';
        return true;
    }
    require $root . '/api/index.php';
    return true;
}
return false;
