<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login(); expire_tasks();
$ACT=ACT;
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $a=$_POST['action']??''; $mode=$_POST['mode']??'all';
  $to=in_array($_POST['back']??'',['index.php','control.php'],true)?$_POST['back']:'control.php';
  $back=function($m,$t)use($to){ flash($m,$t); header('Location: '.$to); exit; };
  // Target ringkas dari dashboard: all | room:ID | pc:ID
  if(isset($_POST['target'])){ $tg=explode(':',(string)$_POST['target']);
    if($tg[0]==='room'){ $mode='room'; $_POST['room']=(int)($tg[1]??0); } elseif($tg[0]==='pc'){ $mode='pick'; $_POST['ids']=[(int)($tg[1]??0)]; } else $mode='all'; }
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
page_header('Remote Application Launcher','control','Buka aplikasi, browser, atau URL dan kirim perintah ke komputer client.'); ?>
<form method="post" onsubmit="return confirmAct()"><?=csrf_field()?>
<div class="row g-3 mb-3"><div class="col-lg-5"><div class="card h-100"><div class="card-body"><div class="card-title mb-2">Target</div>
<label class="d-block mb-1"><input class="form-check-input" type="radio" name="mode" value="all" checked> Semua komputer online</label>
<label class="d-block mb-1"><input class="form-check-input" type="radio" name="mode" value="room"> Satu ruang laboratorium</label>
<select name="room" class="form-select form-select-sm mb-2 ms-4 w-auto" id="roomSel"><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.h($r['name']).'</option>'; ?></select>
<label class="d-block mb-1"><input class="form-check-input" type="radio" name="mode" value="pick"> Komputer tertentu</label>
<div id="pickBox" class="border rounded p-2 ms-4" style="max-height:220px;overflow:auto"><?php foreach($cl as $c) echo '<label class="d-block'.($c['is_on']?'':' text-muted').'"><input class="form-check-input" type="checkbox" name="ids[]" value="'.$c['id'].'"'.' data-on="'.(int)$c['is_on'].'"> '.h($c['name']).' <small>('.h($c['room']?:'-').')</small>'.($c['is_on']?'':' <span class="st-off small">offline</span>').'</label>'; ?></div></div></div></div>
<div class="col-lg-7"><div class="card h-100"><div class="card-body"><div class="card-title mb-2">Jenis Perintah</div><select name="action" id="act" class="form-select mb-3"><?php foreach($ACT as $k=>$v) echo '<option value="'.$k.'"'.($k===$pre?' selected':'').'>'.h($v).'</option>'; ?></select>
<div class="g g-lock"><label class="form-label">Pesan di layar kunci</label><textarea name="lock_msg" class="form-control" rows="3" maxlength="500">Ujian CBT akan segera dimulai. Mohon persiapkan diri dan jangan mengoperasikan komputer lain. Terima kasih.</textarea></div>
<div class="g g-message"><label class="form-label">Isi pesan</label><textarea name="msg_text" class="form-control" rows="3" maxlength="500"></textarea><label class="mt-2"><input class="form-check-input" type="checkbox" name="show_ok" value="1" checked> Tampilkan tombol OK (wajib ditekan)</label></div>
<div class="g g-open_url"><label class="form-label">Browser</label><select name="browser" id="browser" class="form-select mb-2"><option value="chrome">Google Chrome</option><option value="edge">Microsoft Edge</option><option value="default">Browser default</option></select>
<label class="form-label">URL Tujuan</label><input name="url" id="url" class="form-control" list="urls" placeholder="https://..."><datalist id="urls"><?php foreach($urls as $u) echo '<option value="'.h($u['path']).'">'.h($u['name']).'</option>'; ?></datalist>
<label class="mt-2 d-block"><input class="form-check-input" type="checkbox" name="kiosk" id="kiosk" value="1"> Mode kiosk / fullscreen</label><small class="text-muted ms-4">Browser dibuka dalam mode layar penuh jika didukung (Chrome/Edge).</small></div>
<div class="g g-run_app"><label class="form-label">Program (hanya yang diizinkan)</label><select name="program" id="prog" class="form-select"><?php foreach($apps as $a) echo '<option value="'.$a['id'].'">'.h($a['name']).'</option>'; ?></select></div>
<div class="g g-unlock g-restart g-shutdown g-wake text-muted small">Tidak ada pengaturan tambahan. Restart/Matikan dijalankan 10 detik setelah diterima. Wake-on-LAN hanya untuk client offline dan butuh WoL aktif di BIOS/NIC.</div></div></div></div></div>
<div class="row g-3 mb-3 align-items-center"><div class="col-lg-8"><div class="summary"><b class="text-success"><i class="bi bi-terminal"></i> Ringkasan Perintah</b><dl class="row mb-0 mt-2 small" id="sum"></dl></div></div>
<div class="col-lg-4"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-send-fill"></i> Kirim Perintah</button></div></div></form>
<div class="card"><div class="card-body"><div class="card-title mb-2">Tugas terakhir</div><table class="table table-sm mb-0"><thead><tr><th>Waktu</th><th>Client</th><th>Tugas</th><th>Status</th><th>Hasil</th></tr></thead><tbody>
<?php $bc=['pending'=>'b-idle','sent'=>'b-info','running'=>'b-info','done'=>'b-ok','failed'=>'b-fail']; foreach($tasks as $t) echo '<tr><td>'.h(date('H:i:s',strtotime($t['created_at']))).'</td><td>'.h($t['cname']).'</td><td>'.h($ACT[$t['type']]??$t['type']).'</td><td><span class="badge-s '.$bc[$t['status']].'">'.h($t['status']).'</span></td><td class="text-muted">'.h($t['result']).'</td></tr>'; ?></tbody></table></div></div>
<script>
const act=document.getElementById('act');
function sync(){document.querySelectorAll('.g').forEach(e=>e.style.display=e.classList.contains('g-'+act.value)?'':'none');
 document.querySelectorAll('#pickBox input').forEach(i=>{i.disabled=(act.value==='wake')===(i.dataset.on==='1');if(i.disabled)i.checked=false;});
 const m=document.querySelector('[name=mode]:checked').value;document.getElementById('roomSel').disabled=m!=='room';document.getElementById('pickBox').style.opacity=m==='pick'?1:.5;}
