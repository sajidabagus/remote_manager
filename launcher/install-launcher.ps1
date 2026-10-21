#Requires -RunAsAdministrator
# Pakai: .\install-launcher.ps1 -Source .\publish-launcher
param([string]$Source=".\publish-launcher")
$dir="$env:ProgramFiles\RCM\Launcher"
Get-Process RcmLauncher -ErrorAction SilentlyContinue | Stop-Process -Force
New-Item -ItemType Directory -Force $dir | Out-Null
Copy-Item "$Source\*" $dir -Recurse -Force
icacls $dir /inheritance:r /grant "*S-1-5-18:(OI)(CI)F" "*S-1-5-32-544:(OI)(CI)F" "*S-1-5-32-545:(OI)(CI)RX" | Out-Null   # siswa hanya boleh menjalankan
New-ItemProperty -Path "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run" -Name RcmLauncher -Value "`"$dir\RcmLauncher.exe`"" -PropertyType String -Force | Out-Null
Write-Host "Launcher terpasang. Otomatis jalan saat user login. Untuk uji sekarang: Start-Process '$dir\RcmLauncher.exe'"
