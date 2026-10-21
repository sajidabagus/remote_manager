<?php
// Dipanggil otomatis oleh scan.php: php cli/scan.php <job_id>
define('NO_SESSION',1); require __DIR__.'/../includes/bootstrap.php';
$id=(int)($argv[1]??0); $win=PHP_OS_FAMILY==='Windows';
function job($id,$status,$prog,$msg=null){
  db()->prepare('UPDATE scan_jobs SET status=?,progress=?,message=COALESCE(?,message),finished_at=IF(?IN("done","failed"),NOW(),NULL) WHERE id=?')->execute([$status,$prog,$msg,$status,$id]);
}
function arp_table(){
  $o=PHP_OS_FAMILY==='Windows'?shell_exec('arp -a'):(shell_exec('ip neigh 2>/dev/null')?:shell_exec('arp -an 2>/dev/null'));
  $t=[]; preg_match_all('/(\d{1,3}(?:\.\d{1,3}){3}).*?((?:[0-9a-f]{2}[:-]){5}[0-9a-f]{2})/i',(string)$o,$m,PREG_SET_ORDER);
  foreach($m as $x){ $mac=norm_mac($x[2]); if($mac && $mac!=='FF:FF:FF:FF:FF:FF' && strpos($mac,'01:00:5E')!==0) $t[$x[1]]=$mac; }
  return $t;
}
try{
  $s=db()->prepare('SELECT * FROM scan_jobs WHERE id=?'); $s->execute([$id]); $j=$s->fetch(); if(!$j) exit(1);
  job($id,'running',5);
  [$net,$bits]=explode('/',$j['cidr']); $bits=(int)$bits;
  $mask=(0xFFFFFFFF<<(32-$bits))&0xFFFFFFFF; $start=ip2long($net)&$mask; $cnt=1<<(32-$bits);
  $found=[]; // ip => [mac,host,vendor]
  // 1) nmap bila ada
  $x=shell_exec('nmap -sn -oX - '.escapeshellarg($j['cidr']).' 2>&1');
  if($x && strpos($x,'<nmaprun')!==false && ($xml=@simplexml_load_string($x))){
    foreach($xml->host as $hst){
      if((string)$hst->status['state']!=='up') continue; $ip=$mac=$ven=null;
      foreach($hst->address as $a){ if($a['addrtype']=='ipv4')$ip=(string)$a['addr']; if($a['addrtype']=='mac'){$mac=norm_mac((string)$a['addr']);$ven=(string)$a['vendor'];} }
      if($ip) $found[$ip]=[$mac,short_host($hst->hostnames->hostname['name']??''),$ven];
    }
  } else { // 2) cadangan: ping sweep paralel
    $null=$win?'NUL':'/dev/null'; $ips=[]; for($i=1;$i<$cnt-1;$i++) $ips[]=long2ip($start+$i);
    foreach(array_chunk($ips,64) as $k=>$chunk){
      $pr=[]; foreach($chunk as $ip){ $cmd=$win?['ping','-n','1','-w','1000',$ip]:['ping','-c','1','-W','1',$ip];
        $pr[$ip]=proc_open($cmd,[1=>['file',$null,'w'],2=>['file',$null,'w']],$pp); }
      foreach($pr as $ip=>$p){ while(true){ $st=proc_get_status($p); if(!$st['running']){ if($st['exitcode']===0)$found[$ip]=[null,'',null]; break; } usleep(50000);} proc_close($p); }
      job($id,'running',min(60,5+(int)(55*($k+1)*64/max(1,count($ips)))));
    }
  }
  job($id,'running',65);
  // 3) lengkapi MAC (ARP) dan nama
  $arp=arp_table(); $saved=0; $nomac=0; $ins=db()->prepare('INSERT INTO scan_results(job_id,ip,mac,hostname,vendor,room_id) VALUES(?,?,?,?,?,?)');
  foreach($found as $ip=>[$mac,$host,$ven]){
    $mac=$mac?:($arp[$ip]??null); if(!$mac){$nomac++;continue;}
    if($host==='' ){ $g=@gethostbyaddr($ip); $host=($g&&$g!==$ip)?short_host($g):''; }
    $ins->execute([$id,$ip,$mac,$host?:null,$ven?:null,room_for_name($host)]); $saved++;
  }
  job($id,'done',100,"$saved perangkat ditemukan".($nomac?", $nomac tanpa MAC dilewati (beda segmen?)":''));
}catch(Throwable $e){ job($id,'failed',0,substr($e->getMessage(),0,250)); }
