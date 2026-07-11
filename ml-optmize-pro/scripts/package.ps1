#!/usr/bin/env pwsh
# Package script PowerShell — empacota o plugin como ZIP oficial para distribuicao via GitHub Releases.
# Espelha scripts/package.sh para uso local em Windows.

[CmdletBinding()]
param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

$Slug = "ml-optmize-pro"
$RootDir = Split-Path -Parent $PSScriptRoot
$PluginDir = Join-Path $RootDir $Slug

if (-not $Version) {
    $headerContent = Get-Content (Join-Path $PluginDir "$Slug.php") -Raw
    $match = [regex]::Match($headerContent, '^\s*\*\s*Version:\s*([0-9.]+)', 'Multiline')
    if ($match.Success) {
        $Version = $match.Groups[1].Value
    }
}

if (-not $Version) {
    Write-Error "Versao nao detectada. Forneca como argumento: pwsh scripts/package.ps1 1.2.3"
    exit 1
}

$OutName = "${Slug}-v${Version}.zip"
$OutPath = Join-Path $RootDir $OutName

# Pre-flight checks
Write-Host "[$Slug] Verificando estrutura..."
$required = @("$Slug.php", "readme.txt", "uninstall.php", "index.php", "includes", "assets", "languages")
foreach ($f in $required) {
    $p = Join-Path $PluginDir $f
    if (-not (Test-Path $p)) {
        Write-Error "Arquivo obrigatorio ausente: $Slug/$f"
        exit 2
    }
}
Write-Host "[$Slug] OK."

# Limpa zip existente
if (Test-Path $OutPath) {
    Remove-Item $OutPath -Force
}

# Empacota — garante cd na pasta do plugin para a primeira entrada ser "$Slug/"
Push-Location $PluginDir
try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($PluginDir, $OutPath, [System.IO.Compression.CompressionLevel]::Optimal, $false)
} finally {
    Pop-Location
}

Write-Host "[$Slug] ZIP criado: $OutPath"
$size = (Get-Item $OutPath).Length
Write-Host "[$Slug] Tamanho: $([math]::Round($size/1MB, 2)) MB"

# Validacao
Write-Host "[$Slug] Validando estrutura do ZIP..."
Add-Type -AssemblyName System.IO.Compression
$zip = [System.IO.Compression.ZipFile]::OpenRead($OutPath)
try {
    $entries = $zip.Entries.FullName
    if ($entries.Count -lt 1) { Write-Error "ZIP vazio."; exit 3 }
    $first = $entries[0]
    Write-Host "Primeira entrada: $first"
    if (-not $first.StartsWith("$Slug/")) {
        Write-Error "ERRO: primeira entrada nao eh '$Slug/'. Estrutura invalida."
        exit 3
    }
    if ($entries | Where-Object { $_ -like "*__MACOSX*" -or $_ -like "*.DS_Store" -or $_ -like "*/.git/*" }) {
        Write-Error "ERRO: ZIP contem lixo (__MACOSX, .DS_Store ou .git)."
        exit 4
    }
} finally {
    $zip.Dispose()
}

Write-Host "[$Slug] Validacao OK."
Write-Host "[$Slug] Asset filename esperado para o Updater: $OutName"
