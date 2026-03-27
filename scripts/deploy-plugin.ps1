$ErrorActionPreference = 'Stop'

$source = Split-Path -Parent $PSScriptRoot
$deploymentRoot = Join-Path $source 'deployment'
$stageRoot = Join-Path $deploymentRoot 'stage'
$stagePluginRoot = Join-Path $stageRoot 'oft-upload-form'
$zipPath = Join-Path $deploymentRoot 'oft-upload-form.zip'
$configPath = Join-Path $source 'deployment.config.json'
$jsonPath = Join-Path $deploymentRoot 'oft-upload-form.json'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$mainPluginFile = Join-Path $source 'oft-upload-form.php'

if (-not (Test-Path -LiteralPath $configPath)) {
	throw "Missing deployment config: $configPath"
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$pluginFileContent = Get-Content $mainPluginFile -Raw

if ($pluginFileContent -notmatch "Version:\s*([0-9]+(?:\.[0-9]+)*)") {
	throw "Could not read plugin version from $mainPluginFile"
}

$pluginVersion = $Matches[1]
if ($pluginVersion -ne $config.version) {
	throw "Deployment config version ($($config.version)) does not match plugin header version ($pluginVersion)."
}

if (Test-Path -LiteralPath $deploymentRoot) {
	Remove-Item -LiteralPath $deploymentRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $stagePluginRoot -Force | Out-Null

$robocopyArgs = @(
	$source
	$stagePluginRoot
	'/MIR'
	'/XD', '.git', '.vscode', 'deployment'
	'/XF', '.gitignore', 'deployment.config.json'
	'/R:2'
	'/W:1'
	'/NFL'
	'/NDL'
	'/NJH'
	'/NJS'
)

& robocopy @robocopyArgs
$exitCode = $LASTEXITCODE

if ($exitCode -ge 8) {
	throw "Deployment packaging failed with robocopy exit code $exitCode."
}

& tar -a -c -f $zipPath -C $stageRoot 'oft-upload-form'
if ($LASTEXITCODE -ne 0) {
	throw "ZIP packaging failed with tar exit code $LASTEXITCODE."
}

$json = $config | ConvertTo-Json -Depth 10
$jsonBytes = [System.Text.Encoding]::UTF8.GetBytes($json + [Environment]::NewLine)
[System.IO.File]::WriteAllBytes($jsonPath, $jsonBytes)
Remove-Item -LiteralPath $stageRoot -Recurse -Force

Write-Host "Deployment package created:"
Write-Host "ZIP:  $zipPath"
Write-Host "JSON: $jsonPath"
Write-Host "Version: $($config.version)"