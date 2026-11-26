# Backup PostgreSQL (ajustar ruta pg_dump y credenciales via .env/entorno)
param(
  [string]$PgDump = "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe",
  [string]$DbName = "BD_Seniat",
  [string]$DbUser = "postgres",
  [string]$OutDir = "C:\Users\UserBy4n\Desktop\paginas\seniat-control\storage\backups"
)
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$ts = Get-Date -Format "yyyyMMdd_HHmmss"
$out = Join-Path $OutDir "$DbName`_$ts.sql"
$env:PGPASSWORD = $env:PGPASSWORD # set externally
& $PgDump -U $DbUser -d $DbName -f $out
if ($LASTEXITCODE -eq 0) { Write-Output "BACKUP_OK $out" } else { Write-Error "BACKUP_FAIL" }