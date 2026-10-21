<?php
require __DIR__.'/../includes/bootstrap.php'; require __DIR__.'/../includes/layout.php'; require_login();
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
$logs=db()->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 6')->fetchAll();
$qs=fn($x)=>h(http_build_query(array_merge(['q'=>$q,'room'=>$room,'st'=>$f,'per'=>$per],$x)));
page_header('Dashboard','index'); ?>
<?php if($pending): ?><div class="alert alert-warning py-2"><?=$pending?> perangkat menunggu persetujuan. <a href="clients.php">Lihat</a></div><?php endif; ?>
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center"><b class="me-2">Kontrol Massal</b>
<?php foreach(['wake'=>['Nyalakan Semua','info'],'lock'=>['Kunci Semua','danger'],'message'=>['Kirim Pesan','primary'],'open_url'=>['Buka URL','success'],'restart'=>['Restart','warning'],'shutdown'=>['Matikan','dark']] as $k=>$v) echo '<a class="btn btn-'.$v[1].' btn-sm" href="control.php?act='.$k.'">'.$v[0].'</a>'; ?></div></div>
<div class="row g-3 mb-3">
<?php foreach([['Total Client',$total,'primary','bi-pc-display'],['Online',$online,'success','bi-check-circle'],['Offline',$offline,'danger','bi-x-circle'],['Dalam Proses',$proc,'info','bi-arrow-repeat']] as $c): ?>
<div class="col-6 col-lg-3"><div class="card stat"><div class="card-body d-flex align-items-center gap-3"><i class="bi <?=$c[3]?> fs-1 text-<?=$c[2]?>"></i>
<div><div class="text-muted small"><?=$c[0]?></div><div class="fs-3 fw-bold"><?=$c[1]?></div><?php if($c[0]==='Online'&&$total) echo '<small class="text-success">'.round($online/$total*100).'%</small>'; ?></div></div></div></div>
<?php endforeach; ?></div>
<div class="card mb-3"><div class="card-body"><h5 class="fw-bold">Daftar Komputer Client</h5>
<form class="row g-2 mb-3"><div class="col-md-5"><input class="form-control" name="q" value="<?=h($q)?>" placeholder="Cari nama komputer, IP, atau ruang..."></div>
<div class="col-md-2"><select name="room" class="form-select"><option value="0">Semua Ruang</option><?php foreach($rooms as $r) echo '<option value="'.$r['id'].'"'.($room==$r['id']?' selected':'').'>'.h($r['name']).'</option>'; ?></select></div>
<div class="col-md-2"><select name="st" class="form-select"><option value="">Semua Status</option><option value="online"<?=$f==='online'?' selected':''?>>Online</option><option value="offline"<?=$f==='offline'?' selected':''?>>Offline</option></select></div>
<div class="col-md-2"><select name="per" class="form-select"><?php foreach([10,25,50] as $x) echo "<option".($per==$x?' selected':'').">$x</option>"; ?></select></div>
<div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div></form>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>No</th><th>Nama Client</th><th>IP Address</th><th>MAC</th><th>Ruang</th><th>Status</th><th>Aktivitas Terakhir</th></tr></thead><tbody>
<?php foreach($rows as $i=>$r): ?><tr><td><?=$off+$i+1?></td><td class="fw-semibold"><?=h($r['name'])?></td><td><?=h($r['ip']?:'-')?></td><td class="small text-muted"><?=h($r['mac'])?></td><td><?=h($r['room']?:'Belum ada ruang')?></td>
<td><?php if($r['status']==='approved') echo '<span class="badge text-bg-secondary">Menunggu Agent</span>'; elseif($r['is_on']) echo '<span class="text-success">● Online</span>'; else echo '<span class="text-danger">● Offline</span>'; ?></td>
<td><?=$r['last_seen']?h(date('d M H:i',strtotime($r['last_seen']))):'-'?></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada client. Mulai dari menu <a href="scan.php">Scan Jaringan</a>.</td></tr>'; ?></tbody></table></div>
<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Menampilkan <?=count($rows)?> dari <?=$n?> data</small>
<ul class="pagination pagination-sm mb-0"><?php for($i=1;$i<=$pages;$i++) echo '<li class="page-item'.($i==$pg?' active':'').'"><a class="page-link" href="?'.$qs(['p'=>$i]).'">'.$i.'</a></li>'; ?></ul></div></div></div>
<div class="card"><div class="card-body"><h5 class="fw-bold">Log Aktivitas Terbaru</h5><table class="table table-sm mb-0"><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Target</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
<?php foreach($logs as $l) echo '<tr><td>'.h(date('H:i',strtotime($l['created_at']))).'</td><td>'.h($l['user']).'</td><td>'.h($l['action']).'</td><td>'.h($l['target']).'</td><td><span class="badge text-bg-'.($l['status']==='Sukses'?'success':'danger').'">'.h($l['status']).'</span></td><td class="text-muted">'.h($l['note']).'</td></tr>'; ?></tbody></table></div></div>
<script>setTimeout(()=>location.reload(),30000)</script>
<?php page_footer();
