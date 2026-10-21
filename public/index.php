<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
// Semua form di halaman ini POST ke control.php (sudah memeriksa CSRF) lalu kembali ke index.php.
$on=is_online_sql('c');
$st=db()->query("SELECT SUM(status IN('approved','active')) total, SUM($on) online, SUM(status='pending') pending FROM clients c")->fetch();
$total=(int)$st['total'];$online=(int)$st['online'];$offline=$total-$online;$pending=(int)$st['pending'];expire_tasks();$proc=(int)db()->query("SELECT COUNT(*) FROM tasks WHERE status IN('pending','sent')")->fetchColumn();
$q=trim($_GET['q']??'');$room=(int)($_GET['room']??0);$f=$_GET['st']??'';$per=(int)($_GET['per']??10); if(!in_array($per,[10,25,50],true)) $per=10;$pg=max(1,(int)($_GET['p']??1));
$w=["c.status IN('approved','active')"];$p=[];
if($q!==''){$w[]="(c.name LIKE ? OR c.ip LIKE ? OR r.name LIKE ?)";array_push($p,"%$q%","%$q%","%$q%");}
if($room>0){$w[]='c.room_id=?';$p[]=$room;}
if($f==='online')$w[]=$on; if($f==='offline')$w[]="NOT $on";
$W=implode(' AND ',$w);
$s=db()->prepare("SELECT COUNT(*) FROM clients c LEFT JOIN rooms r ON r.id=c.room_id WHERE $W");$s->execute($p);$n=(int)$s->fetchColumn();
$pages=max(1,(int)ceil($n/$per));$pg=min($pg,$pages);$off=($pg-1)*$per;
$s=db()->prepare("SELECT c.*,r.name room,$on is_on FROM clients c LEFT JOIN rooms r ON r.id=c.room_id WHERE $W ORDER BY c.name LIMIT $per OFFSET $off");$s->execute($p);$rows=$s->fetchAll();
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
$all=db()->query("SELECT id,name FROM clients WHERE status IN('approved','active') ORDER BY name")->fetchAll();
$logs=db()->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 6')->fetchAll();
$favs=db()->query('SELECT * FROM programs WHERE allowed=1 ORDER BY type,name LIMIT 5')->fetchAll();
$apps=db()->query("SELECT id,name FROM programs WHERE type='app' AND allowed=1 ORDER BY name")->fetchAll();
$dow=(int)date('N');
$today=array_values(array_filter(db()->query('SELECT s.*,r.name room FROM schedules s LEFT JOIN rooms r ON r.id=s.room_id WHERE s.active=1 ORDER BY s.run_time')->fetchAll(),fn($x)=>in_array($dow,array_map('intval',explode(',',$x['days'])),true)));
$qs=fn($x)=>h(http_build_query(array_merge(['q'=>$q,'room'=>$room,'st'=>$f,'per'=>$per],$x)));
$CF=csrf_field();
$opts=function() use($rooms,$all,$total){ $o='<option value="all">Semua komputer ('.$total.')</option><optgroup label="Per ruang">'; foreach($rooms as $r) $o.='<option value="room:'.$r['id'].'">'.h($r['name']).'</option>';
  $o.='</optgroup><optgroup label="Satu komputer">'; foreach($all as $c) $o.='<option value="pc:'.$c['id'].'">'.h($c['name']).'</option>'; return $o.'</optgroup>'; };
