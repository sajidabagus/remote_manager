<?php
// Pakai: php cli/create_admin.php username password
define('NO_SESSION',1); require __DIR__.'/../includes/bootstrap.php';
[$u,$p]=[$argv[1]??'',$argv[2]??''];
if($u===''||strlen($p)<8) exit("Pakai: php create_admin.php <username> <password minimal 8 karakter>\n");
db()->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)')->execute([$u,password_hash($p,PASSWORD_DEFAULT)]);
echo "Admin '$u' siap.\n";
