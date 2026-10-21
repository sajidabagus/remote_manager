# Fase 2 - Agent
**Server:** jalankan `sql/phase2_migration.sql` (kolom `tasks.result`). Timpa `public/api/index.php` dan `public/clients.php`.
(API kini dipanggil via `index.php?r=...`, tanpa .htaccess; token lewat header `X-Agent-Token`.)

**Build agent** (di PC dev dengan .NET 8 SDK), dari folder `agent/RcmAgent`:
    dotnet publish -c Release -r win-x64 --self-contained -p:PublishSingleFile=true -o publish
**Pasang di PC client** (PowerShell Admin), salin folder `publish` + `install.ps1`:
    .\install.ps1 -ServerUrl http://IP-SERVER/remote_manager/public/api

**Uji 1 PC:**
1. Agent jalan -> PC muncul di Kelola Client > *Menunggu persetujuan* (atau sudah *Menunggu Agent* bila di-scan dulu).
2. Setujui -> dalam <= 30 detik agent mendapat token, status jadi Online di dashboard.
3. Klik **Tes ping** di Kelola Client -> hasil "pong dari <nama PC>" muncul di Log Aktivitas dalam <= 15 detik.
Log agent: Event Viewer > Application (sumber RcmAgent).
