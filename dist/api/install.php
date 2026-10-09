<?php
declare(strict_types=1);

/**
 * 安装脚本。两种用法：
 *   CLI：php api/install.php
 *   Web：/api/install.php?token=<app.install_token>
 * 执行后请删除或改名本文件。
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/users.php';
require_once __DIR__ . '/lib/content.php';

$isCli = PHP_SAPI === 'cli';
$token = $isCli ? '' : (string) ($_GET['token'] ?? lfun_str_param('token', ''));
$expected = (string) cfg('app.install_token', '');

if (!$isCli) {
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        echo '安装令牌无效：请在 config.local.php 的 app.install_token 中设置一段随机字符串，并带上 ?token= 参数。';
        exit;
    }
}

$log = [];
$fail = null;

try {
    foreach (db_install() as $stmt) {
        $log[] = 'schema: ' . $stmt;
    }
    $log[] = '数据库驱动：' . db_driver();

    $adminCfg = (array) cfg('admin', []);
    $username = (string) ($adminCfg['username'] ?? '');
    $password = (string) ($adminCfg['password'] ?? '');
    if ($username !== '' && $password !== '') {
        $existing = db_one('SELECT * FROM admin_users WHERE username = ?', [$username]);
        if (strpos($password, '请改成') === 0) {
            $log[] = '警告：管理员密码仍为示例值，请修改 config.local.php 后重新运行。';
        } elseif ($existing) {
            db_exec('UPDATE admin_users SET pass_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $existing['id']]);
            $log[] = '管理员密码已更新：' . $username;
        } else {
            db_exec('INSERT INTO admin_users (username, pass_hash, created_at) VALUES (?, ?, ?)', [
                $username, password_hash($password, PASSWORD_DEFAULT), lfun_now(),
            ]);
            $log[] = '已创建管理员：' . $username;
        }
    } else {
        $log[] = '未配置管理员账号（config.local.php 的 admin.username / admin.password）。';
    }

    $p = pricing();
    $log[] = sprintf('当前定价：单次 ¥%s / 会员 ¥%s / 打赏 ¥%s-%s',
        $p['single_unlock_cny'], $p['member_monthly_cny'], $p['donation_min_cny'], $p['donation_max_cny']);

    $lib = content_library('zh');
    $deepZh = is_file(LFUN_API_DIR . '/data/deep.zh.php');
    $deepEn = is_file(LFUN_API_DIR . '/data/deep.en.php');
    $log[] = '内容库：' . count($lib['categories']) . ' 个类别，付费组合断语（中文）' . ($deepZh ? '已装载' : '未提供（将使用回退文案）');
    $log[] = '内容库：付费组合断语（英文）' . ($deepEn ? '已装载' : '未提供（将使用回退文案）');

    $storage = LFUN_API_DIR . '/storage';
    $log[] = 'storage 目录可写：' . (is_dir($storage) && is_writable($storage) ? '是' : '否（请设置 755/775 权限）');

    db_audit('install', 'install.run', null, ['driver' => db_driver()]);
} catch (Throwable $e) {
    $fail = $e->getMessage();
}

if ($isCli) {
    foreach ($log as $line) {
        echo $line . "\n";
    }
    if ($fail) {
        fwrite(STDERR, "安装失败：" . $fail . "\n");
        exit(1);
    }
    echo "安装完成。请删除 api/install.php。\n";
    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>安装 · 问时</title><style>body{font-family:system-ui,-apple-system,"PingFang SC",sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.7;color:#1d1d1f}li{margin:4px 0}.err{color:#b3261e;font-weight:600}code{background:#f5f5f7;padding:2px 6px;border-radius:4px}</style>';
echo '<h1>问时 · 小六壬 安装</h1>';
if ($fail) {
    echo '<p class="err">安装失败：' . htmlspecialchars($fail, ENT_QUOTES, 'UTF-8') . '</p>';
} else {
    echo '<p>安装完成。请立即删除或改名 <code>api/install.php</code>。</p><ul>';
    foreach ($log as $line) {
        echo '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p>管理后台：<a href="./admin/">/api/admin/</a></p>';
}
