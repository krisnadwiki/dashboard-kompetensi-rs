<?php
Auth::requireAuth();

$user = Auth::user();
$rs   = Db::fetchOne("SELECT * FROM rs_profile LIMIT 1");

// Fetch available snapshots
$snapshots = Db::fetchAll("SELECT id, name, periode, description, total_items, created_at FROM snapshots ORDER BY id DESC");
$selectedSnapId = isset($_GET['snapshot']) ? (int)$_GET['snapshot'] : 0;

$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Data Kompetensi']
];
$pageTitle  = 'Data Kompetensi Layanan RS';

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Data Kompetensi Layanan</h1>
    <p class="page-subtitle"><?= e($rs['nama_rs'] ?? 'RSUD Kilisuci') ?> &mdash; Monitoring status kompetensi dan instrumen SIRS</p>
  </div>
  <div class="page-actions">
    <button onclick="triggerDownloadAllCsv()" class="btn btn-primary" title="Unduh seluruh data 24 layanan dalam format CSV">
      <i data-lucide="download"></i> Unduh Semua Data (CSV)
    </button>
    <button onclick="triggerDownloadXlsx()" class="btn btn-secondary" title="Unduh rekap Excel lengkap">
      <i data-lucide="file-spreadsheet"></i> Unduh Rekap Excel
    </button>
    <?php if (Auth::isAdmin()): ?>
    <a href="/master/sinkronisasi" class="btn btn-outline" title="Hanya Administrator">
      <i data-lucide="refresh-cw"></i> Sinkronisasi ke RS Online
    </a>
    <?php endif; ?>
    <a href="/static-dashboard/index.html" target="_blank" class="btn btn-secondary" title="Tampilkan dashboard layar penuh">
      <i data-lucide="maximize-2"></i> Buka Fullscreen
    </a>
  </div>
</div>

<!-- ─── PERIODE / TARIKAN SELECTOR BAR ──────────────────────────────────── -->
<div class="card mb-16" style="background:var(--bg-card); border: 1px solid var(--border);">
  <div class="card-body" style="padding:12px 18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
      <div style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:13.5px;">
        <i data-lucide="calendar" style="color:var(--primary); width:18px; height:18px;"></i>
        <span>Pilih Tanggal Tarikan / Periode:</span>
      </div>
      <select id="snapshotSelector" class="form-control" style="width:auto; min-width:320px; font-weight:500;" onchange="changeSnapshot(this.value)">
        <option value="0" <?= $selectedSnapId === 0 ? 'selected' : '' ?>>🏛️ Rekap Bawaan Standar (17 Okt 2026)</option>
        <?php foreach ($snapshots as $snap): ?>
          <option value="<?= $snap['id'] ?>" <?= $selectedSnapId === (int)$snap['id'] ? 'selected' : '' ?>>
            📅 <?= e($snap['periode'] ?? date('Y-m-d', strtotime($snap['created_at']))) ?> &mdash; <?= e($snap['name']) ?> (<?= number_format($snap['total_items']) ?> baris)
          </option>
        <?php endforeach; ?>
      </select>
      <span id="snapLoading" class="spinner" style="display:none; width:16px; height:16px;"></span>
      
      <?php if (Auth::isAdmin()): ?>
      <form id="delSnapForm" method="POST" action="/master/snapshots/delete" style="display:<?= $selectedSnapId > 0 ? 'inline' : 'none' ?>;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data periode tarikan yang dipilih?');">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delSnapId" value="<?= $selectedSnapId ?>">
        <button type="submit" class="btn btn-sm btn-danger" title="Hapus periode yang sedang dipilih">
          <i data-lucide="trash-2"></i> Hapus Periode Ini
        </button>
      </form>
      <?php endif; ?>
    </div>

    <div style="display:flex; align-items:center; gap:8px;">
      <span class="badge badge-success" id="activePeriodBadge"><i data-lucide="check-circle" style="width:12px;height:12px"></i> Periode Terkini</span>
      <button onclick="document.getElementById('kompetensiFrame').contentWindow.location.reload();" class="btn btn-sm btn-secondary" title="Segarkan tampilan">
        <i data-lucide="rotate-cw"></i> Refresh
      </button>
    </div>
  </div>
