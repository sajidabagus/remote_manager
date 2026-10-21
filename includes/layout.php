<?php
// Layout bersama: sidebar navy + header (tanggal, jam berjalan, notifikasi, profil). Gaya di public/assets/rcm.css
function page_header(string $title,string $active='',string $sub=''){
  $nav=['index'=>['Dashboard','bi-house-door-fill','index.php'],'clients'=>['Kelola Client','bi-pc-display','clients.php'],
        'programs'=>['Program & Aplikasi','bi-grid','programs.php'],'schedules'=>['Jadwal Otomatis','bi-calendar-check','schedules.php'],
        'control'=>['Remote Control','bi-display','control.php'],'lock'=>['Kunci Perangkat','bi-lock','index.php#kunci'],
        'msg'=>['Pesan ke Client','bi-envelope','index.php#pesan'],'logs'=>['Riwayat & Log','bi-journal-text','logs.php'],
        'scan'=>['Scan Jaringan','bi-wifi','scan.php'],'settings'=>['Pengaturan','bi-gear','settings.php']];
  $pending=0; try{ $pending=(int)db()->query("SELECT COUNT(*) FROM clients WHERE status='pending'")->fetchColumn(); }catch(Throwable $e){}
  $srv=$_SERVER['SERVER_ADDR']??'127.0.0.1'; $user=$_SESSION['admin']['username']??'admin'; $role=$_SESSION['admin']['role']??'admin';
  $hari=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w')];
  $bulan=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)date('n')];
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> - RCM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/rcm.css" rel="stylesheet"></head><body>
<aside class="side"><div class="brand"><div class="logo"><i class="bi bi-display"></i></div><div><b>Remote Client Manager</b><small>Laboratorium Komputer</small></div></div>
<nav><?php foreach($nav as $k=>$n): ?><a class="<?=$k===$active?'active':''?>" href="<?=$n[2]?>"><i class="bi <?=$n[1]?>"></i><?=$n[0]?><i class="bi bi-chevron-right chev"></i></a><?php endforeach; ?></nav>
<div class="srv"><span class="dot"></span><b>Server Online</b><div class="ms-3"><?=h($srv)?><br><span class="text-white-50">v<?=h(defined('APP_VERSION')?APP_VERSION:'1.0.0')?></span></div></div></aside>
<div class="main"><header class="topbar"><i class="bi bi-list fs-4 d-lg-none"></i><div class="ms-auto"><?=$hari.', '.date('j').' '.$bulan.' '.date('Y')?></div>
<span class="clock" id="clock"><?=date('H:i:s')?></span>
<a class="bell" href="clients.php" title="<?=$pending?> perangkat menunggu persetujuan"><i class="bi bi-bell"></i><?php if($pending) echo '<span class="n">'.$pending.'</span>'; ?></a>
<div class="dropdown"><a class="prof dropdown-toggle" href="#" data-bs-toggle="dropdown"><span class="av"><i class="bi bi-person-fill"></i></span><span><b><?=h($user)?></b><small><?=h(ucfirst($role))?></small></span></a>
<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Pengaturan</a></li><li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>Keluar</a></li></ul></div></header>
<div class="content"><h1 class="page-title"><?=h($title)?></h1><div class="page-sub"><?=h($sub)?></div>
<?php if(!empty($_SESSION['flash'])){ [$m,$t]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-'.h($t).'">'.h($m).'</div>'; } ?>
<?php }
function page_footer(){ ?></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>(function(){const c=document.getElementById('clock');const p=n=>String(n).padStart(2,'0');setInterval(()=>{const d=new Date();c.textContent=p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds())},1000)})();</script>
</body></html><?php }
