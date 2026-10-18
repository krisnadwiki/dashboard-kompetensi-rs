<?php
Auth::requireAuth();

$user = Auth::user();
$rs   = Db::fetchOne("SELECT * FROM rs_profile LIMIT 1");



// Summary stats
$totalUsers   = Db::fetchOne("SELECT COUNT(*) AS cnt FROM users")['cnt'] ?? 0;
$activeUsers  = Db::fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE is_active = 1")['cnt'] ?? 0;
$totalSyncLog = Db::fetchOne("SELECT COUNT(*) AS cnt FROM sync_log")['cnt'] ?? 0;
$lastSync     = Db::fetchOne("SELECT created_at, notes FROM sync_log ORDER BY id DESC LIMIT 1");
$totalInstrumen = Db::fetchOne("SELECT total_items FROM snapshots ORDER BY id DESC LIMIT 1")['total_items'] ?? 0;

$breadcrumb = [['label' => 'Dashboard']];
$pageTitle  = 'Dashboard';

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard Utama</h1>
    <p class="page-subtitle">Selamat datang, <?= e($user['name']) ?>! 👋 &mdash; Sistem Informasi Kompetensi RS</p>
  </div>
  <div class="page-actions">
    <?php if ($user['role'] === 'admin'): ?>
    <a href="/master/sinkronisasi" class="btn btn-primary">
      <i data-lucide="refresh-cw"></i> Sinkronisasi RS Online
    </a>
    <?php endif; ?>
  </div>
</div>


<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i data-lucide="building-2"></i></div>
    <div class="stat-info">
      <div class="stat-value" title="<?= e($rs['nama_rs'] ?? '-') ?>"><?= e($rs['nama_rs'] ?? '-') ?></div>
      <div class="stat-label">Nama Faskes</div>
      <?php if (!empty($rs['kelas_rs'])): ?>
      <div class="stat-sublabel">Kelas <?= e($rs['kelas_rs']) ?> — <?= e($rs['kepemilikan'] ?? '') ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon green"><i data-lucide="database"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($totalInstrumen) ?></div>
      <div class="stat-label">Total Instrumen Terkini</div>
      <div class="stat-sublabel">Baris data dari tarikan SIRS</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon cyan"><i data-lucide="refresh-cw"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $totalSyncLog ?></div>
      <div class="stat-label">Riwayat Log Tarikan</div>
      <div class="stat-sublabel">Total sinkronisasi tercatat</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i data-lucide="clock"></i></div>
    <div class="stat-info">
      <?php if ($lastSync): ?>
      <div class="stat-value"><?= date('d M Y', strtotime($lastSync['created_at'])) ?></div>
      <div class="stat-label">Tarikan Terakhir</div>
      <div class="stat-sublabel">Pukul <?= date('H:i', strtotime($lastSync['created_at'])) ?> WIB</div>
      <?php else: ?>
      <div class="stat-value">—</div>
      <div class="stat-label">Tarikan Terakhir</div>
      <div class="stat-sublabel">Belum ada data</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Info RS & Quick Actions -->
