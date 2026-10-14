param(
  [string]$Binary = "C:\Program Files\RackSentinel\racksentinel-host.exe",
  [string]$Config = "C:\ProgramData\RackSentinel\host-config.json"
)
$svc = "RackSentinelHost"
if (-not (Test-Path $Binary)) { throw "Binary not found: $Binary" }
sc.exe create $svc binPath= "`"$Binary`" -service -config `"$Config`"" start= auto DisplayName= "RackSentinel Host Agent"
sc.exe description $svc "Reports host metrics to RackSentinel DCIM"
Write-Host "Service $svc created. Start with: sc.exe start $svc"
Write-Host "Uninstall: sc.exe stop $svc; sc.exe delete $svc"
