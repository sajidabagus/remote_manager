<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
if(isset($_GET['ajax'])){ // polling status
  $s=db()->prepare('SELECT * FROM scan_jobs WHERE id=?');$s->execute([(int)$_GET['ajax']]);$j=$s->fetch();
  $s=db()->prepare('SELECT s.id,s.ip,s.mac,s.hostname,s.vendor,s.room_id,c.status cstatus FROM scan_results s LEFT JOIN clients c ON c.mac=s.mac WHERE s.job_id=? ORDER BY INET_ATON(s.ip)');$s->execute([(int)$_GET['ajax']]);
  header('Content-Type: application/json'); echo json_encode(['job'=>$j,'rows'=>$s->fetchAll()]); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  if(($_POST['action']??'')==='start'){
    $c=trim($_POST['cidr']??''); $ok=preg_match('~^(\d{1,3}(?:\.\d{1,3}){3})/(\d{2})$~',$c,$m)&&ip2long($m[1])!==false&&$m[2]>=22&&$m[2]<=30;
    if(!$ok){ flash('CIDR tidak valid. Contoh 192.168.1.0/24 (minimal /22).','danger'); header('Location: scan.php'); exit; }
    db()->prepare('INSERT INTO scan_jobs(cidr) VALUES(?)')->execute([$c]); $id=(int)db()->lastInsertId();
    $script=realpath(__DIR__.'/../cli/scan.php');
    if(PHP_OS_FAMILY==='Windows') pclose(popen('start /B "" '.PHP_CLI.' "'.$script.'" '.$id.' > NUL 2>&1','r'));
    else exec('nohup '.escapeshellarg(PHP_CLI).' '.escapeshellarg($script).' '.$id.' > /dev/null 2>&1 &');
    log_activity('Scan Jaringan',$c); header('Location: scan.php?job='.$id); exit;
  }
  if(($_POST['action']??'')==='register'){
    $job=(int)$_POST['job']; $new=0; $skip=0;
    $sel=db()->prepare('SELECT * FROM scan_results WHERE id=? AND job_id=?');
    $ins=db()->prepare("INSERT IGNORE INTO clients(name,ip,mac,room_id,status) VALUES(?,?,?,?, 'approved')");
    foreach((array)($_POST['sel']??[]) as $rid){
      $sel->execute([(int)$rid,$job]); $r=$sel->fetch(); if(!$r) continue;
      $room=(int)($_POST['room'][$rid]??0)?:null;
      $ins->execute([$r['hostname']?:$r['ip'],$r['ip'],$r['mac'],$room]); $ins->rowCount()?$new++:$skip++;
    }
    log_activity('Daftarkan perangkat','scan #'.$job,'Sukses',"$new baru, $skip sudah ada");
    flash("$new perangkat didaftarkan ($skip sudah terdaftar). Menunggu Agent terpasang."); header('Location: clients.php'); exit;
  }
}
$job=(int)($_GET['job']??0);
page_header('Scan Jaringan','scan','Temukan komputer di jaringan lab dan daftarkan.'); ?>
<div class="card mb-3"><div class="card-body"><form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="start">
<div class="col-md-4"><label class="form-label">Rentang IP (CIDR)</label><input name="cidr" class="form-control" value="192.168.1.0/24"></div>
<div class="col-auto"><button class="btn btn-primary"><i class="bi bi-wifi"></i> Mulai Scan</button></div></form>
<small class="text-muted">Server harus berada di segmen jaringan yang sama agar MAC terbaca.</small></div></div>
<?php if($job): ?><div class="card"><div class="card-body"><div id="st" class="mb-2">Memulai...</div>
<div class="progress mb-3"><div id="bar" class="progress-bar" style="width:0%"></div></div>
<form method="post" id="f"><?=csrf_field()?><input type="hidden" name="action" value="register"><input type="hidden" name="job" value="<?=$job?>">
<table class="table table-sm align-middle"><thead><tr><th><input type="checkbox" id="all"></th><th>IP</th><th>MAC</th><th>Nama Perangkat</th><th>Vendor</th><th>Ruang</th><th>Status</th></tr></thead><tbody id="tb"></tbody></table>
<button class="btn btn-success"><i class="bi bi-check2-square"></i> Daftarkan yang dipilih</button></form></div></div>
<script>
const ROOMS=<?=json_encode($rooms)?>, JOB=<?=$job?>;let rendered=false;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function poll(){
  const d=await (await fetch('scan.php?ajax='+JOB)).json(), j=d.job;
  document.getElementById('bar').style.width=j.progress+'%';
  document.getElementById('st').textContent=j.status==='done'?'Selesai: '+j.message:j.status==='failed'?'Gagal: '+j.message:'Scanning... '+j.progress+'%';
  if(j.status==='done'&&!rendered){rendered=true;
    document.getElementById('tb').innerHTML=d.rows.map(r=>{
      const lbl={null:'<span class="badge text-bg-primary">Baru</span>',approved:'<span class="badge text-bg-secondary">Menunggu Agent</span>',active:'<span class="badge text-bg-success">Agent aktif</span>',pending:'<span class="badge text-bg-warning">Perlu persetujuan</span>'}[r.cstatus];
      const opts='<option value="0">- Belum ada ruang -</option>'+ROOMS.map(o=>`<option value="${o.id}"${o.id==r.room_id?' selected':''}>${esc(o.name)}</option>`).join('');
      return `<tr><td>${r.cstatus?'':`<input type="checkbox" name="sel[]" value="${r.id}" checked>`}</td><td>${esc(r.ip)}</td><td class="small">${esc(r.mac)}</td><td>${esc(r.hostname||'-')}</td><td class="small text-muted">${esc(r.vendor||'')}</td><td><select class="form-select form-select-sm" name="room[${r.id}]">${opts}</select></td><td>${lbl}</td></tr>`}).join('')||'<tr><td colspan=7 class="text-muted">Tidak ada perangkat.</td></tr>';
    document.getElementById('all').onchange=e=>document.querySelectorAll('[name="sel[]"]').forEach(c=>c.checked=e.target.checked);
  }
  if(j.status==='queued'||j.status==='running') setTimeout(poll,2000);
}
poll();</script><?php endif; page_footer();
