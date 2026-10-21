<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
$DN=[1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $a=$_POST['action']??''; $id=(int)($_POST['id']??0);
  if($a==='add'){
    $act=$_POST['sact']??''; $days=array_values(array_intersect(array_map('intval',(array)($_POST['days']??[])),range(1,7)));
    $mode=($_POST['mode']??'')==='room'?'room':'all'; $err=null; $pl=isset(ACT[$act])?build_payload($act,$_POST,$err):[];
    if(trim($_POST['name']??'')==='') $err='Nama jadwal wajib diisi.';
    elseif(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$_POST['time']??'')) $err='Jam tidak valid.';
    elseif(!isset(ACT[$act])) $err='Aksi tidak valid.'; elseif(!$days) $err='Pilih minimal satu hari.';
    if($err) flash($err,'danger');
    else{ db()->prepare('INSERT INTO schedules(name,run_time,days,action,mode,room_id,payload) VALUES(?,?,?,?,?,?,?)')
      ->execute([trim($_POST['name']),$_POST['time'].':00',implode(',',$days),$act,$mode,$mode==='room'?(int)$_POST['room']:null,json_encode($pl?:new stdClass)]); log_activity('Tambah jadwal',trim($_POST['name'])); flash('Jadwal ditambahkan.'); }
  }
  if($a==='toggle') db()->prepare('UPDATE schedules SET active=1-active WHERE id=?')->execute([$id]);
  if($a==='del') db()->prepare('DELETE FROM schedules WHERE id=?')->execute([$id]);
  if($a==='run'){ $s=db()->prepare('SELECT * FROM schedules WHERE id=?'); $s->execute([$id]); if($r=$s->fetch()){ $n=run_schedule($r); flash("Jadwal dijalankan: $n client."); } }
  header('Location: schedules.php'); exit;
}
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
$apps=db()->query("SELECT id,name FROM programs WHERE type='app' AND allowed=1 ORDER BY name")->fetchAll();
$urls=db()->query("SELECT name,path FROM programs WHERE type='url' AND allowed=1")->fetchAll();
$rows=db()->query('SELECT s.*,r.name room FROM schedules s LEFT JOIN rooms r ON r.id=s.room_id ORDER BY s.run_time')->fetchAll();
$btn=fn($act,$id,$cls,$lbl)=>'<form method="post" class="d-inline">'.csrf_field().'<input type="hidden" name="action" value="'.$act.'"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-sm btn-outline-'.$cls.'">'.$lbl.'</button></form>';
page_header('Jadwal Otomatis','schedules','Atur waktu dan aksi yang dijalankan secara otomatis.'); $pre=isset(ACT[$_GET['act']??''])?$_GET['act']:''; ?>
<div class="d-flex justify-content-end mb-2"><a href="#tambah" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Tambah Jadwal</a></div>
<div class="tip mb-3"><b class="text-primary"><i class="bi bi-info-circle-fill"></i> Catatan</b><br>Jadwal dijalankan oleh <code>cli/scheduler.php</code> (tiap menit, lihat README-FASE4). Terlambat sampai 5 menit tetap dijalankan, dan satu jadwal hanya sekali per hari.</div>
<div class="card mb-3"><div class="card-body"><table class="table align-middle"><thead><tr><th>Jam</th><th>Nama</th><th>Hari</th><th>Aksi</th><th>Target</th><th>Terakhir</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): $d=array_map(fn($x)=>$DN[(int)$x],explode(',',$r['days'])); ?><tr class="<?=$r['active']?'':'text-muted'?>"><td class="fw-bold"><?=h(substr($r['run_time'],0,5))?></td><td><?=h($r['name'])?></td><td class="small"><?=h(implode(' ',$d))?></td><td><?=h(ACT[$r['action']]??$r['action'])?></td><td><?=$r['mode']==='room'?h($r['room']):'Semua'?></td>
<td class="small"><?=$r['last_run']?h($r['last_run']).'<br>'.h($r['last_result']):'-'?></td><td><?=$r['active']?'<span class="badge-s b-ok">Aktif</span>':'<span class="badge-s b-fail">Nonaktif</span>'?></td>
<td class="text-nowrap"><?=$btn('run',$r['id'],'primary','Jalankan')?> <form method="post" class="d-inline-block form-switch align-middle"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><input class="form-check-input" type="checkbox" role="switch" title="Aktif/nonaktif" onchange="this.form.submit()"<?=$r['active']?' checked':''?>></form> <?=$btn('del',$r['id'],'danger','<i class="bi bi-trash"></i>')?></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada jadwal.</td></tr>'; ?></tbody></table></div></div>
<form method="post" class="card" id="tambah"><div class="card-body"><?=csrf_field()?><input type="hidden" name="action" value="add"><div class="card-title mb-2">Tambah jadwal</div>
<div class="row g-2 mb-2"><div class="col-md-4"><input name="name" class="form-control" placeholder="Nama jadwal, mis. Nyalakan semua client" required></div><div class="col-md-2"><input type="time" name="time" class="form-control" value="06:30" required></div>
<div class="col-md-6 pt-2"><?php foreach($DN as $k=>$v) echo '<label class="me-2"><input type="checkbox" name="days[]" value="'.$k.'"'.($k<=5?' checked':'').'> '.$v.'</label>'; ?></div></div>
<div class="row g-2 mb-2"><div class="col-md-4"><select name="sact" id="act" class="form-select"><?php foreach(ACT as $k=>$v) echo '<option value="'.$k.'"'.($k===$pre?' selected':'').'>'.h($v).'</option>'; ?></select></div>
<div class="col-md-3"><select name="mode" id="mode" class="form-select"><option value="all">Semua client</option><option value="room">Per ruang</option></select></div>
<div class="col-md-3"><select name="room" id="roomSel" class="form-select"><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.h($r['name']).'</option>'; ?></select></div></div>
<div class="g g-lock"><textarea name="lock_msg" class="form-control" rows="2" placeholder="Pesan layar kunci"></textarea></div>
<div class="g g-message"><textarea name="msg_text" class="form-control" rows="2" placeholder="Isi pesan, mis. Komputer akan dimatikan 10 menit lagi"></textarea><label class="mt-1"><input type="checkbox" name="show_ok" value="1" checked> Tombol OK wajib ditekan</label></div>
<div class="g g-open_url row g-2"><div class="col-md-6"><input name="url" class="form-control" list="urls" placeholder="https://..."><datalist id="urls"><?php foreach($urls as $u) echo '<option value="'.h($u['path']).'">'.h($u['name']).'</option>'; ?></datalist></div>
<div class="col-md-3"><select name="browser" class="form-select"><option value="chrome">Chrome</option><option value="edge">Edge</option><option value="default">Default</option></select></div><div class="col-md-3 pt-2"><label><input type="checkbox" name="kiosk" value="1"> Kiosk</label></div></div>
<div class="g g-run_app"><select name="program" class="form-select"><?php foreach($apps as $a) echo '<option value="'.$a['id'].'">'.h($a['name']).'</option>'; ?></select></div>
<div class="g g-unlock g-restart g-shutdown g-wake text-muted small">Tidak ada pengaturan tambahan.</div>
<button class="btn btn-primary mt-3">Simpan Jadwal</button></div></form>
<script>const act=document.getElementById('act'),mode=document.getElementById('mode');
function sync(){document.querySelectorAll('.g').forEach(e=>e.style.display=e.classList.contains('g-'+act.value)?'':'none');document.getElementById('roomSel').style.display=mode.value==='room'?'':'none';}
act.onchange=mode.onchange=sync;sync();</script>
<?php page_footer();
