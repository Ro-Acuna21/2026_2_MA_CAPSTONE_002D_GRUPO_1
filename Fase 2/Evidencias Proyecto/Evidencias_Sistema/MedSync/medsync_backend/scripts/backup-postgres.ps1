param(
    [Parameter(Mandatory = $true)]
    [string] $OutputDirectory,
    [string] $PgBin = ''
)

$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$config = @{}
foreach ($line in (Get-Content -LiteralPath (Join-Path $backend '.env'))) {
    if ($line -match '^([A-Z_]+)=(.*)$') {
        $config[$matches[1]] = $matches[2].Trim().Trim('"')
    }
}

$hostName = $config['DB_HOST']
$port = $config['DB_PORT']
$userName = $config['DB_USERNAME']
$coreDatabase = $config['DB_CORE_DATABASE']
if (-not $hostName -or -not $port -or -not $userName -or -not $coreDatabase) {
    throw 'Falta la configuración PostgreSQL de Core en .env.'
}

$pgDump = if ($PgBin) { Join-Path $PgBin 'pg_dump.exe' } else { (Get-Command pg_dump -ErrorAction Stop).Source }
$pgRestore = if ($PgBin) { Join-Path $PgBin 'pg_restore.exe' } else { (Get-Command pg_restore -ErrorAction Stop).Source }
$psql = if ($PgBin) { Join-Path $PgBin 'psql.exe' } else { (Get-Command psql -ErrorAction Stop).Source }
$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
$documents = [System.IO.Path]::GetFullPath((Join-Path $backend 'storage/app/private/medical-results'))
if ($outputRoot.StartsWith($documents + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'El destino del respaldo no puede quedar dentro de medical-results.'
}

$env:PGPASSWORD = $config['DB_PASSWORD']
try {
    $centerNames = & $psql -h $hostName -p $port -U $userName -d $coreDatabase -At -c "select distinct database_name from medical_centers where database_name is not null and database_name <> '' order by 1"
    if ($LASTEXITCODE -ne 0) { throw 'No fue posible leer las bases de centros desde Core.' }
    $databases = @($coreDatabase) + @($centerNames | Where-Object { $_ }) | Select-Object -Unique
    foreach ($database in $databases) {
        if ($database -notmatch '^[a-zA-Z][a-zA-Z0-9_]*$') { throw "Nombre de base no válido: $database" }
    }

    $snapshot = Join-Path $outputRoot ('medsync-' + (Get-Date -Format 'yyyyMMdd-HHmmssfff'))
    New-Item -ItemType Directory -Path $snapshot -Force | Out-Null
    $archives = foreach ($database in $databases) {
        $file = Join-Path $snapshot "$database.dump"
        & $pgDump -h $hostName -p $port -U $userName -Fc -f $file $database
        if ($LASTEXITCODE -ne 0) { throw "Falló pg_dump para $database" }
        & $pgRestore --list $file > $null
        if ($LASTEXITCODE -ne 0) { throw "El archivo $file no se puede leer con pg_restore" }
        $hash = Get-FileHash -LiteralPath $file -Algorithm SHA256
        [pscustomobject]@{ database = $database; file = "$database.dump"; bytes = (Get-Item -LiteralPath $file).Length; sha256 = $hash.Hash }
    }

    $privateFiles = 0
    if (Test-Path -LiteralPath $documents) {
        Copy-Item -LiteralPath $documents -Destination (Join-Path $snapshot 'medical-results') -Recurse
        $privateFiles = @(Get-ChildItem -LiteralPath $documents -Recurse -File).Count
    }
    [pscustomobject]@{ created_at = (Get-Date).ToString('o'); databases = @($archives); medical_result_files = $privateFiles } |
        ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $snapshot 'manifest.json') -Encoding UTF8
    Write-Output "Respaldo creado: $snapshot"
    Write-Output "Bases: $($databases -join ', '); archivos clínicos: $privateFiles"
} finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}
