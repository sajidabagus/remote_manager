#Requires -RunAsAdministrator
# Pakai: .\install-scheduler.ps1 -Php C:\xampp\php\php.exe -Script C:\xampp\htdocs\remote_manager\cli\scheduler.php
param([string]$Php="C:\xampp\php\php.exe",[string]$Script="C:\xampp\htdocs\remote_manager\cli\scheduler.php")
$a=New-ScheduledTaskAction -Execute $Php -Argument "`"$Script`""
$t=New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
Register-ScheduledTask -TaskName "RCM-Scheduler" -Action $a -Trigger $t -User "SYSTEM" -RunLevel Highest -Force | Out-Null
Write-Host "Task 'RCM-Scheduler' dibuat (tiap 1 menit)."
