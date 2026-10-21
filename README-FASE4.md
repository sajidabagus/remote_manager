# Fase 4 - Wake-on-LAN, Jadwal, Log, Kontrol Massal
**Server:** jalankan `sql/phase4_migration.sql`. Timpa `includes/`, `public/`, `cli/` dengan versi zip ini.
**Tetap pakai `config.php` lama**, lalu tambahkan satu baris (alamat broadcast subnet Anda):
    const WOL_BROADCAST='192.168.18.255';
**Wake-on-LAN:** aktifkan di BIOS ("Wake on LAN"/"Power On by PCI-E") dan di Windows (Device Manager > NIC > Power Management >
"Allow this device to wake the computer" + Advanced > "Wake on Magic Packet"). Matikan Fast Startup bila WoL gagal dari kondisi shutdown.
Server harus satu segmen/VLAN dengan client. Jika PHP error soal socket, aktifkan `extension=sockets` di php.ini (opsional, ada cadangan).
**Scheduler (PowerShell Admin di server):**
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
    .\cli\install-scheduler.ps1 -Php C:\xampp\php\php.exe -Script C:\xampp\htdocs\remote_manager\cli\scheduler.php
Uji manual: `C:\xampp\php\php.exe cli\scheduler.php`
**Contoh jadwal harian:** 06:30 Nyalakan (semua) | 06:40 Buka URL CBT kiosk | 15:25 Pesan "Komputer dimatikan 10 menit lagi" | 15:35 Matikan (semua).
