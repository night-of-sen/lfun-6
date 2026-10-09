#!/usr/bin/env bash
# Linux/macOS 版一键全量测试（对应 tools/run-all-tests.ps1）。
# 用法：bash tools/ci.sh
# 可用环境变量覆盖 CI 默认值：LFUN_INSTALL_TOKEN / LFUN_ADMIN_PASS / LFUN_PAYPAL_ME / LFUN_PORT
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${LFUN_PORT:-8099}"
BASE="http://127.0.0.1:${PORT}"
echo "== 问时 · 小六壬 CI =="

echo "-- PHP 语法检查 --"
fail=0
while IFS= read -r f; do
  php -l "$f" >/dev/null || fail=1
done < <(find dist/api -name "*.php")
[ "$fail" -eq 0 ] || { echo "PHP lint 失败"; exit 1; }
echo "PHP lint 通过"

echo "-- 内容库自检 --"
php tests/verify-content.php
echo "-- 通知载荷自检 --"
php tests/verify-notify.php

if [ ! -f dist/api/config.local.php ]; then
  echo "-- 生成 CI 用 config.local.php --"
  cp dist/api/config.local.php.example dist/api/config.local.php
  sed -i "s/请改成一段只有你知道的随机字符串/${LFUN_INSTALL_TOKEN:-ci-install-token-0123456789abcdef}/" dist/api/config.local.php
  sed -i "s/请改成强密码/${LFUN_ADMIN_PASS:-lfun-dev-pass}/" dist/api/config.local.php
  sed -i "s/'me'          => ''/'me'          => '${LFUN_PAYPAL_ME:-ci-paypal-me}'/" dist/api/config.local.php
fi
rm -f dist/api/storage/lfun.sqlite
php dist/api/install.php

echo "-- 启动本地服务 (${BASE}) --"
# 文档根必须是 dist/（部署到 Hostinger public_html 的内容）
cd dist
php -S "127.0.0.1:${PORT}" ../tools/dev-router.php >/tmp/lfun-ci-server.log 2>&1 &
SRV=$!
cd ..
trap 'kill $SRV 2>/dev/null || true' EXIT
for i in $(seq 1 30); do
  curl -sf "${BASE}/api/health" >/dev/null 2>&1 && break
  sleep 1
done
curl -sf "${BASE}/api/health" >/dev/null || { echo "服务未启动"; exit 1; }

echo "-- e2e-api --"
node tools/e2e-api.js "$BASE"
echo "-- e2e-web --"
node tools/e2e-web.js "$BASE"
echo "-- e2e-web-en --"
node tools/e2e-web-en.js "$BASE"
echo "-- e2e-admin --"
node tools/e2e-admin.js "${BASE}/api/admin/" "${BASE}/api"
echo "-- check-client-leak --"
node tools/check-client-leak.js "$BASE"

echo "== 全部通过 =="