<div class="grid-2" style="margin-bottom:24px;">

  <!-- Profil RS Card -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i data-lucide="hospital"></i> Profil Rumah Sakit</h2>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="/master/profil-rs" class="btn btn-sm btn-secondary"><i data-lucide="edit-3"></i> Edit</a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($rs): ?>
      <table class="table" style="font-size:13px;">
        <tr><td class="text-muted" style="width:40%;padding:6px 0">Nama RS</td><td><strong><?= e($rs['nama_rs']) ?></strong></td></tr>
        <tr><td class="text-muted" style="padding:6px 0">Kelas</td><td><span class="badge badge-primary">Kelas <?= e($rs['kelas_rs'] ?? '-') ?></span></td></tr>
        <tr><td class="text-muted" style="padding:6px 0">Kepemilikan</td><td><?= e($rs['kepemilikan'] ?? '-') ?></td></tr>
        <tr><td class="text-muted" style="padding:6px 0">Direktur</td><td><?= e($rs['direktur'] ?? '-') ?></td></tr>
        <tr><td class="text-muted" style="padding:6px 0">Kota</td><td><?= e($rs['kota_kabupaten'] ?? '-') ?></td></tr>
        <tr><td class="text-muted" style="padding:6px 0">Telepon</td><td><?= e($rs['no_telepon'] ?? '-') ?></td></tr>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <i data-lucide="info"></i>
        <p>Data profil RS belum diisi.</p>
        <a href="/master/profil-rs" class="btn btn-sm btn-primary mt-8">Isi Sekarang</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><i data-lucide="zap"></i> Akses Cepat</h2>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
      <a href="/dashboard/kompetensi" class="btn btn-secondary w-100" style="justify-content:flex-start;gap:12px;padding:12px 16px;">
        <i data-lucide="clipboard-list" style="width:20px;height:20px;color:var(--primary)"></i>
        <div style="text-align:left">
          <div style="font-weight:600">Data Kompetensi 24 Layanan</div>
          <div class="text-muted" style="font-size:12px">Analisis gap, pemenuhan, dan unduh rekap Excel/CSV</div>
        </div>
      </a>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="/master/sinkronisasi" class="btn btn-secondary w-100" style="justify-content:flex-start;gap:12px;padding:12px 16px;">
        <i data-lucide="refresh-cw" style="width:20px;height:20px;color:var(--accent)"></i>
        <div style="text-align:left">
          <div style="font-weight:600">Sinkronisasi & Tarik Data SIRS</div>
          <div class="text-muted" style="font-size:12px">Tarik data clipboard DataTables & buka SIRS Online</div>
        </div>
      </a>
      <a href="/master/profil-rs" class="btn btn-secondary w-100" style="justify-content:flex-start;gap:12px;padding:12px 16px;">
        <i data-lucide="hospital" style="width:20px;height:20px;color:var(--success)"></i>
        <div style="text-align:left">
          <div style="font-weight:600">Profil Rumah Sakit</div>
          <div class="text-muted" style="font-size:12px">Kelola data master identitas RS</div>
        </div>
      </a>
      <a href="/master/users" class="btn btn-secondary w-100" style="justify-content:flex-start;gap:12px;padding:12px 16px;">
        <i data-lucide="users" style="width:20px;height:20px;color:var(--warning)"></i>
        <div style="text-align:left">
          <div style="font-weight:600">Pengguna</div>
          <div class="text-muted" style="font-size:12px">Kelola akun operator dan administrator</div>
        </div>
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Sync Log -->
<?php
$syncLogs = Db::fetchAll(
  "SELECT sl.*, u.name as user_name FROM sync_log sl JOIN users u ON sl.user_id = u.id ORDER BY sl.id DESC LIMIT 10"
);
?>
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-lucide="activity"></i> Log Tarikan & Sinkronisasi Terakhir</h2>
    <?php if ($user['role'] === 'admin' && !empty($syncLogs)): ?>
    <a href="/master/sinkronisasi" class="btn btn-sm btn-secondary">
      <i data-lucide="settings"></i> Kelola & Hapus Log
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <?php if ($syncLogs): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:160px">Waktu</th>
            <th style="width:160px">Pengguna</th>
            <th style="width:140px">Aksi</th>
            <th>Catatan</th>
            <?php if ($user['role'] === 'admin'): ?>
            <th style="width:80px; text-align:right">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($syncLogs as $log): ?>
          <tr>
            <td class="text-muted fs-12"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
            <td><strong><?= e($log['user_name']) ?></strong></td>
            <td><span class="badge badge-primary"><?= e($log['action']) ?></span></td>
            <td><?= e($log['notes'] ?? '-') ?></td>
            <?php if ($user['role'] === 'admin'): ?>
            <td style="text-align:right">
              <form method="POST" action="/master/sinkronisasi/delete-log" onsubmit="return confirm('Hapus log #<?= $log['id'] ?>?');" style="display:inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_log">
                <input type="hidden" name="id" value="<?= $log['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary text-danger" title="Hapus log">
                  <i data-lucide="trash-2"></i>
                </button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <i data-lucide="inbox"></i>
      <strong>Belum ada log tarikan data</strong>
      <p>Log akan tercatat saat Anda menarik data dari SIRS atau melakukan sinkronisasi.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
view('layout/app', compact('pageTitle', 'breadcrumb', 'content'));