$TPL=['Ujian CBT akan segera dimulai. Mohon persiapkan diri.','Perangkat akan otomatis dimatikan dalam 10 menit.','Jangan membuka aplikasi lain selama ujian berlangsung.','Waktu praktikum tersisa 5 menit. Simpan pekerjaan Anda.'];
page_header('Dashboard','index','Kelola dan pantau semua komputer client dari satu tempat.'); ?>
<?php if($pending): ?><div class="alert alert-warning py-2"><i class="bi bi-bell me-1"></i><?=$pending?> perangkat menunggu persetujuan. <a href="clients.php">Lihat</a></div><?php endif; ?>
<form method="post" action="control.php" id="massForm"><?=$CF?><input type="hidden" name="back" value="index.php"><input type="hidden" name="action"><input type="hidden" name="mode" value="all"></form>
<form method="post" action="control.php" id="rowForm"><?=$CF?><input type="hidden" name="back" value="index.php"><input type="hidden" name="action"><input type="hidden" name="target"></form>
<div class="row g-3">
<div class="col-xl-8">
 <div class="row g-3 mb-3">
 <?php foreach([['Total Client',$total,'s-blue','bi-pc-display','Semua perangkat'],['Online',$online,'s-green','bi-wifi',$total?round($online/$total*100).'%':''],['Offline',$offline,'s-red','bi-wifi-off',$total?round($offline/$total*100).'%':''],['Dalam Proses',$proc,'s-purple','bi-arrow-repeat','Menjalankan perintah']] as $c): ?>
 <div class="col-6 col-lg-3"><div class="stat <?=$c[2]?>"><div class="ic"><i class="bi <?=$c[3]?>"></i></div><div><div class="lb"><?=$c[0]?></div><div class="v"><?=$c[1]?></div><small class="text-muted"><?=h($c[4])?></small></div></div></div>
 <?php endforeach; ?></div>
 <div class="card mb-3"><div class="card-body"><div class="card-title mb-3">Daftar Komputer Client</div>
 <form class="row g-2 mb-3"><div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?=h($q)?>" placeholder="Cari nama komputer, IP, atau ruang..."></div></div>
 <div class="col-md-3"><select name="room" class="form-select" onchange="this.form.submit()"><option value="0">Semua Ruang</option><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'"'.($room==$r['id']?' selected':'').'>'.h($r['name']).'</option>'; ?></select></div>
 <div class="col-md-3"><select name="st" class="form-select" onchange="this.form.submit()"><option value="">Semua Status</option><option value="online"<?=$f==='online'?' selected':''?>>Online</option><option value="offline"<?=$f==='offline'?' selected':''?>>Offline</option></select></div>
 <div class="col-md-2 d-flex align-items-center gap-2"><small class="text-muted">Tampilkan:</small><select name="per" class="form-select" onchange="this.form.submit()"><?php foreach([10,25,50] as $x) echo "<option".($per==$x?' selected':'').">$x</option>"; ?></select></div></form>
 <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><input class="form-check-input" type="checkbox" id="chkAll"></th><th>No</th><th>Nama Client</th><th>IP Address</th><th>Ruang</th><th>Status</th><th>Aktivitas Terakhir</th><th>Aksi</th></tr></thead><tbody>
 <?php foreach($rows as $i=>$r): ?><tr><td><input class="form-check-input chk" type="checkbox" form="massForm" name="ids[]" value="<?=$r['id']?>"></td><td><?=$off+$i+1?></td><td class="fw-semibold"><?=h($r['name'])?></td><td><?=h($r['ip']?:'-')?></td><td><?=h($r['room']?:'Belum ada ruang')?></td>
 <td><?php if($r['status']==='approved') echo '<span class="badge-s b-idle">Menunggu Agent</span>'; elseif($r['is_on']) echo '<span class="st-on">Online</span>'; else echo '<span class="st-off">Offline</span>'; ?></td>
 <td><?=$r['last_seen']?h(date('d M H:i',strtotime($r['last_seen']))):'-'?></td>
 <td class="text-nowrap"><a class="ra ra-run" title="Jalankan program" href="control.php?act=run_app"><i class="bi bi-play-fill"></i></a>
 <button class="ra ra-pw" title="<?=$r['is_on']?'Matikan':'Nyalakan (WoL)'?>" onclick="rowAct('<?=$r['is_on']?'shutdown':'wake'?>',<?=$r['id']?>,'<?=h($r['name'])?>')"><i class="bi bi-power"></i></button>
 <a class="ra ra-send" title="Kirim pesan" href="#pesan" onclick="pickTarget(<?=$r['id']?>)"><i class="bi bi-send-fill"></i></a>
 <span class="dropdown"><button class="ra ra-more" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu">
 <?php foreach(['lock'=>'Kunci','unlock'=>'Buka Kunci','restart'=>'Restart'] as $k=>$v) echo '<li><a class="dropdown-item" href="#" onclick="rowAct(\''.$k.'\','.$r['id'].',\''.h($r['name']).'\');return false">'.$v.'</a></li>'; ?></ul></span></td></tr>
 <?php endforeach; if(!$rows) echo '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada client. Mulai dari menu <a href="scan.php">Scan Jaringan</a>.</td></tr>'; ?></tbody></table></div>
 <div class="d-flex justify-content-between align-items-center"><small class="text-muted">Menampilkan <?=$n?$off+1:0?> - <?=$off+count($rows)?> dari <?=$n?> data</small>
 <ul class="pagination pagination-sm mb-0"><li class="page-item<?=$pg<=1?' disabled':''?>"><a class="page-link" href="?<?=$qs(['p'=>$pg-1])?>">&lsaquo;</a></li><?php for($i=max(1,$pg-2);$i<=min($pages,$pg+2);$i++) echo '<li class="page-item'.($i==$pg?' active':'').'"><a class="page-link" href="?'.$qs(['p'=>$i]).'">'.$i.'</a></li>'; ?><li class="page-item<?=$pg>=$pages?' disabled':''?>"><a class="page-link" href="?<?=$qs(['p'=>$pg+1])?>">&rsaquo;</a></li></ul></div></div></div>
 <div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><div class="card-title"><i class="bi bi-clock-history text-primary me-1"></i> Log Aktivitas Terbaru</div><a href="logs.php" class="small">Lihat Semua</a></div>
  <table class="table table-sm mt-2 mb-0"><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Target</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
  <?php foreach($logs as $l) echo '<tr><td>'.h(date('H:i',strtotime($l['created_at']))).'</td><td>'.h($l['user']).'</td><td>'.h($l['action']).'</td><td>'.h($l['target']).'</td><td><span class="badge-s '.($l['status']==='Sukses'?'b-ok':'b-fail').'">'.h($l['status']).'</span></td><td class="text-muted small">'.h($l['note']).'</td></tr>';
  if(!$logs) echo '<tr><td colspan="6" class="text-muted text-center">Belum ada log.</td></tr>'; ?></tbody></table></div></div></div>
  <div class="col-lg-5"><div class="card h-100" id="pesan"><div class="card-body"><div class="card-title mb-2"><i class="bi bi-chat-dots-fill text-primary me-1"></i> Pesan ke Client</div>
  <ul class="nav nav-tabs mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tLangsung" type="button">Pesan Langsung</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tTemplate" type="button">Template</button></li></ul>
  <form method="post" action="control.php"><?=$CF?><input type="hidden" name="back" value="index.php"><input type="hidden" name="action" value="message">
  <label class="form-label small fw-semibold">Target</label><select name="target" id="msgTarget" class="form-select form-select-sm mb-2"><?=$opts()?></select>
  <div class="tab-content"><div class="tab-pane show active" id="tLangsung"></div><div class="tab-pane" id="tTemplate"><select class="form-select form-select-sm mb-2" onchange="if(this.value)document.getElementById('msgText').value=this.value"><option value="">Pilih template...</option><?php foreach($TPL as $t) echo '<option>'.h($t).'</option>'; ?></select></div></div>
  <label class="form-label small fw-semibold">Pesan</label><textarea name="msg_text" id="msgText" class="form-control" rows="3" maxlength="500" placeholder="Tulis pesan yang akan dikirim ke client..." required></textarea>
  <label class="small mt-2 d-block"><input type="checkbox" class="form-check-input" name="show_ok" value="1" checked> Tampilkan tombol OK (wajib ditekan)</label>
  <button class="btn btn-primary w-100 mt-2"><i class="bi bi-send-fill"></i> Kirim Pesan</button></form></div></div></div>
 </div>
