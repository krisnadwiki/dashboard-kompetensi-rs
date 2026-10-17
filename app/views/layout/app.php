<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#3b6ef0">
  <title><?= e($pageTitle ?? 'Kompetensi RS') ?> | Kompetensi RS</title>
  <meta name="description" content="Sistem Informasi Kompetensi Rumah Sakit — Pantau & kelola kompetensi SDM dan alkes RS secara real-time.">
  <meta name="robots" content="noindex, nofollow">
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= asset('/assets/img/logo.svg') ?>">
  <link rel="apple-touch-icon" href="<?= asset('/assets/img/logo.svg') ?>">

  <!-- ─── Open Graph / WhatsApp / Facebook ──────────────────────────────── -->
  <meta property="og:title" content="<?= e($pageTitle ?? 'Kompetensi RS') ?> — Kompetensi RS">
  <meta property="og:description" content="Pantau & kelola kompetensi SDM dan alat kesehatan rumah sakit secara real-time, terintegrasi dengan SIRS Online Kemenkes.">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Kompetensi RS">
  <meta property="og:url" content="<?= appUrl($_SERVER['REQUEST_URI'] ?? '/') ?>">
  <meta property="og:image" content="<?= appUrl(asset('/assets/img/og-image.png')) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Kompetensi RS — Sistem Informasi Kompetensi Rumah Sakit">
  <meta property="og:locale" content="id_ID">

  <!-- ─── Twitter Card ─────────────────────────────────────────────────── -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle ?? 'Kompetensi RS') ?> — Kompetensi RS">
  <meta name="twitter:description" content="Pantau & kelola kompetensi SDM dan alat kesehatan rumah sakit secara real-time.">
  <meta name="twitter:image" content="<?= appUrl(asset('/assets/img/og-image.png')) ?>">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
  <?php if (isset($extraCss)): foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="<?= asset(e($css)) ?>">
  <?php endforeach; endif; ?>
</head>
<body class="has-sidebar">

<!-- ─── Sidebar ───────────────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <img src="<?= asset('/assets/img/logo.svg') ?>" alt="Logo" width="32" height="32">
      <span class="sidebar-logo-text">Kompetensi RS</span>
    </div>
    <button class="sidebar-toggle-close" id="sidebarClose" aria-label="Tutup sidebar">
      <i data-lucide="x"></i>
    </button>
  </div>

  <?php
    $user = Auth::user();
    $rs   = Db::fetchOne("SELECT nama_rs FROM rs_profile LIMIT 1");
    $rsName = $rs['nama_rs'] ?? 'RSUD';
  ?>
  <div class="sidebar-rs-badge">
    <i data-lucide="building-2"></i>
    <span><?= e($rsName) ?></span>
  </div>

  <nav class="sidebar-nav">
    <p class="nav-section-label">Utama</p>
    <a href="/dashboard" class="nav-item <?= navActive('/dashboard') ?>" title="Dashboard">
      <i data-lucide="layout-dashboard"></i>
      <span>Dashboard</span>
    </a>
    <a href="/dashboard/kompetensi" class="nav-item <?= navActive('/dashboard/kompetensi') ?>" title="Data Kompetensi">
      <i data-lucide="clipboard-list"></i>
      <span>Data Kompetensi</span>
    </a>

    <?php if ($user['role'] === 'admin'): ?>
    <p class="nav-section-label">Master Data</p>
    <a href="/master/profil-rs" class="nav-item <?= navActive('/master/profil-rs') ?>" title="Profil RS">
      <i data-lucide="hospital"></i>
      <span>Profil RS</span>
    </a>
    <a href="/master/users" class="nav-item <?= navActive('/master/users') ?>" title="Pengguna">
      <i data-lucide="users"></i>
      <span>Pengguna</span>
    </a>
    <a href="/master/sinkronisasi" class="nav-item <?= navActive('/master/sinkronisasi') ?>" title="Sinkronisasi RS Online">
      <i data-lucide="refresh-cw"></i>
      <span>Sinkronisasi</span>
    </a>
    <?php endif; ?>
  </nav>
</aside>

