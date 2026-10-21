#Requires -RunAsAdministrator
# Pakai: .\install.ps1 -ServerUrl http://192.168.1.10/remote_manager/public/api -Source .\publish
param([Parameter(Mandatory)][string]$ServerUrl,[string]$Source=".\publish")
$dir="$env:ProgramFiles\RCM\Agent"
if(Get-Service RcmAgent -ErrorAction SilentlyContinue){ Stop-Service RcmAgent -Force; sc.exe delete RcmAgent | Out-Null; Start-Sleep 2 }
New-Item -ItemType Directory -Force $dir | Out-Null
Copy-Item "$Source\*" $dir -Recurse -Force
$cfg=Get-Content "$dir\appsettings.json" -Raw | ConvertFrom-Json
$cfg.Agent.ServerUrl=$ServerUrl
$cfg | ConvertTo-Json -Depth 5 | Set-Content "$dir\appsettings.json" -Encoding UTF8
New-Service -Name RcmAgent -BinaryPathName "`"$dir\RcmAgent.exe`"" -DisplayName "RCM Agent" -StartupType Automatic | Out-Null
sc.exe failure RcmAgent reset= 86400 actions= restart/5000/restart/5000/restart/5000 | Out-Null
Start-Service RcmAgent
Write-Host "RcmAgent terpasang dan berjalan. Log: Event Viewer > Windows Logs > Application (sumber RcmAgent)."