</div>

<!-- ─── FRAME MODUL KOMPETENSI ───────────────────────────────────────────── -->
<div class="card" style="padding:0; overflow:hidden; border: 1px solid var(--border);">
  <iframe 
    id="kompetensiFrame" 
    src="/static-dashboard/index.html" 
    style="width:100%; height:calc(100vh - 270px); min-height:800px; border:none; display:block; background:var(--bg);"
    title="Modul Kompetensi Rumah Sakit">
  </iframe>
</div>

<script>
  function triggerDownloadAllCsv() {
    const frame = document.getElementById('kompetensiFrame');
    if (frame && frame.contentWindow && frame.contentWindow.downloadAllCsv) {
      frame.contentWindow.downloadAllCsv();
    }
  }

  function triggerDownloadXlsx() {
    const frame = document.getElementById('kompetensiFrame');
    if (frame && frame.contentWindow && frame.contentWindow.downloadXlsx) {
      frame.contentWindow.downloadXlsx();
    }
  }

  function changeSnapshot(id) {
    const loader = document.getElementById('snapLoading');
    const frame = document.getElementById('kompetensiFrame');
    const delForm = document.getElementById('delSnapForm');
    const delInput = document.getElementById('delSnapId');
    const periodBadge = document.getElementById('activePeriodBadge');

    if (delForm && delInput) {
      delInput.value = id;
      delForm.style.display = (id && id !== '0') ? 'inline' : 'none';
    }

    if (id === '0') {
      if (periodBadge) periodBadge.innerHTML = '<i data-lucide="check-circle" style="width:12px;height:12px"></i> Periode Terkini';
      if (frame && frame.contentWindow && frame.contentWindow.resetAllData) {
        frame.contentWindow.resetAllData();
      }
      return;
    }

    loader.style.display = 'inline-block';
    fetch('/api/snapshot-data?id=' + id)
      .then(res => res.json())
      .then(res => {
        loader.style.display = 'none';
        if (res.status === 'success' && res.items) {
          if (periodBadge) periodBadge.innerHTML = '<i data-lucide="archive" style="width:12px;height:12px"></i> Periode: ' + (res.periode || res.name);
          if (frame && frame.contentWindow && frame.contentWindow.loadCustomSnapshot) {
            frame.contentWindow.loadCustomSnapshot(res.items, res.name);
          }
        }
      })
      .catch(err => {
        loader.style.display = 'none';
        alert('Gagal memuat data snapshot: ' + err.message);
      });
  }

  // Auto synchronize theme and data snapshot to iframe on load and changes
  function syncThemeToFrame() {
    const curTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const frame = document.getElementById('kompetensiFrame');
    if (frame && frame.contentDocument && frame.contentDocument.documentElement) {
      frame.contentDocument.documentElement.setAttribute('data-theme', curTheme);
    }
    // Auto load selected or latest snapshot
    const snapSelect = document.getElementById('snapshotSelector');
    let targetId = <?= json_encode($selectedSnapId) ?>;
    if (!targetId && snapSelect && snapSelect.options.length > 1) {
      // Pick first snapshot from DB
      targetId = snapSelect.options[1].value;
      snapSelect.value = targetId;
    }
    if (targetId && targetId !== '0') {
      setTimeout(() => changeSnapshot(targetId), 400);
    }
  }

  document.getElementById('kompetensiFrame')?.addEventListener('load', syncThemeToFrame);
  new MutationObserver(syncThemeToFrame).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
  });
</script>

<?php
$content = ob_get_clean();
view('layout/app', compact('pageTitle', 'breadcrumb', 'content'));