function confirmAct(){return !['restart','shutdown','lock'].includes(act.value)||confirm('Yakin menjalankan "'+act.options[act.selectedIndex].text+'" pada target terpilih?')}
function summary(){const m=document.querySelector('[name=mode]:checked').value,sel=e=>e.options[e.selectedIndex]?.text||'-';
 const t=m==='all'?'Semua komputer online':m==='room'?'Ruang '+sel(document.getElementById('roomSel')):document.querySelectorAll('#pickBox input:checked').length+' komputer dipilih';
 const rows=[['Target',t],['Perintah',sel(act)]];
 if(act.value==='open_url'){rows.push(['Browser',sel(document.getElementById('browser'))],['URL',document.getElementById('url').value||'-'],['Mode',document.getElementById('kiosk').checked?'Fullscreen (kiosk)':'Jendela biasa']);}
 if(act.value==='run_app')rows.push(['Program',sel(document.getElementById('prog'))]);
 const d=document.getElementById('sum');d.innerHTML='';rows.forEach(([k,v])=>{const a=document.createElement('dt'),b=document.createElement('dd');a.className='col-3';b.className='col-9 mb-1';a.textContent=k;b.textContent=': '+v;d.append(a,b);});}
document.querySelector('form').addEventListener('input',summary);document.querySelector('form').addEventListener('change',summary);
act.onchange=sync;document.querySelectorAll('[name=mode]').forEach(r=>r.onchange=sync);sync();summary();
</script>
<?php page_footer();
