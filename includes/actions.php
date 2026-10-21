<?php
// Logika aksi bersama: dipakai Kontrol Client, Kelola Client, dan Scheduler.
const ACT=['lock'=>'Kunci Perangkat','unlock'=>'Buka Kunci','message'=>'Kirim Pesan','open_url'=>'Buka Browser & URL','run_app'=>'Jalankan Aplikasi','restart'=>'Restart Client','shutdown'=>'Matikan Client','wake'=>'Nyalakan (Wake-on-LAN)'];

function wol_send(string $mac): bool { // magic packet: 6x FF + 16x MAC, UDP broadcast
  $hex=str_replace(':','',$mac); if(strlen($hex)!==12||!ctype_xdigit($hex)) return false;
  $pkt=str_repeat("\xFF",6).str_repeat(hex2bin($hex),16); $ok=false;
  $bc=array_unique([defined('WOL_BROADCAST')?WOL_BROADCAST:'255.255.255.255','255.255.255.255']);
  foreach($bc as $ip) foreach([9,7] as $port){
    if(function_exists('socket_create')){
      $s=@socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);
      if($s){ @socket_set_option($s,SOL_SOCKET,SO_BROADCAST,1); if(@socket_sendto($s,$pkt,strlen($pkt),0,$ip,$port)!==false)$ok=true; socket_close($s); }
    } else { $f=@stream_socket_client("udp://$ip:$port",$en,$es,1); if($f){ if(@fwrite($f,$pkt))$ok=true; fclose($f); } }
  }
  return $ok;
}

function build_payload(string $a,array $d,&$err): array {
  switch($a){
    case 'lock': return ['message'=>mb_substr(trim($d['lock_msg']??''),0,500)];
    case 'message': $t=mb_substr(trim($d['msg_text']??''),0,500); if($t==='')$err='Pesan masih kosong.'; return ['text'=>$t,'show_ok'=>!empty($d['show_ok'])];
    case 'open_url': $u=trim($d['url']??''); if(!filter_var($u,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$u))$err='URL harus diawali http:// atau https://';
      return ['url'=>$u,'browser'=>in_array($d['browser']??'',['chrome','edge'])?$d['browser']:'default','kiosk'=>!empty($d['kiosk'])];
    case 'run_app': $s=db()->prepare("SELECT path,parameter FROM programs WHERE id=? AND type='app' AND allowed=1"); $s->execute([(int)($d['program']??0)]); $g=$s->fetch();
      if(!$g){$err='Program tidak ada di daftar yang diizinkan.';return [];} return ['path'=>$g['path'],'args'=>(string)$g['parameter']];
  }
  return [];
}

// $online=true: client online (untuk tugas); false: client offline (untuk Wake-on-LAN)
function pick_targets(string $mode,int $room,array $ids,bool $online=true): array {
  $cond=$online?is_online_sql('c'):"c.status IN('active','approved') AND NOT ".is_online_sql('c');
  $sql="SELECT c.id,c.name,c.mac FROM clients c WHERE $cond"; $p=[];
  if($mode==='room'){ $sql.=' AND c.room_id=?'; $p[]=$room; }
  elseif($mode==='pick'){ $ids=array_map('intval',$ids); if(!$ids) return []; $sql.=' AND c.id IN('.implode(',',$ids).')'; }
  $s=db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}

function run_action(string $a,array $targets,array $pl): int {
  $n=0; foreach($targets as $t){ if($a==='wake'){ if(wol_send($t['mac']))$n++; } else { create_task($t['id'],$a,$pl); $n++; } } return $n;
}

function run_schedule(array $s): int { // dipanggil scheduler / tombol "Jalankan sekarang"
  $t=pick_targets($s['mode'],(int)$s['room_id'],[],$s['action']!=='wake');
  $n=run_action($s['action'],$t,$s['payload']?(json_decode($s['payload'],true)?:[]):[]);
  $res=$n.' client'.($t?'':' (tidak ada target yang sesuai)');
  db()->prepare('UPDATE schedules SET last_result=? WHERE id=?')->execute([$res,$s['id']]);
  log_activity('Jadwal: '.$s['name'],ACT[$s['action']]??$s['action'],'Sukses',$res);
  return $n;
}
