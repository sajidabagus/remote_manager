<?php
require __DIR__.'/../includes/bootstrap.php';
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $s=db()->prepare('SELECT * FROM admins WHERE username=?'); $s->execute([trim($_POST['username']??'')]); $a=$s->fetch();
  if($a && password_verify($_POST['password']??'',$a['password_hash'])){
    session_regenerate_id(true); $_SESSION['admin']=['id'=>$a['id'],'username'=>$a['username'],'role'=>$a['role']];
    log_activity('Login','admin'); header('Location: index.php'); exit;
  }
  usleep(500000); $err='Username atau password salah.'; log_activity('Login gagal',substr($_POST['username']??'',0,50),'Gagal','','anon');
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - RCM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body style="background:#0b1f4d" class="d-flex align-items-center vh-100"><div class="card mx-auto shadow" style="width:360px"><div class="card-body p-4">
<h4 class="fw-bold">Remote Client Manager</h4><p class="text-muted">Masuk sebagai admin</p>
<?php if($err) echo '<div class="alert alert-danger py-2">'.h($err).'</div>'; ?>
<form method="post"><?=csrf_field()?><input class="form-control mb-2" name="username" placeholder="Username" required autofocus>
<input class="form-control mb-3" type="password" name="password" placeholder="Password" required><button class="btn btn-primary w-100">Masuk</button></form></div></div></body></html>
