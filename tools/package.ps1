# 生成可直接上传 Hostinger 的部署包。
# 用法：powershell -File tools\package.ps1 [-Out build]
param([string]$Out = 'build')
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$stamp = Get-Date -Format 'yyyyMMdd'
$stage = Join-Path $root "$Out\lfun-deploy-$stamp"
$zip = Join-Path $root "$Out\lfun-hostinger-$stamp.zip"

if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force -Path $stage | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $root $Out) | Out-Null

# 复制站点，排除私有配置与运行数据
& robocopy (Join-Path $root 'dist') $stage /E /NFL /NDL /NJH /NJS /NP /XF config.local.php *.sqlite *.sqlite3 *.log /XD storage | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy 失败：$LASTEXITCODE" }

# storage 目录必须存在且带保护
$storageDir = Join-Path $stage 'api\storage'
New-Item -ItemType Directory -Force -Path $storageDir | Out-Null
Set-Content -LiteralPath (Join-Path $storageDir '.htaccess') -Value "Require all denied`nOptions -Indexes" -Encoding UTF8

if (Test-Path $zip) { Remove-Item -Force $zip }
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $zip -CompressionLevel Optimal

$hash = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash
$size = (Get-Item $zip).Length
Write-Host ""
Write-Host "部署包：$zip" -ForegroundColor Green
Write-Host ("大小：{0:N0} 字节" -f $size)
Write-Host "SHA256：$hash"
Write-Host ""
Write-Host "上传后请确认 public_html/index.html 与 public_html/api/index.php 存在（不要多套一层目录）。"
