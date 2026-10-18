<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#3b6ef0">
  <title>Masuk | Kompetensi RS</title>
  <meta name="description" content="Sistem Informasi Kompetensi Rumah Sakit — Pantau & kelola kompetensi SDM dan alkes RS secara real-time.">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="<?= asset('/assets/img/logo.svg') ?>">
  <link rel="apple-touch-icon" href="<?= asset('/assets/img/logo.svg') ?>">

  <!-- ─── Open Graph / WhatsApp / Facebook ──────────────────────────────── -->
  <meta property="og:title" content="Kompetensi RS — Sistem Informasi Kompetensi Rumah Sakit">
  <meta property="og:description" content="Pantau & kelola kompetensi SDM dan alat kesehatan rumah sakit secara real-time, terintegrasi dengan SIRS Online Kemenkes.">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Kompetensi RS">
  <meta property="og:url" content="<?= appUrl('/') ?>">
  <meta property="og:image" content="<?= appUrl(asset('/assets/img/og-image.png')) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Kompetensi RS — Sistem Informasi Kompetensi Rumah Sakit">
  <meta property="og:locale" content="id_ID">

  <!-- ─── Twitter Card ─────────────────────────────────────────────────── -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Kompetensi RS — Sistem Informasi Kompetensi Rumah Sakit">
  <meta name="twitter:description" content="Pantau & kelola kompetensi SDM dan alat kesehatan rumah sakit secara real-time.">
  <meta name="twitter:image" content="<?= appUrl(asset('/assets/img/og-image.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
  <link rel="stylesheet" href="<?= asset('/assets/css/login.css') ?>">
</head>
<body class="login-body">

<div class="login-page">
  <!-- Animated background -->
  <div class="login-bg">
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>
  </div>

  <!-- Login card -->
  <div class="login-card">
    <!-- Header -->
    <div class="login-header">
      <div class="login-logo">
        <img src="<?= asset('/assets/img/logo.svg') ?>" alt="Logo" width="48" height="48">
      </div>
      <h1 class="login-title">Kompetensi RS</h1>
      <p class="login-subtitle">Sistem Informasi Kompetensi Rumah Sakit</p>
    </div>

    <!-- RS Info -->
    <?php if ($rsName): ?>
    <div class="login-rs-badge">
      <i data-lucide="building-2"></i>
      <span><?= e($rsName) ?></span>
    </div>
    <?php endif; ?>

    <!-- Error / Flash -->
    <?= Flash::render('error') ?>
    <?= Flash::render('success') ?>

    <!-- Form -->
    <form method="POST" action="/login" id="loginForm" novalidate>
      <?= Csrf::field() ?>

      <div class="form-group">
        <label class="form-label" for="username">Username / Email</label>
        <div class="input-group">
          <i data-lucide="user" class="input-icon"></i>
          <input
            type="text"
            id="username"
            name="username"
            class="form-control"
            placeholder="Masukkan username atau email"
            value="<?= e($_POST['username'] ?? '') ?>"
            required
            autocomplete="username"
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <i data-lucide="lock" class="input-icon"></i>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="Masukkan password"
            required
            autocomplete="current-password"
          >
          <button type="button" class="input-group-right toggle-password" aria-label="Tampilkan password">
            <i data-lucide="eye" id="pwEye"></i>
          </button>
        </div>
      </div>

      <div class="login-actions">
        <label class="checkbox-label">
          <input type="checkbox" name="remember" id="remember">
          <span>Ingat saya</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg login-btn" id="submitBtn">
        <i data-lucide="log-in"></i>
        <span>Masuk</span>
      </button>

      <div style="margin-top:14px; text-align:center;">
        <a href="/static-dashboard/index.html" class="btn btn-outline w-100" style="justify-content:center; padding:10px; font-weight:600; font-size:13px; border-style:dashed;">
          <i data-lucide="eye"></i> Akses Publik: Lihat Data Kompetensi (Tanpa Login)
        </a>
      </div>
    </form>

    <!-- Info box -->
    <div class="login-info-box">
      <i data-lucide="info"></i>
      <div>
        <strong>Info Login:</strong>
        <span>Hubungi Administrator jika lupa password.</span>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="login-footer">
    <span>&copy; <?= date('Y') ?> RSUD Kilisuci &mdash; ICT (Information and Communication Technology)</span>
    <a href="https://sirs.kemkes.go.id" target="_blank" rel="noopener">RS Online Kemenkes ↗</a>
  </div>

  <!-- Floating ICT Badge -->
  <div class="ict-badge">
    <img src="<?= asset('/assets/img/badge-ict.svg') ?>" alt="ICT RSUD Kilisuci" title="ICT RSUD Kilisuci">
  </div>

  <!-- Theme toggle -->
  <button class="login-theme-btn" id="themeToggle" aria-label="Ganti tema">
    <i data-lucide="sun" id="themeLight"></i>
    <i data-lucide="moon" id="themeDark" style="display:none"></i>
  </button>
</div>

<script>
  lucide.createIcons();

  // Theme
  const html = document.documentElement;
  let theme = localStorage.getItem('theme') || 'light';
  function applyTheme(t) {
    html.setAttribute('data-theme', t);
    document.getElementById('themeLight').style.display = t === 'dark' ? 'none' : '';
    document.getElementById('themeDark').style.display  = t === 'dark' ? '' : 'none';
    localStorage.setItem('theme', t);
  }
  applyTheme(theme);
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    theme = theme === 'dark' ? 'light' : 'dark';
    applyTheme(theme);
  });

  // Password toggle
  const pwInput = document.getElementById('password');
  const pwEye   = document.getElementById('pwEye');
  document.querySelector('.toggle-password')?.addEventListener('click', function() {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    this.querySelector('i').setAttribute('data-lucide', show ? 'eye-off' : 'eye');
    lucide.createIcons({ nodes: [this.querySelector('i')] });
  });

  // Submit loading state
  document.getElementById('loginForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span><span>Memproses...</span>';
  });
</script>
</body>
</html>
