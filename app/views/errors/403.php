<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>403 - Akses Ditolak</title>
  <link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; text-align:center; padding:20px; }
    .box { max-width:440px; }
  </style>
</head>
<body>
  <div class="box">
    <h1 style="font-size:64px; font-weight:800; color:var(--danger); line-height:1; margin-bottom:12px;">403</h1>
    <h2 style="font-size:20px; margin-bottom:8px;">Akses Dibatasi</h2>
    <p class="text-muted" style="margin-bottom:24px;">Anda tidak memiliki izin (hak administrator) untuk membuka halaman ini.</p>
    <a href="/dashboard" class="btn btn-primary">Kembali ke Dashboard</a>
  </div>
</body>
</html>
