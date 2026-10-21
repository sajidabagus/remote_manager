<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login(); expire_tasks();
$ACT=ACT;
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $a=$_POST['action']??''; $mode=$_POST['mode']??'all'; $back=function($m,$t){ flash($m,$t); header('Location: control.php'); exit; };
  if(!isset(ACT[$a])) $back('Aksi tidak valid.','danger');
  $err=null; $pl=build_payload($a,$_POST,$err); if($err) $back($err,'danger');
  if($mode==='pick'&&empty($_POST['ids'])) $back('Pilih minimal satu komputer.','warning');
  $targets=pick_targets($mode,(int)($_POST['room']??0),(array)($_POST['ids']??[]),$a!=='wake');
  if(!$targets) $back($a==='wake'?'Tidak ada client offline pada target tersebut.':'Tidak ada client online pada target tersebut.','warning');
  $n=run_action($a,$targets,$pl);
  log_activity(ACT[$a],count($targets).' client',$n?'Sukses':'Gagal',$mode==='room'?'per ruang':($mode==='pick'?'dipilih':'semua'));
  $back($a==='wake'?"Paket Wake-on-LAN dikirim ke $n client. PC butuh sekitar 30-90 detik untuk menyala.":"'".ACT[$a]."' dikirim ke $n client online. Eksekusi dalam ≤ 15 detik.",$n?'success':'danger');
}
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
$cl=db()->query('SELECT c.id,c.name,r.name room,'.is_online_sql('c').' is_on FROM clients c LEFT JOIN rooms r ON r.id=c.room_id WHERE c.status IN(\'active\',\'approved\') ORDER BY c.name')->fetchAll();
$apps=db()->query("SELECT id,name FROM programs WHERE type='app' AND allowed=1 ORDER BY name")->fetchAll();
$urls=db()->query("SELECT name,path,parameter FROM programs WHERE type='url' AND allowed=1 ORDER BY name")->fetchAll();
$tasks=db()->query('SELECT t.*,c.name cname FROM tasks t JOIN clients c ON c.id=t.client_id ORDER BY t.id DESC LIMIT 12')->fetchAll();
$pre=isset($ACT[$_GET['act']??''])?$_GET['act']:'lock';
page_header('Kontrol Client','control'); ?>
<form method="post" class="card mb-3" onsubmit="return confirmAct()"><div class="card-body"><?=csrf_field()?>
<div class="row g-3"><div class="col-lg-5"><h5>1. Target</h5>
<label class="d-block"><input type="radio" name="mode" value="all" checked> Semua client online</label>
<label class="d-block"><input type="radio" name="mode" value="room"> Per ruang</label>
<select name="room" class="form-select form-select-sm mb-2" id="roomSel"><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.h($r['name']).'</option>'; ?></select>
<label class="d-block"><input type="radio" name="mode" value="pick"> Komputer tertentu</label>
<div id="pickBox" class="border rounded p-2" style="max-height:200px;overflow:auto"><?php foreach($cl as $c) echo '<label class="d-block'.($c['is_on']?'':' text-muted').'"><input type="checkbox" name="ids[]" value="'.$c['id'].'"'.' data-on="'.(int)$c['is_on'].'"> '.h($c['name']).' <small>('.h($c['room']?:'-').')</small>'.($c['is_on']?'':' offline').'</label>'; ?></div></div>
<div class="col-lg-7"><h5>2. Aksi</h5><select name="action" id="act" class="form-select mb-3"><?php foreach($ACT as $k=>$v) echo '<option value="'.$k.'"'.($k===$pre?' selected':'').'>'.h($v).'</option>'; ?></select>
<div class="g g-lock"><label class="form-label">Pesan di layar kunci</label><textarea name="lock_msg" class="form-control" rows="3" maxlength="500">Ujian CBT akan segera dimulai. Mohon persiapkan diri dan jangan mengoperasikan komputer lain. Terima kasih.</textarea></div>
<div class="g g-message"><label class="form-label">Isi pesan</label><textarea name="msg_text" class="form-control" rows="3" maxlength="500"></textarea><label class="mt-2"><input type="checkbox" name="show_ok" value="1" checked> Tampilkan tombol OK (wajib ditekan)</label></div>
<div class="g g-open_url"><label class="form-label">URL</label><input name="url" class="form-control" list="urls" placeholder="https://..."><datalist id="urls"><?php foreach($urls as $u) echo '<option value="'.h($u['path']).'">'.h($u['name']).'</option>'; ?></datalist>
<div class="row g-2 mt-1"><div class="col-6"><select name="browser" class="form-select"><option value="chrome">Google Chrome</option><option value="edge">Microsoft Edge</option><option value="default">Browser default</option></select></div><div class="col-6 pt-2"><label><input type="checkbox" name="kiosk" value="1"> Mode kiosk / fullscreen</label></div></div></div>
<div class="g g-run_app"><label class="form-label">Program (hanya yang diizinkan)</label><select name="program" class="form-select"><?php foreach($apps as $a) echo '<option value="'.$a['id'].'">'.h($a['name']).'</option>'; ?></select></div>
<div class="g g-unlock g-restart g-shutdown g-wake text-muted small">Tidak ada pengaturan tambahan. Restart/Matikan dijalankan 10 detik setelah diterima. Wake-on-LAN hanya untuk client offline dan butuh WoL aktif di BIOS/NIC.</div>
<button class="btn btn-primary mt-3"><i class="bi bi-send"></i> Kirim Perintah</button></div></div></div></form>
<div class="card"><div class="card-body"><h5>Tugas terakhir</h5><table class="table table-sm mb-0"><thead><tr><th>Waktu</th><th>Client</th><th>Tugas</th><th>Status</th><th>Hasil</th></tr></thead><tbody>
<?php $bc=['pending'=>'secondary','sent'=>'info','running'=>'info','done'=>'success','failed'=>'danger']; foreach($tasks as $t) echo '<tr><td>'.h(date('H:i:s',strtotime($t['created_at']))).'</td><td>'.h($t['cname']).'</td><td>'.h($ACT[$t['type']]??$t['type']).'</td><td><span class="badge text-bg-'.$bc[$t['status']].'">'.h($t['status']).'</span></td><td class="text-muted">'.h($t['result']).'</td></tr>'; ?></tbody></table></div></div>
<script>
const act=document.getElementById('act');
function sync(){document.querySelectorAll('.g').forEach(e=>e.style.display=e.classList.contains('g-'+act.value)?'':'none');
 document.querySelectorAll('#pickBox input').forEach(i=>{i.disabled=(act.value==='wake')===(i.dataset.on==='1');if(i.disabled)i.checked=false;});
 const m=document.querySelector('[name=mode]:checked').value;document.getElementById('roomSel').disabled=m!=='room';document.getElementById('pickBox').style.opacity=m==='pick'?1:.5;}
function confirmAct(){return !['restart','shutdown','lock'].includes(act.value)||confirm('Yakin menjalankan "'+act.options[act.selectedIndex].text+'" pada target terpilih?')}
act.onchange=sync;document.querySelectorAll('[name=mode]').forEach(r=>r.onchange=sync);sync();
</script>
<?php page_footer();
