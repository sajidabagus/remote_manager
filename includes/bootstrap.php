<?php
require __DIR__.'/../config.php';
date_default_timezone_set(APP_TZ);
if (PHP_SAPI!=='cli' && !defined('NO_SESSION')) {
  session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
function db(): PDO {
  static $pdo;
  if(!$pdo){
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='".DB_TZ_OFFSET."'");
  }
  return $pdo;
}
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function norm_mac($m){ $m=strtoupper(preg_replace('/[^0-9A-Fa-f]/','',(string)$m)); return strlen($m)===12 ? implode(':',str_split($m,2)) : null; }
function short_host($n){ $n=trim((string)$n); return $n===''?'':explode('.',$n)[0]; }
function room_for_name($name){
  if(!$name) return null;
  foreach(db()->query('SELECT pattern,room_id FROM room_rules ORDER BY priority,id') as $r)
    if(@preg_match('~'.str_replace('~','\~',$r['pattern']).'~i',$name)===1) return (int)$r['room_id'];
  return null;
}
function log_activity($action,$target='',$status='Sukses',$note='',$user=null){
  $user=$user??($_SESSION['admin']['username']??'system');
  db()->prepare('INSERT INTO activity_logs(user,action,target,status,note) VALUES(?,?,?,?,?)')->execute([$user,$action,$target,$status,$note]);
}
function csrf(){ return $_SESSION['csrf']??($_SESSION['csrf']=bin2hex(random_bytes(16))); }
function csrf_field(){ return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
function csrf_check(){
  if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){ http_response_code(419); exit('CSRF token tidak valid'); }
}
function require_login(){ if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; } }
function flash($msg,$type='success'){ $_SESSION['flash']=[$msg,$type]; }
function is_online_sql($a='c'){ return "($a.status='active' AND $a.last_seen >= NOW() - INTERVAL ".ONLINE_SECONDS." SECOND)"; }

function create_task($clientId,$type,$payload=[]){
  db()->prepare('INSERT INTO tasks(client_id,type,payload,created_by) VALUES(?,?,?,?)')->execute([$clientId,$type,json_encode($payload?:new stdClass),$_SESSION['admin']['username']??'system']);
}
function expire_tasks(){ // tugas macet: dikirim tapi tak dilaporkan > 2 menit; menunggu > 10 menit (client offline)
  db()->exec("UPDATE tasks SET status='failed',result='Tidak ada laporan dari agent (timeout)',finished_at=NOW() WHERE status='sent' AND sent_at < NOW() - INTERVAL 2 MINUTE");
  db()->exec("UPDATE tasks SET status='failed',result='Kedaluwarsa: client tidak mengambil tugas',finished_at=NOW() WHERE status='pending' AND created_at < NOW() - INTERVAL 10 MINUTE");
}
require __DIR__.'/actions.php';
