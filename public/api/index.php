<?php
define('NO_SESSION',1); require __DIR__.'/../../includes/bootstrap.php';
header('Content-Type: application/json');
function out($d,$c=200){ http_response_code($c); echo json_encode($d); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST') out(['error'=>'method'],405);
$in=json_decode(file_get_contents('php://input'),true)?:[];
$r=$_GET['r']??''; $ip=$_SERVER['REMOTE_ADDR'];
function auth_client(){ // token dikirim lewat header X-Agent-Token (aman dari masalah Authorization di Apache/XAMPP)
  $t=$_SERVER['HTTP_X_AGENT_TOKEN']??''; if(!preg_match('/^[0-9a-f]{64}$/i',$t)) out(['error'=>'unauthorized'],401);
  $s=db()->prepare("SELECT * FROM clients WHERE token_hash=? AND status='active'"); $s->execute([hash('sha256',$t)]);
  return $s->fetch()?:out(['error'=>'unauthorized'],401);
}
if($r==='enroll'){
  $mac=norm_mac($in['mac']??''); $host=short_host(substr($in['hostname']??'',0,100)); if(!$mac||$host==='') out(['error'=>'data tidak valid'],400);
  $s=db()->prepare('SELECT * FROM clients WHERE mac=?'); $s->execute([$mac]); $c=$s->fetch();
  if(!$c){ db()->prepare("INSERT INTO clients(name,ip,mac,room_id,status,agent_version) VALUES(?,?,?,?, 'pending',?)")->execute([$host,$ip,$mac,room_for_name($host),substr($in['version']??'',0,20)]);
    log_activity('Agent minta persetujuan',$host,'Sukses',$mac,'system'); out(['status'=>'pending']); }
  if($c['status']==='pending') out(['status'=>'pending']);
  if($c['status']==='active') out(['status'=>'already_enrolled','hint'=>'Minta admin mereset token'],409);
  $tok=bin2hex(random_bytes(32));
  db()->prepare("UPDATE clients SET token_hash=?,status='active',ip=?,name=?,last_seen=NOW(),agent_version=? WHERE id=?")->execute([hash('sha256',$tok),$ip,$host,substr($in['version']??'',0,20),$c['id']]);
  log_activity('Agent terdaftar',$host,'Sukses',$mac,'system'); out(['status'=>'ok','token'=>$tok,'interval'=>HEARTBEAT_INTERVAL]);
}
$c=auth_client();
if($r==='heartbeat'){
  expire_tasks();
  db()->prepare('UPDATE clients SET ip=?,last_seen=NOW(),agent_version=COALESCE(?,agent_version) WHERE id=?')->execute([$ip,isset($in['version'])?substr($in['version'],0,20):null,$c['id']]);
  $n=db()->prepare("SELECT COUNT(*) FROM tasks WHERE client_id=? AND status='pending'"); $n->execute([$c['id']]);
  out(['ok'=>true,'interval'=>HEARTBEAT_INTERVAL,'tasks'=>(int)$n->fetchColumn()]);
}
if($r==='tasks/pending'){
  $s=db()->prepare("SELECT id,type,payload FROM tasks WHERE client_id=? AND status='pending' ORDER BY id LIMIT 5"); $s->execute([$c['id']]); $rows=$s->fetchAll();
  $u=db()->prepare("UPDATE tasks SET status='sent',sent_at=NOW() WHERE id=? AND status='pending'"); $res=[];
  foreach($rows as $t){ $u->execute([$t['id']]); if($u->rowCount()) $res[]=['id'=>(int)$t['id'],'type'=>$t['type'],'payload'=>$t['payload']?json_decode($t['payload'],true):new stdClass]; }
  out(['tasks'=>$res]);
}
if($r==='tasks/result'){
  $ok=($in['status']??'')==='done'; $msg=substr((string)($in['message']??''),0,250);
  $s=db()->prepare("UPDATE tasks SET status=?,result=?,finished_at=NOW() WHERE id=? AND client_id=? AND status IN('sent','running')");
  $s->execute([$ok?'done':'failed',$msg,(int)($in['id']??0),$c['id']]);
  if($s->rowCount()){ $t=db()->prepare('SELECT type FROM tasks WHERE id=?'); $t->execute([(int)$in['id']]); log_activity('Tugas '.$t->fetchColumn(),$c['name'],$ok?'Sukses':'Gagal',$msg,'agent'); }
  out(['ok'=>true]);
}
out(['error'=>'not found'],404);
