<?php
Auth::requireAdmin();

$user = Auth::user();

// Fetch current RS profile
$rs = Db::fetchOne("SELECT * FROM rs_profile LIMIT 1");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $nama_rs        = trim($_POST['nama_rs'] ?? '');
    $kode_rs        = trim($_POST['kode_rs'] ?? '');
    $jenis_rs       = trim($_POST['jenis_rs'] ?? '');
    $kelas_rs       = trim($_POST['kelas_rs'] ?? 'C');
    $kepemilikan    = trim($_POST['kepemilikan'] ?? '');
    $direktur       = trim($_POST['direktur'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $kota_kabupaten = trim($_POST['kota_kabupaten'] ?? '');
    $provinsi       = trim($_POST['provinsi'] ?? '');
    $kode_pos       = trim($_POST['kode_pos'] ?? '');
    $no_telepon     = trim($_POST['no_telepon'] ?? '');
    $no_fax         = trim($_POST['no_fax'] ?? '');
    $email_rs       = trim($_POST['email_rs'] ?? '');
    $website        = trim($_POST['website'] ?? '');
    $no_izin        = trim($_POST['no_izin'] ?? '');
    $tgl_izin       = !empty($_POST['tgl_izin']) ? $_POST['tgl_izin'] : null;
    $rsonline_url   = trim($_POST['rsonline_url'] ?? 'https://sirs.kemkes.go.id/fo/dashboard');

    if (empty($nama_rs)) {
        Flash::set('error', 'Nama Rumah Sakit wajib diisi.', 'error');
    } else {
        if ($rs) {
            Db::query(
                "UPDATE rs_profile SET
                    nama_rs = ?, kode_rs = ?, jenis_rs = ?, kelas_rs = ?, kepemilikan = ?,
                    direktur = ?, alamat = ?, kota_kabupaten = ?, provinsi = ?, kode_pos = ?,
                    no_telepon = ?, no_fax = ?, email_rs = ?, website = ?, no_izin = ?,
                    tgl_izin = ?, rsonline_url = ?, updated_by = ?
                 WHERE id = ?",
                [
                    $nama_rs, $kode_rs, $jenis_rs, $kelas_rs, $kepemilikan,
                    $direktur, $alamat, $kota_kabupaten, $provinsi, $kode_pos,
                    $no_telepon, $no_fax, $email_rs, $website, $no_izin,
                    $tgl_izin, $rsonline_url, $user['id'], $rs['id']
                ]
            );
        } else {
            Db::query(
                "INSERT INTO rs_profile (
                    nama_rs, kode_rs, jenis_rs, kelas_rs, kepemilikan,
                    direktur, alamat, kota_kabupaten, provinsi, kode_pos,
                    no_telepon, no_fax, email_rs, website, no_izin,
                    tgl_izin, rsonline_url, updated_by
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $nama_rs, $kode_rs, $jenis_rs, $kelas_rs, $kepemilikan,
                    $direktur, $alamat, $kota_kabupaten, $provinsi, $kode_pos,
                    $no_telepon, $no_fax, $email_rs, $website, $no_izin,
                    $tgl_izin, $rsonline_url, $user['id']
                ]
            );
        }

        Flash::set('success', 'Profil Rumah Sakit berhasil diperbarui.', 'success');
        redirect('/master/profil-rs');
    }
}

$breadcrumb = [
    ['label' => 'Master Data', 'url' => '/master'],
    ['label' => 'Profil Rumah Sakit']
];
$pageTitle = 'Master Profil Rumah Sakit';

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Master Data Rumah Sakit</h1>
    <p class="page-subtitle">Kelola informasi identitas, kontak, perizinan, dan konfigurasi RS Online (SIRS)</p>
  </div>
  <div class="page-actions">
    <a href="/master/sinkronisasi" class="btn btn-primary">
      <i data-lucide="refresh-cw"></i> Uji Sinkronisasi RS Online
    </a>
  </div>
</div>

