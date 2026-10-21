<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $a=$_POST['action']??''; $d=db();
  if($a==='room_add'&&trim($_POST['name']??'')!==''){ $d->prepare('INSERT IGNORE INTO rooms(name) VALUES(?)')->execute([trim($_POST['name'])]); }
  if($a==='room_del'){ $d->prepare('DELETE FROM rooms WHERE id=?')->execute([(int)$_POST['id']]); }
  if($a==='rule_add'){ $p=trim($_POST['pattern']??'');
    if($p===''||@preg_match('~'.str_replace('~','\~',$p).'~i','x')===false) flash('Pola regex tidak valid.','danger');
    else $d->prepare('INSERT INTO room_rules(pattern,room_id,priority) VALUES(?,?,?)')->execute([$p,(int)$_POST['room'],(int)($_POST['priority']??10)]); }
  if($a==='rule_del'){ $d->prepare('DELETE FROM room_rules WHERE id=?')->execute([(int)$_POST['id']]); }
  if($a==='apply'){ $n=0; foreach($d->query('SELECT id,name FROM clients WHERE room_id IS NULL') as $c){ if($r=room_for_name($c['name'])){ $d->prepare('UPDATE clients SET room_id=? WHERE id=?')->execute([$r,$c['id']]); $n++; } } flash("$n client diberi ruang otomatis."); }
  header('Location: settings.php'); exit;
}
$rooms=db()->query('SELECT * FROM rooms ORDER BY name')->fetchAll();
$rules=db()->query('SELECT r.*,m.name room FROM room_rules r JOIN rooms m ON m.id=r.room_id ORDER BY priority,id')->fetchAll();
$del=fn($act,$id,$lbl='<i class="bi bi-trash"></i>')=>'<form method="post" class="d-inline">'.csrf_field().'<input type="hidden" name="action" value="'.$act.'"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-sm btn-outline-danger">'.$lbl.'</button></form>';
page_header('Pengaturan','settings','Ruang laboratorium dan aturan penempatan otomatis.'); ?>
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-body"><div class="card-title mb-2">Ruang / Lab</div>
<?php foreach($rooms as $r) echo '<div class="d-flex justify-content-between mb-1"><span>'.h($r['name']).'</span>'.$del('room_del',$r['id']).'</div>'; ?>
<form method="post" class="d-flex gap-2 mt-3"><?=csrf_field()?><input type="hidden" name="action" value="room_add"><input name="name" class="form-control" placeholder="Lab 5"><button class="btn btn-primary">Tambah</button></form></div></div></div>
<div class="col-lg-8"><div class="card"><div class="card-body"><div class="card-title mb-2">Aturan ruang dari nama perangkat</div><p class="text-muted small">Regex, tidak peka huruf besar/kecil. Prioritas lebih kecil dicek lebih dulu. Contoh: <code>^CBT-0[1-6]$</code> untuk CBT-01 s/d CBT-06.</p>
<table class="table table-sm align-middle"><thead><tr><th>Pola</th><th>Ruang</th><th>Prioritas</th><th></th></tr></thead><tbody>
<?php foreach($rules as $r) echo '<tr><td><code>'.h($r['pattern']).'</code></td><td>'.h($r['room']).'</td><td>'.$r['priority'].'</td><td>'.$del('rule_del',$r['id']).'</td></tr>'; ?></tbody></table>
<form method="post" class="row g-2"><?=csrf_field()?><input type="hidden" name="action" value="rule_add"><div class="col-md-5"><input name="pattern" class="form-control" placeholder="^LAB1[-_]" required></div>
<div class="col-md-3"><select name="room" class="form-select"><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'">'.h($r['name']).'</option>'; ?></select></div>
<div class="col-md-2"><input name="priority" type="number" value="10" class="form-control"></div><div class="col-md-2"><button class="btn btn-primary w-100">Tambah</button></div></form>
<form method="post" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="apply"><button class="btn btn-outline-primary">Terapkan ke client tanpa ruang</button></form></div></div></div></div>
<?php page_footer();
