<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check();
  if(($_POST['action']??'')==='purge'){ $n=db()->exec('DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL 90 DAY'); log_activity('Hapus log lama','> 90 hari','Sukses',"$n baris"); flash("$n log lama dihapus."); }
  header('Location: logs.php'); exit; }
$from=$_GET['from']??date('Y-m-d',strtotime('-7 days')); $to=$_GET['to']??date('Y-m-d'); $q=trim($_GET['q']??''); $st=$_GET['st']??''; $pg=max(1,(int)($_GET['p']??1)); $per=25;
$w=['created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY)']; $p=[$from,$to];
if($q!==''){ $w[]='(user LIKE ? OR action LIKE ? OR target LIKE ? OR note LIKE ?)'; array_push($p,...array_fill(0,4,"%$q%")); }
if(in_array($st,['Sukses','Gagal'],true)){ $w[]='status=?'; $p[]=$st; }
$W=implode(' AND ',$w);
if(isset($_GET['csv'])){
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="rcm-log-'.date('Ymd').'.csv"'); echo "\xEF\xBB\xBF";
  $o=fopen('php://output','w'); fputcsv($o,['Waktu','User','Aksi','Target','Status','Keterangan']);
  $s=db()->prepare("SELECT * FROM activity_logs WHERE $W ORDER BY id DESC LIMIT 5000"); $s->execute($p);
  $safe=fn($v)=>preg_match('/^[=+\-@]/',(string)$v)?"'".$v:$v; // cegah formula injection di Excel
  foreach($s as $r) fputcsv($o,[$r['created_at'],$safe($r['user']),$safe($r['action']),$safe($r['target']),$r['status'],$safe($r['note'])]); exit; }
$s=db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE $W"); $s->execute($p); $n=(int)$s->fetchColumn(); $pages=max(1,(int)ceil($n/$per)); $pg=min($pg,$pages);
$s=db()->prepare("SELECT * FROM activity_logs WHERE $W ORDER BY id DESC LIMIT $per OFFSET ".(($pg-1)*$per)); $s->execute($p); $rows=$s->fetchAll();
$qs=fn($x)=>h(http_build_query(array_merge(['from'=>$from,'to'=>$to,'q'=>$q,'st'=>$st],$x)));
page_header('Riwayat & Log','logs'); ?>
<div class="card"><div class="card-body"><form class="row g-2 mb-3"><div class="col-md-2"><input type="date" name="from" value="<?=h($from)?>" class="form-control"></div><div class="col-md-2"><input type="date" name="to" value="<?=h($to)?>" class="form-control"></div>
<div class="col-md-3"><input name="q" value="<?=h($q)?>" class="form-control" placeholder="Cari user, aksi, target, keterangan"></div>
<div class="col-md-2"><select name="st" class="form-select"><option value="">Semua status</option><option<?=$st==='Sukses'?' selected':''?>>Sukses</option><option<?=$st==='Gagal'?' selected':''?>>Gagal</option></select></div>
<div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-success" href="?<?=$qs(['csv'=>1])?>">Ekspor CSV</a></div></form>
<table class="table table-sm align-middle"><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Target</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
<?php foreach($rows as $r) echo '<tr><td class="text-nowrap">'.h(date('d M H:i:s',strtotime($r['created_at']))).'</td><td>'.h($r['user']).'</td><td>'.h($r['action']).'</td><td>'.h($r['target']).'</td><td><span class="badge text-bg-'.($r['status']==='Sukses'?'success':'danger').'">'.h($r['status']).'</span></td><td class="text-muted small">'.h($r['note']).'</td></tr>';
if(!$rows) echo '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada log.</td></tr>'; ?></tbody></table>
<div class="d-flex justify-content-between align-items-center"><small class="text-muted"><?=$n?> entri</small><ul class="pagination pagination-sm mb-0">
<?php foreach(array_unique([1,max(1,$pg-1),$pg,min($pages,$pg+1),$pages]) as $i) echo '<li class="page-item'.($i==$pg?' active':'').'"><a class="page-link" href="?'.$qs(['p'=>$i]).'">'.$i.'</a></li>'; ?></ul>
<form method="post" onsubmit="return confirm('Hapus log lebih dari 90 hari?')"><?=csrf_field()?><input type="hidden" name="action" value="purge"><button class="btn btn-sm btn-outline-danger">Hapus log &gt; 90 hari</button></form></div></div></div>
<?php page_footer();