</div>
<div class="col-xl-4">
 <div class="card mb-3"><div class="card-body"><div class="card-title">Kontrol Massal</div><div class="sub mb-2">Berlaku untuk client yang dicentang, atau semua jika tidak ada yang dicentang.</div>
 <div class="mass"><button class="m-green" onclick="mass('wake','Nyalakan')"><i class="bi bi-power"></i>Nyalakan Semua</button><button class="m-blue" onclick="location='control.php?act=run_app'"><i class="bi bi-play"></i>Jalankan Program</button>
 <button class="m-orange" onclick="mass('restart','Restart')"><i class="bi bi-arrow-repeat"></i>Restart Client</button><button class="m-red" onclick="mass('shutdown','Matikan')"><i class="bi bi-power"></i>Matikan Semua</button></div></div></div>
 <div class="card mb-3"><div class="card-body"><div class="card-title mb-2"><i class="bi bi-lightning-charge text-primary"></i> Aksi Cepat</div><div class="tiles">
 <?php foreach([['t1','bi-globe','Buka Browser & URL','control.php?act=open_url'],['t2','bi-grid-fill','Jalankan Aplikasi','control.php?act=run_app'],['t3','bi-lock-fill','Kunci Perangkat','#kunci'],['t4','bi-envelope-fill','Kirim Pesan','#pesan'],
  ['t5','bi-arrow-clockwise','Restart Client','control.php?act=restart'],['t6','bi-power','Matikan Client','control.php?act=shutdown'],['t7','bi-calendar-week','Jadwal','schedules.php'],['t8','bi-file-earmark-text','Lihat Log','logs.php']] as $t) echo '<a class="tile '.$t[0].'" href="'.$t[3].'"><i class="bi '.$t[1].'"></i>'.$t[2].'</a>'; ?></div></div></div>
 <div class="card mb-3" id="kunci"><div class="card-body"><div class="card-title"><i class="bi bi-lock-fill text-primary"></i> Kunci Perangkat</div><div class="sub mb-2">Mengunci layar client dan menampilkan pesan.</div>
 <form method="post" action="control.php" onsubmit="return lockSubmit(this)"><?=$CF?><input type="hidden" name="back" value="index.php"><input type="hidden" name="action" value="lock">
 <label class="form-label small fw-semibold">Target</label><select name="target" class="form-select form-select-sm mb-2"><?=$opts()?></select>
 <label class="form-label small fw-semibold d-block">Jenis Kunci</label><label class="me-3 small"><input type="radio" class="form-check-input" name="ltype" value="now" checked> Kunci sekarang</label><label class="small"><input type="radio" class="form-check-input" name="ltype" value="sched"> Jadwal</label>
 <label class="form-label small fw-semibold d-block mt-2">Pesan untuk client</label><textarea name="lock_msg" id="lockMsg" class="form-control" rows="3" maxlength="500">Ujian CBT akan segera dimulai. Mohon persiapkan diri dan jangan mengoperasikan komputer lain. Terima kasih.</textarea>
 <label class="small mt-2 d-block"><input type="checkbox" class="form-check-input" checked disabled> Tampilkan tombol OK (wajib ditekan)</label>
 <div class="d-flex gap-2 mt-2"><button class="btn btn-primary flex-fill"><i class="bi bi-send-fill"></i> Kirim Perintah</button><button type="button" class="btn btn-outline-primary flex-fill" onclick="preview()"><i class="bi bi-eye"></i> Lihat Pratinjau</button></div></form>
 <div class="tip mt-3"><b class="text-primary"><i class="bi bi-info-circle-fill"></i> Contoh pesan yang sering digunakan</b><?php foreach($TPL as $t) echo '<div class="mt-1"><a href="#" class="text-reset" onclick="document.getElementById(\'lockMsg\').value=this.textContent;return false">'.h($t).'</a></div>'; ?></div></div></div>
 <div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="card-title"><i class="bi bi-star text-primary"></i> Program Favorit</div><div class="sub">Program dan URL yang diizinkan.</div></div><a href="programs.php" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Tambah</a></div>
 <?php foreach($favs as $g): ?><form method="post" action="control.php" class="d-flex align-items-center gap-2 mt-2" onsubmit="return confirm('Jalankan <?=h($g['name'])?> di semua client online?')"><?=$CF?><input type="hidden" name="back" value="index.php"><input type="hidden" name="mode" value="all">
 <?php if($g['type']==='url'): ?><input type="hidden" name="action" value="open_url"><input type="hidden" name="url" value="<?=h($g['path'])?>"><input type="hidden" name="browser" value="<?=h($g['parameter'])?>"><?php else: ?><input type="hidden" name="action" value="run_app"><input type="hidden" name="program" value="<?=$g['id']?>"><?php endif; ?>
 <span class="fav-ic"><i class="bi <?=$g['type']==='url'?'bi-globe':'bi-window'?>"></i></span><div class="flex-fill text-truncate"><b class="d-block"><?=h($g['name'])?></b><small class="text-muted"><?=h($g['path'])?></small></div><button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-chevron-right"></i> Jalankan</button></form>
 <?php endforeach; if(!$favs) echo '<div class="text-muted small mt-2">Belum ada program.</div>'; ?></div></div>
 <div class="card"><div class="card-body"><div class="d-flex justify-content-between"><div class="card-title"><i class="bi bi-calendar3 text-primary"></i> Jadwal Hari Ini</div><a href="schedules.php" class="small">Lihat semua</a></div>
 <?php foreach($today as $t){ $done=$t['last_run']===date('Y-m-d'); echo '<div class="d-flex align-items-center gap-3 border-bottom py-2"><b>'.h(substr($t['run_time'],0,5)).'</b><span class="flex-fill">'.h($t['name']).'</span><small class="text-muted">'.($t['mode']==='room'?h($t['room']):'Semua client').'</small><span class="badge-s '.($done?'b-ok':'b-wait').'">'.($done?'Selesai':'Menunggu').'</span></div>'; }
 if(!$today) echo '<div class="text-muted small mt-2">Tidak ada jadwal hari ini.</div>'; ?></div></div>