<form method="POST" action="/master/profil-rs">
  <?= Csrf::field() ?>

  <div class="profil-grid">
    
    <!-- Left Column: Form Details -->
    <div>
      <div class="card mb-16">
        <div class="card-header">
          <h2 class="card-title"><i data-lucide="building-2"></i> Identitas Fasilitas Kesehatan</h2>
        </div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nama_rs">Nama Rumah Sakit <span class="req">*</span></label>
              <input type="text" id="nama_rs" name="nama_rs" class="form-control" value="<?= e($rs['nama_rs'] ?? '') ?>" required>
              <div class="form-hint">Contoh: RSUD Kilisuci</div>
            </div>
            <div class="form-group">
              <label class="form-label" for="kode_rs">Kode Faskes / RS (Kemenkes)</label>
              <input type="text" id="kode_rs" name="kode_rs" class="form-control" value="<?= e($rs['kode_rs'] ?? '') ?>" placeholder="Misal: 3571002">
              <div class="form-hint">Kode resmi 7 digit di SIRS Online</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="kelas_rs">Kelas Rumah Sakit</label>
              <select id="kelas_rs" name="kelas_rs" class="form-control">
                <?php foreach (['A', 'B', 'C', 'D', 'D Pratama'] as $k): ?>
                  <option value="<?= $k ?>" <?= ($rs['kelas_rs'] ?? 'C') === $k ? 'selected' : '' ?>>Kelas <?= $k ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="jenis_rs">Jenis Rumah Sakit</label>
              <input type="text" id="jenis_rs" name="jenis_rs" class="form-control" value="<?= e($rs['jenis_rs'] ?? 'Rumah Sakit Umum') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="kepemilikan">Kepemilikan</label>
              <input type="text" id="kepemilikan" name="kepemilikan" class="form-control" value="<?= e($rs['kepemilikan'] ?? 'Pemerintah Kota Kediri') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="direktur">Nama Direktur / Pimpinan</label>
              <input type="text" id="direktur" name="direktur" class="form-control" value="<?= e($rs['direktur'] ?? '') ?>" placeholder="dr. ...">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-16">
        <div class="card-header">
          <h2 class="card-title"><i data-lucide="map-pin"></i> Alamat & Kontak</h2>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label" for="alamat">Alamat Lengkap</label>
            <textarea id="alamat" name="alamat" class="form-control" rows="2"><?= e($rs['alamat'] ?? '') ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="kota_kabupaten">Kota / Kabupaten</label>
              <input type="text" id="kota_kabupaten" name="kota_kabupaten" class="form-control" value="<?= e($rs['kota_kabupaten'] ?? 'Kota Kediri') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="provinsi">Provinsi</label>
              <input type="text" id="provinsi" name="provinsi" class="form-control" value="<?= e($rs['provinsi'] ?? 'Jawa Timur') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="no_telepon">No. Telepon</label>
              <input type="text" id="no_telepon" name="no_telepon" class="form-control" value="<?= e($rs['no_telepon'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="email_rs">Email Resmi</label>
              <input type="email" id="email_rs" name="email_rs" class="form-control" value="<?= e($rs['email_rs'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i data-lucide="file-badge"></i> Perizinan & Regulasi</h2>
        </div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="no_izin">Nomor Izin Operasional</label>
              <input type="text" id="no_izin" name="no_izin" class="form-control" value="<?= e($rs['no_izin'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="tgl_izin">Masa Berlaku Izin</label>
              <input type="date" id="tgl_izin" name="tgl_izin" class="form-control" value="<?= e($rs['tgl_izin'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: RS Online Settings & Save Button -->
    <div>
      <div class="card mb-16" style="border-top: 3px solid var(--accent);">
        <div class="card-header">
          <h2 class="card-title"><i data-lucide="globe"></i> Integrasi RS Online (SIRS)</h2>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label" for="rsonline_url">Deeplink Target URL <span class="req">*</span></label>
            <input type="url" id="rsonline_url" name="rsonline_url" class="form-control" value="<?= e($rs['rsonline_url'] ?? 'https://sirs.kemkes.go.id/fo/dashboard') ?>" required>
            <div class="form-hint">Alamat endpoint portal RS Online SIRS Kemkes yang dituju saat sinkronisasi</div>
          </div>

          <div style="background:var(--bg); padding:12px; border-radius:var(--radius-sm); border:1px solid var(--border); font-size:12px; color:var(--text-muted); margin-bottom:16px;">
            <div style="font-weight:600; color:var(--text); margin-bottom:4px; display:flex; align-items:center; gap:6px;">
              <i data-lucide="info" style="width:14px;height:14px;color:var(--primary)"></i> Catatan Sinkronisasi
            </div>
            URL ini digunakan tombol <strong>Sinkronisasi RS Online</strong>. Sistem akan membuka deeplink dengan validasi sesi login aktif.
          </div>

          <a href="<?= e($rs['rsonline_url'] ?? 'https://sirs.kemkes.go.id/fo/dashboard') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline w-100" style="justify-content:center;">
            <i data-lucide="external-link"></i> Tes Buka URL Ini
          </a>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 btn-lg" style="justify-content:center;">
            <i data-lucide="save"></i> Simpan Profil RS
          </button>
          <div class="text-muted text-center fs-12 mt-8">
            Terakhir diubah: <?= !empty($rs['updated_at']) ? date('d/m/Y H:i', strtotime($rs['updated_at'])) : '-' ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</form>

<?php
$content = ob_get_clean();
view('layout/app', compact('pageTitle', 'breadcrumb', 'content'));
