# RCM - Fase 1
1. Impor `sql/schema.sql` ke MySQL/MariaDB; buat user DB lalu isi `config.php`.
2. Buat admin: `php cli/create_admin.php admin PasswordKuat123`
3. Arahkan web root (Apache/Nginx+PHP-FPM) ke folder `public/`. Aktifkan mod_rewrite untuk `public/api/.htaccess`
   (atau panggil `api/index.php?r=enroll` / `?r=heartbeat`).
4. Scan butuh `php` CLI dan sebaiknya `nmap`. Di Linux, jalankan PHP-FPM/Apache sebagai user yang boleh menjalankan nmap.
5. Uji API:
   curl -X POST http://SERVER/api/enroll -d '{"mac":"AA:BB:CC:DD:EE:01","hostname":"LAB1-PC01","version":"0.1"}'
   (setujui di menu Kelola Client, ulangi enroll untuk mendapat token, lalu:)
   curl -X POST http://SERVER/api/heartbeat -H "Authorization: Bearer TOKEN" -d '{}'
