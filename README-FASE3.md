# Fase 3 - Kontrol client
**Server:** jalankan `sql/phase3_migration.sql`; timpa seluruh isi folder `includes/` dan `public/` dengan versi di zip ini.
**Launcher** (PC dev .NET 8 SDK), dari `launcher/RcmLauncher`:
    dotnet publish -c Release -r win-x64 --self-contained -p:PublishSingleFile=true -o publish-launcher
**Pasang di tiap PC client** (PowerShell Admin; Agent Fase 2 harus sudah terpasang, tidak perlu diubah):
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
    .\install-launcher.ps1 -Source .\publish-launcher
    Start-Process "$env:ProgramFiles\RCM\Launcher\RcmLauncher.exe"      # uji tanpa logout
**Uji urut:** Kontrol Client -> Kirim Pesan (1 PC) -> Buka URL -> Jalankan Aplikasi (Notepad) -> Kunci -> Buka Kunci -> Restart.
