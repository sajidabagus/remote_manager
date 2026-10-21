ALTER TABLE tasks ADD COLUMN sent_at DATETIME NULL AFTER created_at;
CREATE TABLE programs(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, type ENUM('app','url') NOT NULL, path VARCHAR(500) NOT NULL, parameter VARCHAR(255) NULL, allowed TINYINT(1) NOT NULL DEFAULT 1);
INSERT INTO programs(name,type,path,parameter) VALUES('Notepad','app','C:\\Windows\\notepad.exe',NULL),('Calculator','app','C:\\Windows\\System32\\calc.exe',NULL),('File Explorer','app','C:\\Windows\\explorer.exe',NULL),('Browser CBT','url','https://cbt.man2kotamadiun.sch.id','chrome');
