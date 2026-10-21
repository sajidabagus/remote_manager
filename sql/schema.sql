CREATE DATABASE IF NOT EXISTS rcm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rcm;
CREATE TABLE admins(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'admin', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE rooms(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) UNIQUE NOT NULL);
CREATE TABLE room_rules(id INT AUTO_INCREMENT PRIMARY KEY, pattern VARCHAR(150) NOT NULL, room_id INT NOT NULL, priority INT NOT NULL DEFAULT 10, FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE CASCADE);
CREATE TABLE clients(
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, ip VARCHAR(45), mac CHAR(17) NOT NULL UNIQUE,
 room_id INT NULL, token_hash CHAR(64) NULL, status ENUM('pending','approved','active') NOT NULL DEFAULT 'approved',
 last_seen DATETIME NULL, agent_version VARCHAR(20), created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX(token_hash), FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE SET NULL);
CREATE TABLE scan_jobs(id INT AUTO_INCREMENT PRIMARY KEY, cidr VARCHAR(18) NOT NULL, status ENUM('queued','running','done','failed') DEFAULT 'queued', progress TINYINT DEFAULT 0, message VARCHAR(255), started_at DATETIME DEFAULT CURRENT_TIMESTAMP, finished_at DATETIME NULL);
CREATE TABLE scan_results(id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL, ip VARCHAR(45), mac CHAR(17) NOT NULL, hostname VARCHAR(100), vendor VARCHAR(100), room_id INT NULL, INDEX(job_id), FOREIGN KEY(job_id) REFERENCES scan_jobs(id) ON DELETE CASCADE);
CREATE TABLE activity_logs(id INT AUTO_INCREMENT PRIMARY KEY, user VARCHAR(50), action VARCHAR(100), target VARCHAR(100), status VARCHAR(20) DEFAULT 'Sukses', note VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
-- Tabel untuk Fase 3 (dibuat sekarang agar skema stabil)
CREATE TABLE tasks(id INT AUTO_INCREMENT PRIMARY KEY, client_id INT NOT NULL, type VARCHAR(30) NOT NULL, payload JSON NULL, status ENUM('pending','sent','running','done','failed') DEFAULT 'pending', result VARCHAR(255) NULL, created_by VARCHAR(50), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL, finished_at DATETIME NULL, FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE);
INSERT INTO rooms(name) VALUES('Lab 1'),('Lab 2'),('Lab 3'),('Lab 4');
-- Aturan awal (regex, tidak peka huruf besar/kecil). Sesuaikan di menu Pengaturan.
INSERT INTO room_rules(pattern,room_id,priority) VALUES('^LAB1[-_]',1,10),('^LAB2[-_]',2,10),('^LAB3[-_]',3,10),('^LAB4[-_]',4,10);

CREATE TABLE programs(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, type ENUM('app','url') NOT NULL, path VARCHAR(500) NOT NULL, parameter VARCHAR(255) NULL, allowed TINYINT(1) NOT NULL DEFAULT 1);
INSERT INTO programs(name,type,path,parameter) VALUES('Notepad','app','C:\\Windows\\notepad.exe',NULL),('Calculator','app','C:\\Windows\\System32\\calc.exe',NULL),('File Explorer','app','C:\\Windows\\explorer.exe',NULL),('Browser CBT','url','https://cbt.man2kotamadiun.sch.id','chrome');

CREATE TABLE schedules(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, run_time TIME NOT NULL, days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5', action VARCHAR(20) NOT NULL, mode ENUM('all','room') NOT NULL DEFAULT 'all', room_id INT NULL, payload JSON NULL, active TINYINT(1) NOT NULL DEFAULT 1, last_run DATE NULL, last_result VARCHAR(255) NULL, FOREIGN KEY(room_id) REFERENCES rooms(id) ON DELETE CASCADE);