<!-- ─── Overlay (mobile) ──────────────────────────────────────────────────── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── Main Wrapper ──────────────────────────────────────────────────────── -->
<div class="main-wrapper">

  <!-- Navbar -->
  <header class="navbar" id="topNavbar">
    <div class="navbar-left">
      <button class="navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i data-lucide="menu"></i>
      </button>
      <div class="navbar-breadcrumb">
        <?php if (isset($breadcrumb)): ?>
          <?php foreach ($breadcrumb as $i => $crumb): ?>
            <?php if ($i > 0): ?><i data-lucide="chevron-right" class="bc-sep"></i><?php endif; ?>
            <?php if (isset($crumb['url'])): ?>
              <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?>
              <span><?= e($crumb['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="navbar-right">
      <!-- Theme toggle -->
      <button class="navbar-btn" id="themeToggle" aria-label="Toggle tema" title="Ganti tema">
        <i data-lucide="sun" id="themeIconLight"></i>
        <i data-lucide="moon" id="themeIconDark" style="display:none"></i>
      </button>

      <!-- Notifikasi -->
      <button class="navbar-btn" aria-label="Notifikasi">
        <i data-lucide="bell"></i>
      </button>

      <!-- User dropdown -->
      <div class="user-dropdown" id="userDropdown">
        <button class="user-dropdown-trigger" id="userDropdownToggle">
          <span class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
          <div class="user-info d-none-sm">
            <span class="user-name"><?= e($user['name']) ?></span>
            <span class="user-role"><?= ucfirst($user['role']) ?></span>
          </div>
          <i data-lucide="chevron-down" class="dropdown-arrow"></i>
        </button>
        <div class="user-dropdown-menu" id="userDropdownMenu">
          <div class="dropdown-header">
            <strong><?= e($user['name']) ?></strong>
            <small><?= e($user['username']) ?></small>
          </div>
          <hr>
          <a href="#" class="dropdown-item">
            <i data-lucide="user"></i> Profil Saya
          </a>
          <a href="/logout" class="dropdown-item dropdown-item-danger" id="logoutBtn">
            <i data-lucide="log-out"></i> Keluar
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <main class="page-content" id="pageContent">
    <?= Flash::render('success') ?>
    <?= Flash::render('error') ?>
    <?= Flash::render('warning') ?>
    <?= Flash::render('info') ?>

    <?php if (isset($content)) echo $content; ?>
  </main>

  <footer class="page-footer">
    <span>&copy; <?= date('Y') ?> Kompetensi RS &mdash; v<?= APP_VER ?></span>
    <span class="footer-sep">|</span>
    <a href="https://sirs.kemkes.go.id" target="_blank" rel="noopener">RS Online Kemenkes</a>
  </footer>
</div>

<!-- ─── Scripts ───────────────────────────────────────────────────────────── -->
<script>
  // Init Lucide icons
  lucide.createIcons();

  // ─── Sidebar toggle ──────────────────────────────────────────────────────
  const sidebar     = document.getElementById('sidebar');
  const overlay     = document.getElementById('sidebarOverlay');
  const toggleBtn   = document.getElementById('sidebarToggle');
  const closeBtn    = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
    document.body.style.overflow = '';
  }

  toggleBtn?.addEventListener('click', () => {
    if (window.innerWidth < 768) {
      sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    } else {
      sidebarCollapsed = !sidebarCollapsed;
      localStorage.setItem('sb_collapsed', sidebarCollapsed ? '1' : '0');
      applySidebarCollapse(true);
    }
  });
  closeBtn?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // Desktop collapsed sidebar init
  let sidebarCollapsed = localStorage.getItem('sb_collapsed') === '1';
  function applySidebarCollapse(animated = false) {
    if (!animated) document.documentElement.style.setProperty('--sidebar-transition', 'none');
    document.body.classList.toggle('sidebar-collapsed', sidebarCollapsed);
    setTimeout(() => document.documentElement.style.removeProperty('--sidebar-transition'), 50);
  }
  applySidebarCollapse(false);

  // ─── User dropdown ───────────────────────────────────────────────────────
  const dropTrigger = document.getElementById('userDropdownToggle');
  const dropMenu    = document.getElementById('userDropdownMenu');
  dropTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    dropMenu.classList.toggle('open');
  });
  document.addEventListener('click', () => dropMenu?.classList.remove('open'));

  // ─── Theme toggle ────────────────────────────────────────────────────────
  const html      = document.documentElement;
  const iconLight = document.getElementById('themeIconLight');
  const iconDark  = document.getElementById('themeIconDark');
  let theme = localStorage.getItem('theme') || 'light';

  function applyTheme(t) {
    html.setAttribute('data-theme', t);
    if (t === 'dark') {
      iconLight.style.display = 'none';
      iconDark.style.display  = '';
    } else {
      iconLight.style.display = '';
      iconDark.style.display  = 'none';
    }
    localStorage.setItem('theme', t);
    // Broadcast to iframe
    try {
      const frame = document.getElementById('kompetensiFrame');
      if (frame && frame.contentDocument && frame.contentDocument.documentElement) {
        frame.contentDocument.documentElement.setAttribute('data-theme', t);
      }
      if (frame && frame.contentWindow) {
        frame.contentWindow.postMessage({ type: 'THEME_CHANGE', theme: t }, '*');
      }
    } catch (e) {}
  }
  applyTheme(theme);
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    theme = theme === 'light' ? 'dark' : 'light';
    applyTheme(theme);
  });

  // ─── Logout confirm ──────────────────────────────────────────────────────
  document.getElementById('logoutBtn')?.addEventListener('click', (e) => {
    if (!confirm('Anda yakin ingin keluar?')) e.preventDefault();
  });
</script>

<?php if (isset($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= asset(e($js)) ?>"></script>
<?php endforeach; endif; ?>

</body>
</html>
