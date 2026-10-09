# 一键运行全部测试（需要先启动本地 PHP 服务）。
#   tools\serve.ps1 启动服务；另开一个终端运行本脚本。
param(
    [string]$Base = 'http://127.0.0.1:8099'
)
$ErrorActionPreference = 'Continue'
$php = 'C:\Users\52978\Documents\DSH\.tools-php\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }
$ini = 'C:\Users\52978\Documents\DSH\.tools-php\php\php.ini'
$phpArgs = @(); if (Test-Path $ini) { $phpArgs = @('-c', $ini) }

Write-Host '=== 1/4 PHP 语法检查 ===' -ForegroundColor Cyan
$files = Get-ChildItem -Recurse -File (Join-Path $PSScriptRoot '..\dist\api') -Filter *.php
$bad = 0
foreach ($f in $files) {
    $out = & $php @phpArgs -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Host $out; $bad++ }
}
Write-Host ("检查 {0} 个文件，语法错误 {1} 个" -f $files.Count, $bad)

Write-Host '=== 2/4 内容库自检 ===' -ForegroundColor Cyan
& $php @phpArgs (Join-Path $PSScriptRoot '..\tests\verify-content.php')
$contentOk = $LASTEXITCODE -eq 0
& $php @phpArgs (Join-Path $PSScriptRoot '..\tests\verify-notify.php')
$notifyOk = $LASTEXITCODE -eq 0

Write-Host '=== 2.5/5 客户端付费内容泄漏检查 ===' -ForegroundColor Cyan
node (Join-Path $PSScriptRoot 'check-client-leak.js') $Base
$leakOk = $LASTEXITCODE -eq 0

Write-Host '=== 3/4 接口端到端 ===' -ForegroundColor Cyan
node (Join-Path $PSScriptRoot 'e2e-api.js') $Base
$apiOk = $LASTEXITCODE -eq 0

Write-Host '=== 4/4 前端与后台端到端（jsdom） ===' -ForegroundColor Cyan
node (Join-Path $PSScriptRoot 'e2e-web.js') "$Base/"
$webOk = $LASTEXITCODE -eq 0
node (Join-Path $PSScriptRoot 'e2e-web-en.js') $Base
$enOk = $LASTEXITCODE -eq 0
node (Join-Path $PSScriptRoot 'e2e-admin.js') "$Base/api/admin/" "$Base/api"
$adminOk = $LASTEXITCODE -eq 0

Write-Host ''
if ($bad -eq 0 -and $contentOk -and $notifyOk -and $leakOk -and $apiOk -and $webOk -and $enOk -and $adminOk) {
    Write-Host '全部测试通过。' -ForegroundColor Green
    exit 0
} else {
    Write-Host ("存在失败：lint={0} content={1} leak={2} api={3} web={4} en={5} admin={6}" -f $bad, $contentOk, $leakOk, $apiOk, $webOk, $enOk, $adminOk) -ForegroundColor Red
    exit 1
}
