<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $id=(int)($_POST['id']??0); $a=$_POST['action']??'';
  $c=db()->prepare('SELECT * FROM clients WHERE id=?'); $c->execute([$id]); $c=$c->fetch();
  if($c){
    if($a==='approve'||$a==='setroom'){ $room=(int)($_POST['room']??0)?:null;
      db()->prepare('UPDATE clients SET room_id=?'.($a==='approve'?",status='approved'":'').' WHERE id=?')->execute([$room,$id]); log_activity($a==='approve'?'Setujui client':'Ubah ruang',$c['name']); flash('Tersimpan.'); }
    if($a==='reset'){ db()->prepare("UPDATE clients SET token_hash=NULL,status='approved' WHERE id=?")->execute([$id]); log_activity('Reset token',$c['name']); flash('Token direset. Agent akan mendaftar ulang otomatis.'); }
    if($a==='testping'&&$c['status']==='active'){ db()->prepare("INSERT INTO tasks(client_id,type,created_by) VALUES(?,'ping',?)")->execute([$id,$_SESSION['admin']['username']]); flash('Tugas ping dikirim ke '.$c['name'].'. Hasilnya muncul di Log Aktivitas dashboard.'); }
    if($a==='quick'){ $t=$_POST['type']??'';
      if($t==='wake'){ wol_send($c['mac']); log_activity('Wake-on-LAN',$c['name']); flash('Paket WoL dikirim ke '.$c['name'].'.'); }
      elseif(in_array($t,['lock','unlock','restart','shutdown'],true)&&$c['status']==='active'){ create_task($id,$t,$t==='lock'?['message'=>'Komputer dikunci oleh admin.']:[]); log_activity(ACT[$t],$c['name']); flash(ACT[$t].' dikirim ke '.$c['name'].'.'); } }
    if($a==='delete'){ db()->prepare('DELETE FROM clients WHERE id=?')->execute([$id]); log_activity('Hapus client',$c['name']); flash('Client dihapus.','warning'); }
  }
  header('Location: clients.php'); exit;
}
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
$opt=function($sel) use($rooms){ $o='<option value="0">- Belum ada ruang -</option>'; foreach($rooms as $r)$o.='<option value="'.$r['id'].'"'.($r['id']==$sel?' selected':'').'>'.h($r['name']).'</option>'; return $o; };
$pend=db()->query("SELECT * FROM clients WHERE status='pending' ORDER BY id")->fetchAll();
$all=db()->query("SELECT c.*,".is_online_sql('c')." is_on FROM clients c WHERE status<>'pending' ORDER BY c.name")->fetchAll();
page_header('Kelola Client','clients','Persetujuan perangkat, ruang, dan token agent.');
if($pend): ?><div class="card border-warning mb-3"><div class="card-body"><div class="card-title mb-2">Menunggu persetujuan (<?=count($pend)?>)</div><p class="text-muted small">Agent terpasang di PC ini tetapi belum pernah di-scan/disetujui.</p><table class="table table-sm align-middle">
<?php foreach($pend as $c): ?><tr><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="approve">
<td class="fw-semibold"><?=h($c['name'])?></td><td><?=h($c['ip'])?></td><td class="small"><?=h($c['mac'])?></td><td><select name="room" class="form-select form-select-sm"><?=$opt($c['room_id'])?></select></td>
<td><button class="btn btn-sm btn-success">Setujui</button></td></form><td><form method="post" onsubmit="return confirm('Tolak dan hapus?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Tolak</button></form></td></tr><?php endforeach; ?></table></div></div><?php endif; ?>
<div class="card"><div class="card-body"><table class="table align-middle"><thead><tr><th>Nama</th><th>IP</th><th>MAC</th><th>Ruang</th><th>Status</th><th>Versi</th><th></th></tr></thead><tbody>
<?php foreach($all as $c): ?><tr><td class="fw-semibold"><?=h($c['name'])?></td><td><?=h($c['ip'])?></td><td class="small"><?=h($c['mac'])?></td>
<td><form method="post" class="d-flex gap-1"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="setroom"><select name="room" class="form-select form-select-sm" onchange="this.form.submit()"><?=$opt($c['room_id'])?></select></form></td>
<td><?=$c['status']==='approved'?'<span class="badge-s b-idle">Menunggu Agent</span>':($c['is_on']?'<span class="st-on">Online</span>':'<span class="st-off">Offline</span>')?></td><td class="small"><?=h($c['agent_version'])?></td>
<td class="text-nowrap"><form method="post" class="d-inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="quick"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Aksi</button><ul class="dropdown-menu"><?php foreach(($c['is_on']?['lock'=>'Kunci','unlock'=>'Buka kunci','restart'=>'Restart','shutdown'=>'Matikan']:['wake'=>'Nyalakan (WoL)']) as $k=>$l) echo '<li><button class="dropdown-item" name="type" value="'.$k.'">'.$l.'</button></li>'; ?></ul></div></form> <?php if($c['status']==='active'): ?><form method="post" class="d-inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="testping"><button class="btn btn-sm btn-outline-primary">Tes ping</button></form> <form method="post" class="d-inline" onsubmit="return confirm('Reset token? Agent harus mendaftar ulang.')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-outline-secondary">Reset token</button></form> <?php endif; ?>
<form method="post" class="d-inline" onsubmit="return confirm('Hapus client ini?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; if(!$all) echo '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada client.</td></tr>'; ?></tbody></table></div></div>
<?php page_footer();