</div></div>
<div class="modal fade" id="pv"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content" style="background:#0b1a3a;color:#fff"><div class="modal-body text-center p-5"><i class="bi bi-lock-fill display-3"></i><p class="fs-5 mt-3" id="pvText"></p><button class="btn btn-light px-5" data-bs-dismiss="modal">OK</button></div></div></div></div>
<script>
const chk=()=>[...document.querySelectorAll('.chk:checked')];
document.getElementById('chkAll').onchange=e=>document.querySelectorAll('.chk').forEach(c=>c.checked=e.target.checked);
function mass(a,l){const f=document.getElementById('massForm'),n=chk().length;f.action.value=a;f.mode.value=n?'pick':'all';
 if(confirm(l+' '+(n?n+' client terpilih':'SEMUA client')+'?'))f.submit();}
function rowAct(a,id,name){const f=document.getElementById('rowForm');if(!confirm('Kirim "'+a+'" ke '+name+'?'))return;f.action.value=a;f.target.value='pc:'+id;f.submit();}
function pickTarget(id){document.getElementById('msgTarget').value='pc:'+id;}
function preview(){document.getElementById('pvText').textContent=document.getElementById('lockMsg').value;new bootstrap.Modal(document.getElementById('pv')).show();}
function lockSubmit(f){if(f.ltype.value==='sched'){location='schedules.php?act=lock';return false;}return confirm('Kunci layar pada target terpilih?');}
// Ganti reload penuh: muat ulang hanya bila tidak ada interaksi (tidak ada centang / form terisi)
setTimeout(function r(){if(!chk().length&&document.activeElement===document.body)location.reload();else setTimeout(r,30000)},30000);
</script>
<?php page_footer();
