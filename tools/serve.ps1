# 启动本地开发服务（无需 Apache，模拟 /api 重写）。
# 用法：powershell -File tools\serve.ps1 [-Port 8099]
param([int]$Port = 8099)
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$php = 'C:\Users\52978\Documents\DSH\.tools-php\php\php.exe'
if (-not (Test-Path $php)) { $php = 'php' }
$ini = 'C:\Users\52978\Documents\DSH\.tools-php\php\php.ini'
$args = @()
if (Test-Path $ini) { $args += @('-c', $ini) }
$args += @('-S', "127.0.0.1:$Port", '-t', (Join-Path $root 'dist'), (Join-Path $PSScriptRoot 'dev-router.php'))
Write-Host "本地服务： http://127.0.0.1:$Port/  （前台/后台： /api/admin/）"
Set-Location $root
& $php @args
