<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check(); $a=$_POST['action']??'';
  if($a==='add'){ $n=trim($_POST['name']??''); $t=($_POST['type']??'')==='url'?'url':'app'; $p=trim($_POST['path']??''); $par=trim($_POST['parameter']??'');
    if($n===''||$p==='') flash('Nama dan path/URL wajib diisi.','danger');
    elseif($t==='url'&&!preg_match('~^https?://~i',$p)) flash('URL harus diawali http:// atau https://','danger');
    else{ if($t==='url'&&!in_array($par,['chrome','edge'])) $par='default';
      db()->prepare('INSERT INTO programs(name,type,path,parameter,allowed) VALUES(?,?,?,?,?)')->execute([$n,$t,$p,$par?:null,empty($_POST['allowed'])?0:1]); log_activity('Tambah program',$n); flash('Program ditambahkan.'); } }
  if($a==='toggle') db()->prepare('UPDATE programs SET allowed=1-allowed WHERE id=?')->execute([(int)$_POST['id']]);
  if($a==='del') db()->prepare('DELETE FROM programs WHERE id=?')->execute([(int)$_POST['id']]);
  header('Location: programs.php'); exit;
}
$rows=db()->query('SELECT * FROM programs ORDER BY type,name')->fetchAll();
$btn=fn($act,$id,$cls,$lbl)=>'<form method="post" class="d-inline">'.csrf_field().'<input type="hidden" name="action" value="'.$act.'"><input type="hidden" name="id" value="'.$id.'"><button class="btn btn-sm btn-outline-'.$cls.'">'.$lbl.'</button></form>';
page_header('Program & Aplikasi','programs'); ?>
<div class="alert alert-info py-2">Hanya program berstatus <b>Diizinkan</b> yang bisa dijalankan dari menu Kontrol. Admin tidak bisa mengetik path bebas saat mengirim perintah.</div>
<div class="card mb-3"><div class="card-body"><table class="table align-middle"><thead><tr><th>Nama</th><th>Jenis</th><th>Path / URL</th><th>Parameter / Browser</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td class="fw-semibold"><?=h($r['name'])?></td><td><?=$r['type']==='url'?'URL':'Aplikasi'?></td><td class="small"><?=h($r['path'])?></td><td class="small"><?=h($r['parameter'])?></td>
<td><?=$r['allowed']?'<span class="badge text-bg-success">Diizinkan</span>':'<span class="badge text-bg-secondary">Nonaktif</span>'?></td><td class="text-nowrap"><?=$btn('toggle',$r['id'],'secondary',$r['allowed']?'Nonaktifkan':'Aktifkan')?> <?=$btn('del',$r['id'],'danger','<i class="bi bi-trash"></i>')?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-body"><h5>Tambah program</h5><form method="post" class="row g-2"><?=csrf_field()?><input type="hidden" name="action" value="add">
<div class="col-md-2"><input name="name" class="form-control" placeholder="Nama" required></div>
<div class="col-md-2"><select name="type" class="form-select"><option value="app">Aplikasi Windows</option><option value="url">URL / Browser</option></select></div>
<div class="col-md-4"><input name="path" class="form-control" placeholder="C:\Program Files\...\app.exe atau https://..." required></div>
<div class="col-md-2"><input name="parameter" class="form-control" placeholder="Argumen / chrome|edge"></div>
<div class="col-md-1 pt-2"><label><input type="checkbox" name="allowed" checked> Izinkan</label></div><div class="col-md-1"><button class="btn btn-primary w-100">Tambah</button></div></form></div></div>
<?php page_footer();
