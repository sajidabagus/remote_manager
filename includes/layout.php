<?php
function page_header(string $title,string $active=''){
  $nav=['index'=>['Dashboard','bi-house-door-fill','index.php'],'clients'=>['Kelola Client','bi-pc-display','clients.php'],'control'=>['Kontrol Client','bi-sliders','control.php'],'programs'=>['Program & Aplikasi','bi-grid','programs.php'],'schedules'=>['Jadwal Otomatis','bi-calendar-check','schedules.php'],'logs'=>['Riwayat & Log','bi-journal-text','logs.php'],
        'scan'=>['Scan Jaringan','bi-wifi','scan.php'],'settings'=>['Pengaturan','bi-gear','settings.php']];
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?> - RCM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f4f6fb}.side{width:240px;min-height:100vh;background:#0b1f4d;position:fixed;top:0;left:0}
.side a{color:#cfd8f3;text-decoration:none;display:block;padding:.65rem 1rem;border-radius:.5rem;margin:.2rem .75rem}
.side a:hover{background:#17306b}.side a.active{background:#1d6ff2;color:#fff}.main{margin-left:240px;padding:1.25rem 1.5rem}
.stat,.card{border-radius:.9rem;border-color:#e3e8f5}
@media(max-width:768px){.side{position:static;width:100%;min-height:0}.main{margin-left:0}}
</style></head><body>
<div class="side"><div class="p-3 text-white"><div class="fw-bold fs-5"><i class="bi bi-display"></i> Remote Client Manager</div><small class="text-white-50">Laboratorium Komputer</small></div>
<?php foreach($nav as $k=>$n): ?><a class="<?=$k===$active?'active':''?>" href="<?=$n[2]?>"><i class="bi <?=$n[1]?> me-2"></i><?=$n[0]?></a><?php endforeach; ?>
<a href="logout.php" class="mt-4"><i class="bi bi-box-arrow-left me-2"></i>Keluar (<?=h($_SESSION['admin']['username']??'')?>)</a></div>
<div class="main"><h3 class="fw-bold mb-3"><?=h($title)?></h3>
<?php if(!empty($_SESSION['flash'])){ [$m,$t]=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert alert-'.h($t).'">'.h($m).'</div>'; } ?>
<?php }
function page_footer(){ echo '</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>'; }
