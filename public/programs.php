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
page_header('Kelola Program & Aplikasi','programs','Daftar aplikasi dan URL yang dapat dijalankan dari remote.'); $tab=($_GET['tab']??'app')==='url'?'url':'app'; ?>
<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3">
<ul class="nav nav-tabs border-0"><li class="nav-item"><a class="nav-link<?=$tab==='app'?' active':''?>" href="?tab=app">Aplikasi Windows</a></li><li class="nav-item"><a class="nav-link<?=$tab==='url'?' active':''?>" href="?tab=url">URL / Browser</a></li></ul>
<a href="#tambah" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Tambah <?=$tab==='url'?'URL':'Aplikasi'?></a></div>
<div class="tip mb-3">Hanya program berstatus <b>Diizinkan</b> yang bisa dijalankan dari menu Remote Control. Admin tidak bisa mengetik path bebas saat mengirim perintah.</div>
<table class="table align-middle"><thead><tr><th>No</th><th>Nama <?=$tab==='url'?'URL':'Aplikasi'?></th><th><?=$tab==='url'?'URL':'Path'?></th><th><?=$tab==='url'?'Browser':'Parameter'?></th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php $no=0; foreach($rows as $r): if($r['type']!==$tab) continue; $no++; ?><tr><td><?=$no?></td><td class="fw-semibold"><i class="bi <?=$tab==='url'?'bi-globe':'bi-window'?> text-primary me-1"></i><?=h($r['name'])?></td><td class="small"><?=h($r['path'])?></td><td class="small"><?=h($r['parameter']?:'-')?></td>
<td><?=$r['allowed']?'<span class="badge-s b-ok">Diizinkan</span>':'<span class="badge-s b-idle">Nonaktif</span>'?></td><td class="text-nowrap"><?=$btn('toggle',$r['id'],'secondary',$r['allowed']?'Nonaktifkan':'Aktifkan')?> <?=$btn('del',$r['id'],'danger','<i class="bi bi-trash"></i>')?></td></tr>
<?php endforeach; if(!$no) echo '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data.</td></tr>'; ?></tbody></table></div></div>
<div class="card" id="tambah"><div class="card-body"><div class="card-title mb-2">Tambah program</div><form method="post" class="row g-2"><?=csrf_field()?><input type="hidden" name="action" value="add">
<div class="col-md-2"><input name="name" class="form-control" placeholder="Nama" required></div>
<div class="col-md-2"><select name="type" class="form-select"><option value="app"<?=$tab==='app'?' selected':''?>>Aplikasi Windows</option><option value="url"<?=$tab==='url'?' selected':''?>>URL / Browser</option></select></div>
<div class="col-md-4"><input name="path" class="form-control" placeholder="C:\Program Files\...\app.exe atau https://..." required></div>
<div class="col-md-2"><input name="parameter" class="form-control" placeholder="Argumen / chrome|edge"></div>
<div class="col-md-1 pt-2"><label><input class="form-check-input" type="checkbox" name="allowed" checked> Izinkan</label></div><div class="col-md-1"><button class="btn btn-primary w-100">Tambah</button></div></form></div></div>
<?php page_footer();
