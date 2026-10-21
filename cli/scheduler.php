<?php
// Jalankan tiap menit (Task Scheduler / cron): php cli/scheduler.php
define('NO_SESSION',1); require __DIR__.'/../includes/bootstrap.php';
expire_tasks();
$sec=(int)date('G')*3600+(int)date('i')*60+(int)date('s'); $today=date('Y-m-d');
$s=db()->prepare('SELECT * FROM schedules WHERE active=1 AND (last_run IS NULL OR last_run<>?) AND FIND_IN_SET(?,days) AND TIME_TO_SEC(run_time)<=? AND TIME_TO_SEC(run_time)>?');
$s->execute([$today,date('N'),$sec,$sec-300]); // toleransi telat 5 menit
foreach($s->fetchAll() as $row){
  $claim=db()->prepare('UPDATE schedules SET last_run=? WHERE id=? AND (last_run IS NULL OR last_run<>?)'); $claim->execute([$today,$row['id'],$today]);
  if(!$claim->rowCount()) continue; // sudah diambil proses lain
  try{ $n=run_schedule($row); echo date('H:i:s')." {$row['name']}: $n\n"; }
  catch(Throwable $e){ log_activity('Jadwal: '.$row['name'],'','Gagal',substr($e->getMessage(),0,250)); }
}
