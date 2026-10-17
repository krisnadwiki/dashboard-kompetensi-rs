<?php
Auth::requireAdmin();

$user = Auth::user();
$rs   = Db::fetchOne("SELECT * FROM rs_profile LIMIT 1");
$rsonlineUrl = $rs['rsonline_url'] ?? 'https://sirs.kemkes.go.id/fo/dashboard';

// ─── 24 Layanan Terstandar Kemenkes ──────────────────────────────────────────
$layananList = [
    1  => ['nama' => 'Jantung dan Pembuluh Darah',        'status' => 'Tidak Kompeten'],
    2  => ['nama' => 'Paru dan Pernafasan',               'status' => 'Tidak Kompeten'],
    3  => ['nama' => 'Uro Nefro',                         'status' => 'Dasar'],
    4  => ['nama' => 'Neonatus',                          'status' => 'Dasar'],
    5  => ['nama' => 'Neoplasma',                         'status' => 'Dasar'],
    6  => ['nama' => 'Ibu dan Ginekologi',                'status' => 'Madya'],
    7  => ['nama' => 'Musculoskeletal dan Jaringan Lunak','status' => 'Dasar'],
    8  => ['nama' => 'THT',                               'status' => 'Tidak Kompeten'],
    9  => ['nama' => 'Mata',                              'status' => 'Tidak Kompeten'],
    10 => ['nama' => 'Kulit & Penyakit Kelamin',          'status' => 'Dasar'],
    11 => ['nama' => 'Saraf/ Neuroscience',               'status' => 'Tidak Kompeten'],
    12 => ['nama' => 'Infeksi dan Parasit',               'status' => 'Madya'],
    13 => ['nama' => 'Pencernaan dan Hepatobilier',       'status' => 'Dasar'],
    14 => ['nama' => 'Hematologi',                        'status' => 'Dasar'],
    15 => ['nama' => 'Alergi Imunologi dan Rheumatologi', 'status' => 'Madya'],
    16 => ['nama' => 'Rekonstruksi',                      'status' => 'Dasar'],
    17 => ['nama' => 'Keracunan',                         'status' => 'Dasar'],
    18 => ['nama' => 'Endokrin, Nutrisi dan Metabolik',   'status' => 'Dasar'],
    19 => ['nama' => 'Luka Bakar',                        'status' => 'Dasar'],
    20 => ['nama' => 'Trauma',                            'status' => 'Dasar'],
    21 => ['nama' => 'Jiwa',                              'status' => 'Dasar'],
    22 => ['nama' => 'Gigi dan Mulut',                    'status' => 'Dasar'],
    23 => ['nama' => 'Forensik',                          'status' => 'Tidak Kompeten'],
    24 => ['nama' => 'Rehabilitasi',                      'status' => 'Tidak Kompeten'],
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $action = $_POST['action'] ?? '';

    // 1. Delete single log
    if ($action === 'delete_log') {
        $logId = (int)($_POST['id'] ?? 0);
        if ($logId > 0) {
            Db::query("DELETE FROM sync_log WHERE id = ?", [$logId]);
            Flash::set('success', "Log tarikan/sinkronisasi #{$logId} berhasil dihapus.", 'success');
        }
        redirect('/master/sinkronisasi');
    }

    // 2. Clear all logs
    if ($action === 'clear_all_logs') {
        Db::query("TRUNCATE TABLE sync_log");
        Flash::set('success', "Seluruh log riwayat tarikan telah dibersihkan.", 'success');
        redirect('/master/sinkronisasi');
    }

    // 3. Tarik data dari SIRS (Paste clipboard / DataTables copy)
    if ($action === 'tarik_sirs') {
        $noLayanan = (int)($_POST['no_layanan'] ?? 1);
        $komponen  = trim($_POST['komponen'] ?? 'SDM'); // SDM / Alkes
        $pasted    = trim($_POST['pasted_data'] ?? '');
        $namaSnap  = trim($_POST['snapshot_name'] ?? '');

        if (empty($pasted)) {
            Flash::set('error', 'Data tempelan dari SIRS kosong.', 'error');
            redirect('/master/sinkronisasi');
        }

        $namaLayanan = $layananList[$noLayanan]['nama'] ?? "Layanan {$noLayanan}";

        // Parse lines (tab separated from DataTables 'Copy' or CSV)
        $lines = preg_split('/\r\n|\r|\n/', $pasted);
        $parsedItems = [];
        $headerFound = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Split by tab or semicolon or comma
            $cols = preg_split('/\t/', $line);
            if (count($cols) < 3) {
                $cols = str_getcsv($line, ';');
                if (count($cols) < 3) $cols = str_getcsv($line, ',');
            }

            // Skip header if detected
            $first = strtolower(trim($cols[0] ?? ''));
            if (in_array($first, ['no', 'no.', 'kode', 'nama', 'strata', 'layanan'])) {
                $headerFound = true;
                continue;
            }

            // Default extraction mapping
            // Many SIRS tables format: [No, Strata, Kode, Nama, Kelompok/Kategori, Status, Jumlah, Group]
            $strata  = 'Dasar';
            $kode    = '';
            $nama    = '';
            $kel     = ($komponen === 'SDM') ? 'SDM Medis' : 'Alat Kesehatan';
            $status  = 'wajib';
            $jumlah  = 1;
            $group   = ($komponen === 'SDM') ? 'ksdm01' : 'kalkes01';

            // 1. Direct full item export [no, layanan, strata, kode, nama, kelompok, status, jumlah, group, [kategori]]
            if (count($cols) >= 9 && (is_numeric(trim($cols[0])) && (int)trim($cols[0]) >= 1 && (int)trim($cols[0]) <= 24)) {
                $noLay = (int)$cols[0];
                $parsedItems[] = [
                    $noLay ?: $noLayanan,
                    $cols[1] ?: ($layananList[$noLay]['nama'] ?? $namaLayanan),
                    $cols[2] ?: 'Dasar',
                    $cols[3] ?? '',
                    $cols[4] ?? '',
                    $cols[5] ?? 'SDM Medis',
                    $cols[6] ?? 'wajib',
                    is_numeric($cols[7]) ? (int)$cols[7] : 1,
                    $cols[8] ?? 'ksdm01',
                    $cols[9] ?? ''
                ];
                continue;
            }

            $isStrata = in_array(strtolower(trim($cols[1] ?? '')), ['dasar', 'madya', 'utama', 'paripurna']);
            $col4IsKelompok = in_array(strtolower(trim($cols[4] ?? '')), ['alat kesehatan', 'sarana']);
            $col3IsSDM = strtolower(trim($cols[3] ?? '')) === 'sdm medis';
            $col5IsStatus = in_array(strtolower(trim($cols[5] ?? '')), ['substitusi', 'wajib']);
            $col4IsStatus = in_array(strtolower(trim($cols[4] ?? '')), ['substitusi', 'wajib']);

            // 2. Format 8 kolom Alkes & Sarana RS Online:
            // No (0), Strata (1), Kode (2), Nama (3), Kelompok (4), Status (5), Jumlah (6), Group (7)
            if ($isStrata && count($cols) >= 8 && ($col4IsKelompok || ($col5IsStatus && is_numeric(trim($cols[2] ?? ''))) || $komponen === 'Alkes')) {
                $strata   = ucfirst(strtolower(trim($cols[1])));
                $kode     = trim($cols[2]);
                $nama     = trim($cols[3]);
                $rawKel   = strtolower(trim($cols[4]));
                $kel      = ($rawKel === 'sarana') ? 'Sarana' : 'Alat Kesehatan';
                $status   = strtolower(trim($cols[5])) === 'substitusi' ? 'substitusi' : 'wajib';
                $jumlah   = is_numeric(trim($cols[6])) ? (int)trim($cols[6]) : 0;
                // Normalisasi prefix g → k agar konsisten: gsarana→ksarana, galkes→kalkes
                $rawGroup = trim($cols[7]) ?: $group;
                $group    = preg_replace('/^g(sdm|alkes|sarana)(\d)/i', 'k$1$2', strtolower($rawGroup));
                $kategori = '';

                $parsedItems[] = [
                    $noLayanan,
                    $namaLayanan,
                    $strata,
                    $kode,
                    $nama,
                    $kel,
                    $status,
                    $jumlah,
                    $group,
                    $kategori
                ];
                continue;
            }

            // 3. Format 7-8 kolom SDM Medis standar SIRS:
            // No (0), Strata (1), Nama (2), Kelompok (3), Status (4), Jumlah (5), Group (6), [Kategori Nakes (7)]
            if ($isStrata && count($cols) >= 7 && ($col3IsSDM || $col4IsStatus || !is_numeric(trim($cols[2] ?? '')))) {
                $strata   = ucfirst(strtolower(trim($cols[1])));
                $kode     = '';
                $nama     = trim($cols[2]);
                $kel      = trim($cols[3]) ?: 'SDM Medis';
                $status   = strtolower(trim($cols[4])) === 'substitusi' ? 'substitusi' : 'wajib';
                $jumlah   = is_numeric(trim($cols[5])) ? (int)trim($cols[5]) : 0;
                $group    = preg_replace('/^g(sdm|alkes|sarana)(\\d)/i', 'k$1$2', strtolower(trim($cols[6]) ?: $group));
                $kategori = isset($cols[7]) ? trim($cols[7]) : '';

                $parsedItems[] = [
                    $noLayanan,
                    $namaLayanan,
                    $strata,
                    $kode,
                    $nama,
                    $kel,
                    $status,
                    $jumlah,
                    $group,
                    $kategori
                ];
                continue;
            }

            // 4. Fallback jika kolom fleksibel / custom
            if (count($cols) >= 4) {
                foreach ($cols as $c) {
                    $cl = strtolower(trim($c));
                    if (in_array($cl, ['dasar', 'madya', 'utama', 'paripurna'])) {
                        $strata = ucfirst($cl);
                    }
                    if (in_array($cl, ['wajib', 'substitusi'])) {
                        $status = $cl;
                    }
                    // Cocokkan kode group dengan prefix k atau g (RS Online kadang pakai prefix g)
                    if (preg_match('/^[kg](sdm|alkes|sarana)\d+/i', $cl)) {
                        // Normalisasi: gsarana → ksarana, galkes → kalkes, gsdm → ksdm
                        $group = preg_replace('/^g(sdm|alkes|sarana)(\d)/i', 'k$1$2', strtolower($cl));
                    }
                    if (in_array($cl, ['alat kesehatan', 'sarana', 'sdm medis'])) {
                        $kel = ucwords($cl);
                    }
                }
                $reserved = ['dasar', 'madya', 'utama', 'paripurna', 'wajib', 'substitusi', 'sdm medis', 'alat kesehatan', 'sarana'];
                foreach ($cols as $idx => $c) {
                    $cl = strtolower(trim($c));
                    if ($idx > 0 && strlen($cl) > 2 && !is_numeric($cl) && !in_array($cl, $reserved) && !preg_match('/^[kg](sdm|alkes|sarana)\d+/i', $cl)) {
                        $nama = trim($c);
                        if ($idx > 1 && is_numeric(trim($cols[$idx - 1]))) {
                            $kode = trim($cols[$idx - 1]);
                        }
                        break;
                    }
                }
                if (!$nama) {
                    $nama = trim($cols[2] ?? $cols[1] ?? $cols[0]);
                }
                if (isset($cols[count($cols)-1]) && is_numeric(trim($cols[count($cols)-1]))) {
                    $jumlah = (int)trim($cols[count($cols)-1]);
                }
            } else {
                $nama = trim($cols[0]);
            }

            $parsedItems[] = [
                $noLayanan,
                $namaLayanan,
                $strata,
                $kode,
                $nama,
                $kel,
                $status,
                $jumlah,
                $group,
                ''
            ];
        }

        $count = count($parsedItems);
        if ($count === 0) {
            Flash::set('error', 'Gagal memproses baris data. Pastikan format tabel DataTables disalin lengkap.', 'error');
            redirect('/master/sinkronisasi');
        }

        // Save as snapshot if requested or default
        $snapTitle = $namaSnap ?: ($komponen === 'Semua' ? "Tarikan Otomatis Semua Layanan SIRS - " . date('d/m/Y H:i') : "Tarikan SIRS Layanan {$noLayanan} ({$komponen}) - " . date('d/m/Y H:i'));
        Db::query(
            "INSERT INTO snapshots (name, periode, description, source_type, total_items, data_json, created_by)
             VALUES (?, ?, ?, 'sirs_copy', ?, ?, ?)",
            [
                $snapTitle,
                date('Y-m-d'),
                "Tarikan data dari SIRS Online (" . ($komponen === 'Semua' ? 'Semua 24 Layanan' : "{$namaLayanan} - {$komponen}") . ")",
                $count,
                json_encode($parsedItems),
                $user['id']
            ]
        );

        // Record sync log
        Db::query(
            "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'tarik_sirs', ?)",
            [$user['id'], "Berhasil menarik {$count} baris data dari SIRS (" . ($komponen === 'Semua' ? 'Semua 24 Layanan' : "{$namaLayanan} - {$komponen}") . ")"]
        );

        Flash::set('success', "Berhasil menarik {$count} baris data (" . ($komponen === 'Semua' ? 'Semua 24 Layanan' : "{$namaLayanan} - {$komponen}") . ") dan disimpan sebagai snapshot.", 'success');
        redirect('/master/sinkronisasi');
    }

    // 4. Manual sync note
    if ($action === 'manual_note') {
        $notes = trim($_POST['notes'] ?? '');
        Db::query(
            "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'manual_note', ?)",
            [$user['id'], $notes ?: 'Catatan sinkronisasi manual']
        );
        Flash::set('success', 'Catatan sinkronisasi berhasil disimpan.', 'success');
        redirect('/master/sinkronisasi');
    }
}

// Fetch sync history
$history = Db::fetchAll(
    "SELECT sl.*, u.name as user_name, u.role as user_role 
     FROM sync_log sl 
     JOIN users u ON sl.user_id = u.id 
     ORDER BY sl.id DESC LIMIT 30"
);

// Fetch existing snapshots
$snapshots = Db::fetchAll(
    "SELECT id, name, periode, description, source_type, total_items, created_at
     FROM snapshots 
     ORDER BY id DESC"
);

$breadcrumb = [
    ['label' => 'Master Data', 'url' => '/master'],
    ['label' => 'Sinkronisasi RS Online']
];
$pageTitle = 'Sinkronisasi RS Online (SIRS)';

$targetApiUrl    = appUrl('/api/ingest-sirs');
$targetImportUrl = appUrl('/master/sinkronisasi?import_clipboard=1');
$targetSimrsUrl  = appUrl('/master/sinkronisasi');

// Bookmarklet JS: menarik semua 24 layanan dengan group unik per item
// PENTING:
// 1. Pakai textContent bukan innerText (DOMParser tidak punya layout engine jadi innerText selalu kosong)
// 2. Gunakan rgb(...) bukan hex (#) agar tidak terpotong oleh parser URL browser (# dianggap hash fragment).
// 3. TIDAK MENGGUNAKAN rawurlencode karena browser JS engine melempar SyntaxError pada %28 %29.
// 4. Deteksi lokasi: jika dijalankan bukan di situs kemkes.go.id, beri peringatan jelas & buka tab SIRS.
$rawJsTemplate = <<<'JS'
(function(){
  try {
    if (!location.hostname || location.hostname.indexOf('kemkes.go.id') < 0) {
      alert("⚠️ PERHATIAN:\n\nBookmarklet ini harus dijalankan saat tab browser Anda berada di situs SIRS RS Online (sirs.kemkes.go.id)!\n\nSaat ini Anda berada di: " + (location.hostname || 'halaman lokal') + "\n\nSistem akan membuka portal SIRS Kemenkes untuk Anda.");
      window.open('https://sirs.kemkes.go.id/fo/dashboard', '_blank');
      return;
    }

    var b = location.origin + '/fo/dashboard';
    var s = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24];
    var LAYANAN = ['', 'Jantung dan Pembuluh Darah', 'Paru dan Pernafasan', 'Uro Nefro', 'Neonatus', 'Neoplasma', 'Ibu dan Ginekologi', 'Muskuloskeletal dan Jaringan Lunak', 'THT', 'Mata', 'Kulit dan Penyakit Kelamin', 'Saraf', 'Infeksi dan Parasit', 'Pencernaan dan Hepatobilier', 'Hematologi', 'Alergi Imunologi dan Rheumatologi', 'Rekonstruksi dan Estetika', 'Keracunan', 'Endokrin, Nutrisi dan Metabolik', 'Luka Bakar', 'Trauma', 'Jiwa', 'Gigi dan Mulut', 'Forensik', 'Rehabilitasi'];
    var all = [];
    var done = 0;
    var total = s.length * 2;

    var oldBox = document.getElementById('sirs-sync-box');
    if (oldBox) oldBox.remove();

    var box = document.createElement('div');
    box.id = 'sirs-sync-box';
    box.style.cssText = 'position:fixed;top:20px;right:20px;z-index:2147483647;background:rgb(13,107,90);color:white;padding:16px 20px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.5);font-family:sans-serif;font-size:13.5px;min-width:320px;max-width:440px;line-height:1.5;box-sizing:border-box;';

    box.onclick = function(e) {
      if (e.target && (e.target.getAttribute('data-close') === '1' || (e.target.parentElement && e.target.parentElement.getAttribute('data-close') === '1'))) {
        box.remove();
      }
    };

    function renderBox(title, content, bgColor) {
      if (bgColor) box.style.background = bgColor;
      box.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><b style="font-size:14px;">' + title + '</b><button type="button" data-close="1" style="background:rgba(255,255,255,0.25);border:none;color:white;font-size:16px;width:26px;height:26px;border-radius:50%;cursor:pointer;line-height:1;margin-left:8px;display:inline-flex;align-items:center;justify-content:center;" title="Tutup">&times;</button></div>' + content;
    }

    renderBox('⚡ Menarik 24 Layanan SIRS...', '<div id="sirs-sp">Menginisialisasi koneksi...</div><div style="font-size:11px;opacity:0.85;margin-top:4px;">Mohon jangan tutup tab ini sampai proses selesai.</div>');
    document.body.appendChild(box);

    function txt(el) { return (el ? el.textContent || '' : '').trim(); }
    function upd() {
      var e = document.getElementById('sirs-sp');
      if (e) e.textContent = done + '/' + total + ' komponen selesai | ' + all.length + ' baris terkumpul';
    }

    function finish() {
      renderBox('✓ Selesai ' + all.length + ' Baris!', 'Mengirim data ke SIMRS...', 'rgb(22,163,74)');
      var targetApi = '{{TARGET_API_URL}}';
      var targetSimrs = '{{TARGET_SIMRS_URL}}';
      var targetImport = '{{TARGET_IMPORT_URL}}';
      var payload = JSON.stringify({
        items: all,
        snapshot_name: 'Tarik Otomatis 24 Layanan SIRS ' + new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID'),
        periode: new Date().toISOString().slice(0, 10)
      });

      fetch(targetApi, {
        method: 'POST',
        mode: 'cors',
        headers: { 'Content-Type': 'application/json' },
        body: payload
      })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        renderBox('✔ SUKSES DISIMPAN!', res.message + '<div style="margin-top:12px;display:flex;gap:8px;"><a href="' + targetSimrs + '" target="_blank" style="background:white;color:rgb(22,163,74);padding:7px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:bold;">🔗 Buka Hasil di SIMRS</a><button type="button" data-close="1" style="background:rgba(255,255,255,0.25);border:none;color:white;padding:7px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Tutup</button></div>', 'rgb(22,163,74)');
        setTimeout(function() { if (box && box.parentElement) box.remove(); }, 15000);
      })
      .catch(function(err) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(payload).then(function() {
            renderBox('📋 ' + all.length + ' Baris di-Copy ke Clipboard!', '<small>Data berhasil disalin. Buka SIMRS untuk langsung simpan snapshot.</small><div style="margin-top:12px;display:flex;gap:8px;"><a href="' + targetImport + '" target="_blank" style="background:rgb(22,163,74);color:white;text-decoration:none;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:bold;">🔗 Buka SIMRS &amp; Simpan</a><button type="button" data-close="1" style="background:rgba(255,255,255,0.25);border:none;color:white;padding:7px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Tutup</button></div>', 'rgb(217,119,6)');
          }).catch(function() {
            renderBox('Data Siap (' + all.length + ' baris)', '<div style="margin-top:8px;"><a href="' + targetImport + '" target="_blank" style="background:white;color:rgb(217,119,6);text-decoration:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:bold;">Buka SIMRS</a><button type="button" data-close="1" style="margin-left:6px;background:rgba(255,255,255,0.25);border:none;color:white;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Tutup</button></div>', 'rgb(217,119,6)');
          });
        } else {
          renderBox('Data Siap (' + all.length + ' baris)', '<div style="margin-top:8px;"><a href="' + targetImport + '" target="_blank" style="background:white;color:rgb(217,119,6);text-decoration:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:bold;">Buka SIMRS</a><button type="button" data-close="1" style="margin-left:6px;background:rgba(255,255,255,0.25);border:none;color:white;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Tutup</button></div>', 'rgb(217,119,6)');
        }
      });
    }

    function fetchKomp(no, k) {
      var url = b + '/' + (k === 'SDM' ? 'sdm_detil' : 'alkessarana_detil') + '/' + no;
      fetch(url)
        .then(function(r) { return r.text(); })
        .then(function(html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var nm = LAYANAN[no] || ('Layanan ' + no);
          var curStrata = 'Dasar';
          var colMap = (k === 'Alkes') ?
            { no: 0, strata: 1, kode: 2, nama: 3, kelompok: 4, status: 5, jumlah: 6, group: 7 } :
            { no: 0, strata: 1, nama: 2, kelompok: 3, status: 4, jumlah: 5, group: 6, kategori: 7 };

          var ths = doc.querySelectorAll('table thead th, table tr th');
          if (ths.length >= 4) {
            var found = {};
            ths.forEach(function(th, idx) {
              var h = txt(th).toLowerCase().replace(/[^a-z0-9]/g, '');
              if ((h === 'no' || h === 'nomor') && found.no === undefined) found.no = idx;
              else if (h === 'strata' && found.strata === undefined) found.strata = idx;
              else if ((h === 'kode' || h === 'kodealat' || h === 'kodesarana' || h === 'kd') && found.kode === undefined) found.kode = idx;
              else if ((h.indexOf('nama') >= 0 || h === 'item' || h === 'alat' || h === 'sarana' || h === 'komponen') && found.nama === undefined) found.nama = idx;
              else if ((h.indexOf('nakes') >= 0 || h.indexOf('spesifikasi') >= 0 || h === 'kategorinakes') && found.kategori === undefined) found.kategori = idx;
              else if ((h.indexOf('kelompok') >= 0 || h === 'kategori') && found.kelompok === undefined) found.kelompok = idx;
              else if ((h === 'status' || h === 'sifat') && found.status === undefined) found.status = idx;
              else if ((h.indexOf('jumlah') >= 0 || h === 'jml' || h === 'qty') && found.jumlah === undefined) found.jumlah = idx;
              else if ((h.indexOf('group') >= 0 || h.indexOf('grup') >= 0) && found.group === undefined) found.group = idx;
            });
            if (found.nama !== undefined && found.status !== undefined) colMap = found;
          }

          var trs = doc.querySelectorAll('table tr');
          var cnt = 0;
          trs.forEach(function(tr) {
            var tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;
            var cells = [];
            tds.forEach(function(td) { cells.push(txt(td)); });

            var fst = (cells[0] || '').toLowerCase();
            if (fst === 'no' || fst === 'no.' || fst === 'nomor') return;

            var rawStrata = (colMap.strata !== undefined && cells[colMap.strata]) ? cells[colMap.strata] : '';
            var rawKode = (colMap.kode !== undefined && cells[colMap.kode]) ? cells[colMap.kode] : (k === 'Alkes' && cells.length >= 8 ? cells[2] : '');
            var rawNama = (colMap.nama !== undefined && cells[colMap.nama]) ? cells[colMap.nama] : (k === 'Alkes' && cells.length >= 8 ? cells[3] : (cells[2] || cells[1] || ''));
            var rawKel = (colMap.kelompok !== undefined && cells[colMap.kelompok]) ? cells[colMap.kelompok] : (k === 'Alkes' && cells.length >= 8 ? cells[4] : (cells[3] || ''));
            var rawStat = (colMap.status !== undefined && cells[colMap.status]) ? cells[colMap.status] : (k === 'Alkes' && cells.length >= 8 ? cells[5] : (cells[4] || ''));
            var rawJml = (colMap.jumlah !== undefined && cells[colMap.jumlah]) ? cells[colMap.jumlah] : (k === 'Alkes' && cells.length >= 8 ? cells[6] : (cells[5] || '0'));
            var rawGrp = (colMap.group !== undefined && cells[colMap.group]) ? cells[colMap.group] : (k === 'Alkes' && cells.length >= 8 ? cells[7] : (cells[6] || ''));
            var rawKat = (colMap.kategori !== undefined && cells[colMap.kategori]) ? cells[colMap.kategori] : (cells[7] || '');

            var strata = curStrata;
            var sl = rawStrata.toLowerCase();
            if (['dasar', 'madya', 'utama', 'paripurna'].indexOf(sl) >= 0) {
              strata = sl.charAt(0).toUpperCase() + sl.slice(1);
              curStrata = strata;
            }

            var kode = rawKode.trim();
            var nama = rawNama.trim();

            if (/^[0-9]+$/.test(nama) && cells.length >= 4) {
              kode = nama;
              for (var c = 2; c < cells.length; c++) {
                var ct = cells[c].trim();
                if (ct.length > 2 && !/^[0-9]+$/.test(ct) && ['dasar', 'madya', 'utama', 'paripurna', 'alat kesehatan', 'sarana', 'wajib', 'substitusi'].indexOf(ct.toLowerCase()) < 0) {
                  nama = ct;
                  break;
                }
              }
            }

            if (['dasar', 'madya', 'utama', 'paripurna'].indexOf(nama.toLowerCase()) >= 0) {
              for (var c2 = 0; c2 < cells.length; c2++) {
                var ct2 = cells[c2].trim();
                if (ct2.length > 3 && ['dasar', 'madya', 'utama', 'paripurna', 'sdm', 'sdm medis', 'alat kesehatan', 'sarana', 'wajib', 'substitusi'].indexOf(ct2.toLowerCase()) < 0 && !/^[0-9]+$/.test(ct2)) {
                  nama = ct2;
                  break;
                }
              }
            }

            if (!nama || nama.length < 2) return;

            var kel = rawKel.trim();
            if (!kel) {
              kel = (k === 'SDM') ? 'SDM Medis' : 'Alat Kesehatan';
            } else {
              var kl = kel.toLowerCase();
              if (kl.indexOf('sarana') >= 0) kel = 'Sarana';
              else if (kl.indexOf('alat') >= 0 || kl.indexOf('alkes') >= 0) kel = 'Alat Kesehatan';
              else if (kl.indexOf('sdm') >= 0) kel = 'SDM Medis';
            }

            var status = 'wajib';
            var stl = rawStat.toLowerCase();
            if (stl.indexOf('substitusi') >= 0) status = 'substitusi';
            else if (stl.indexOf('wajib') >= 0) status = 'wajib';

            var jumlah = 0;
            var jn = parseInt(rawJml.replace(/[^0-9]/g, ''), 10);
            if (!isNaN(jn)) jumlah = jn;

            cnt++;
            var group = rawGrp.trim().toLowerCase();
            if (!group || !/^k(sdm|alkes|sarana)[0-9]+/i.test(group)) {
              var pfx = (kel === 'SDM Medis') ? 'ksdm' : (kel === 'Sarana' ? 'ksarana' : 'kalkes');
              group = pfx + (cnt < 10 ? '0' : '') + cnt;
            }

            var kat = rawKat.trim();
            all.push([no, nm, strata, kode, nama, kel, status, jumlah, group, kat]);
          });

          done++;
          upd();
          if (done === total) finish();
        })
        .catch(function(err) {
          done++;
          upd();
          if (done === total) finish();
        });
    }

    s.forEach(function(n) {
      setTimeout(function() {
        fetchKomp(n, 'SDM');
        fetchKomp(n, 'Alkes');
      }, n * 250);
    });

  } catch (globalErr) {
    alert('Terjadi kesalahan bookmarklet: ' + globalErr.message);
  }
})();
JS;

$rawJs = str_replace(
    ['{{TARGET_API_URL}}', '{{TARGET_SIMRS_URL}}', '{{TARGET_IMPORT_URL}}'],
    [$targetApiUrl, $targetSimrsUrl, $targetImportUrl],
    $rawJsTemplate
);

// Single line minification for bookmarklet link (no newlines)
$minJs = preg_replace('/\s+/', ' ', trim($rawJs));
$bookmarkletUrl = "javascript:" . $minJs;

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sinkronisasi RS Online (SIRS Kemenkes)</h1>
    <p class="page-subtitle">Deeplink terintegrasi 24 layanan, tarikan data clipboard DataTables, & audit log sinkronisasi</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-secondary" onclick="openTarikModal(1, 'SDM')">
      <i data-lucide="clipboard-paste"></i> Tarik Data dari Clipboard SIRS
    </button>
    <a href="<?= e($rsonlineUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg" onclick="logSyncClick()">
      <i data-lucide="external-link"></i> Buka Portal SIRS (Tab Baru)
    </a>
  </div>
</div>

<!-- Status Hero Card -->
<div class="card mb-24" style="background: linear-gradient(135deg, rgba(59,110,240,0.06), rgba(6,182,212,0.06)); border: 1px solid rgba(59,110,240,0.25);">
  <div class="card-body" style="padding:22px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap;">
      <div style="display:flex; gap:16px;">
        <div style="width:48px; height:48px; border-radius:var(--radius); background:linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
          <i data-lucide="zap" style="width:26px;height:26px;"></i>
        </div>
        <div>
          <h2 style="font-size:16px; font-weight:700; margin-bottom:4px;">Otomasi Tarik Data di Belakang Layar (Background Ingestion)</h2>
          <p class="text-muted" style="font-size:13px; max-width:720px;">
            Anda tidak perlu menyalin tabel satu per satu. Gunakan salah satu metode berikut:
            <br>
            <strong>1. Bookmarklet 1-Klik di SIRS</strong>: Pasang script di Bookmark browser atau salin kodenya. Saat membuka portal SIRS Kemenkes, klik bookmarklet maka sistem otomatis mengunduh ke-24 layanan sekaligus di latar belakang!
            <br>
            <strong>2. Jalankan di Console SIRS (Anti-Gagal)</strong>: Salin script bookmarklet, buka situs SIRS Kemenkes, tekan <kbd>F12</kbd> &rarr; tab <strong>Console</strong>, lalu tekan <kbd>Ctrl+V</kbd> &amp; <kbd>Enter</kbd>.
            <br>
            <strong>3. Tombol Tempel Otomatis</strong>: Jika menyalin tabel dari SIRS secara manual, klik <em>"Tarik Data"</em> lalu gunakan <em>"⚡ Ambil Otomatis dari Clipboard"</em>.
          </p>
        </div>
      </div>

      <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
        <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
          <!-- 1-Click Copy Script Button -->
          <button 
            type="button" 
            class="btn btn-primary" 
            onclick="copyBookmarkletScript()"
            title="Salin script bookmarklet ke clipboard (bisa dipaste di Bookmark Bar atau di Console SIRS)"
          >
            <i data-lucide="copy"></i> 📋 Salin Script Bookmarklet
          </button>

          <!-- Draggable Link Button -->
          <a 
            href="<?= htmlspecialchars($bookmarkletUrl, ENT_QUOTES, 'UTF-8') ?>" 
            class="btn btn-warning" 
            title="Tarik tombol ini ke Bookmarks Bar browser Anda!"
            onclick="openBookmarkletHelp(); return false;"
          >
            <i data-lucide="zap"></i> ⚡ Bookmarklet 24 Layanan
          </a>
        </div>

        <div style="display:flex; align-items:center; gap:8px; font-size:12px; margin-top:2px;">
          <button type="button" class="btn btn-sm btn-ghost" onclick="openBookmarkletHelp()" style="padding:2px 8px; font-size:12px; color:var(--primary);">
            <i data-lucide="help-circle"></i> Panduan Cara Pakai
          </button>
          <span class="text-muted">Target SIMRS:</span>
          <code style="background:var(--bg-input); border:1px solid var(--border); padding:2px 8px; border-radius:6px; font-weight:600; color:var(--primary);"><?= e(appUrl()) ?></code>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ─── IMPORT DARI CLIPBOARD BOOKMARKLET ─────────────────────────────────── -->
<div id="clipImportBanner" style="display:none; margin-bottom:16px;" class="card">
  <div class="card-body" style="background:linear-gradient(135deg,rgba(217,119,6,0.08),rgba(6,182,212,0.06)); border:1.5px solid rgba(217,119,6,0.3); padding:20px;">
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
      <div style="font-size:2rem;">📋</div>
      <div style="flex:1">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 4px;">Data Bookmarklet Siap di Clipboard</h3>
        <p class="text-muted" style="font-size:13px; margin:0;" id="clipImportStatus">
          Membaca data dari clipboard... Mohon izinkan akses clipboard jika browser meminta.
        </p>
      </div>
      <div style="display:flex; gap:8px; flex-shrink:0;">
        <button id="btnClipImport" class="btn btn-primary" onclick="doClipImport()">
          <i data-lucide="download"></i> Import Sekarang
        </button>
        <button class="btn btn-ghost" onclick="document.getElementById('clipImportBanner').style.display='none'">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ─── TABEL 24 LAYANAN TERINTEGRASI SIRS ────────────────────────────────── -->
<div class="card mb-24">
  <div class="card-header">
    <h2 class="card-title">
      <i data-lucide="layers"></i> Pemetaan 24 Layanan & Deeplink SIRS Online
    </h2>
    <span class="badge badge-primary">24 Layanan Terstandar</span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:50px; text-align:right">No</th>
            <th>Layanan</th>
            <th style="width:140px">Status Kompetensi</th>
            <th style="width:200px">Detil SIRS Online</th>
            <th style="width:120px">Modul Input</th>
            <th style="width:130px; text-align:right">Aksi Tarik</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($layananList as $no => $lay): 
            $statusBadge = match($lay['status']) {
              'Madya' => 'badge-warning',
              'Dasar' => 'badge-primary',
              default => 'badge-danger',
            };
          ?>
          <tr>
            <td style="text-align:right; font-weight:600; color:var(--text-muted)"><?= $no ?></td>
            <td><strong><?= e($lay['nama']) ?></strong></td>
            <td>
              <span class="badge <?= $statusBadge ?>"><?= e($lay['status']) ?></span>
            </td>
            <td>
              <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <a 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  class="btn btn-sm btn-warning" 
                  href="https://sirs.kemkes.go.id/fo/dashboard/sdm_detil/<?= $no ?>"
                  title="Buka Detil SDM di SIRS Kemenkes"
                >
                  <i data-lucide="user-check"></i> SDM
                </a>
                <a 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  class="btn btn-sm btn-outline" 
                  style="border-color:var(--accent); color:var(--accent);"
                  href="https://sirs.kemkes.go.id/fo/dashboard/alkessarana_detil/<?= $no ?>"
                  title="Buka Detil Alkes & Sarana di SIRS Kemenkes"
                >
                  <i data-lucide="stethoscope"></i> Alkes & Sarana
                </a>
              </div>
            </td>
            <td>
              <a 
                target="_blank" 
                rel="noopener noreferrer" 
                class="btn btn-sm btn-secondary" 
                href="https://sirs.kemkes.go.id/fo/kompetensi/kompetensi_<?= $no ?>"
                title="Buka formulir pembaruan kompetensi"
              >
                <i data-lucide="edit"></i> Update
              </a>
            </td>
            <td style="text-align:right">
              <button 
                class="btn btn-sm btn-primary"
                onclick="openTarikModal(<?= $no ?>, 'SDM', '<?= e($lay['nama']) ?>')"
                title="Tempel data yang disalin dari tabel SIRS (Copy)"
              >
                <i data-lucide="download"></i> Tarik Data
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─── SNAPSHOT DATA TERSEDIA ────────────────────────────────────────────── -->
<div class="card mb-24">
  <div class="card-header">
    <h2 class="card-title">
      <i data-lucide="database"></i> Snapshot Data Kompetensi per Periode
    </h2>
    <span class="badge badge-gray"><?= count($snapshots) ?> Snapshot Tersimpan</span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Nama Snapshot</th>
            <th>Periode</th>
            <th>Sumber</th>
            <th>Jumlah Baris</th>
            <th>Dibuat Pada</th>
            <th style="text-align:right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($snapshots as $sn): ?>
          <tr>
            <td>
              <strong><?= e($sn['name']) ?></strong>
              <?php if (!empty($sn['description'])): ?>
                <div class="text-muted fs-12"><?= e($sn['description']) ?></div>
              <?php endif; ?>
            </td>
            <td><code><?= e($sn['periode'] ?? '-') ?></code></td>
            <td>
              <span class="badge badge-gray"><?= strtoupper(e($sn['source_type'])) ?></span>
            </td>
            <td><span class="badge badge-primary"><?= number_format($sn['total_items']) ?> baris</span></td>
            <td class="fs-12 text-muted"><?= date('d/m/Y H:i', strtotime($sn['created_at'])) ?></td>
            <td style="text-align:right">
              <div class="table-actions" style="justify-content:flex-end;">
                <a href="/dashboard/kompetensi?snapshot=<?= $sn['id'] ?>" class="btn btn-sm btn-secondary" title="Buka di Dashboard Kompetensi">
                  <i data-lucide="eye"></i> Buka
                </a>
                <form method="POST" action="/master/snapshots/delete" onsubmit="return confirm('Hapus snapshot <?= e($sn['name']) ?>?');" style="display:inline;">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $sn['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Hapus snapshot">
                    <i data-lucide="trash-2"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─── RIWAYAT LOG TARIKAN & SINKRONISASI ─────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-lucide="history"></i> Log Tarikan & Aktivitas Sinkronisasi</h2>
    <div style="display:flex; align-items:center; gap:8px;">
      <span class="badge badge-gray"><?= count($history) ?> Catatan</span>
      <?php if (!empty($history)): ?>
      <form method="POST" action="/master/sinkronisasi/clear-logs" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SELURUH riwayat log tarikan?');" style="display:inline;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="clear_all_logs">
        <button type="submit" class="btn btn-sm btn-danger">
          <i data-lucide="trash"></i> Kosongkan Semua Log
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if ($history): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:60px">ID</th>
            <th style="width:160px">Waktu</th>
            <th style="width:160px">Operator</th>
            <th style="width:140px">Aksi</th>
            <th>Catatan Tarikan</th>
            <th style="width:80px; text-align:right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td><code>#<?= $h['id'] ?></code></td>
            <td class="fs-12 text-muted"><?= date('d/m/Y H:i:s', strtotime($h['created_at'])) ?></td>
            <td>
              <strong><?= e($h['user_name']) ?></strong>
              <div class="text-muted fs-12">(<?= e($h['user_role']) ?>)</div>
            </td>
            <td>
              <span class="badge badge-primary"><?= e($h['action']) ?></span>
            </td>
            <td><?= e($h['notes'] ?? '-') ?></td>
            <td style="text-align:right">
              <form method="POST" action="/master/sinkronisasi/delete-log" onsubmit="return confirm('Hapus log #<?= $h['id'] ?>?');" style="display:inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_log">
                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary text-danger" title="Hapus log ini">
                  <i data-lucide="trash-2"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <i data-lucide="clock"></i>
      <strong>Belum ada catatan aktivitas tarikan</strong>
      <p>Log akan tercatat otomatis saat Anda membuka SIRS atau menarik data.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ─── MODAL TARIK / PASTE DATA DARI SIRS (COPY DATATABLES) ─────────────── -->
<div class="modal-backdrop" id="tarikModal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <h3 class="modal-title" id="tarikModalTitle">Tarik Data dari SIRS Kemenkes</h3>
      <button type="button" class="modal-close" onclick="closeTarikModal()" title="Tutup Modal" aria-label="Tutup">&times;</button>
    </div>
    <form method="POST" action="/master/sinkronisasi/tarik-sirs">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="tarik_sirs">

      <div class="modal-body">
        <div style="background:var(--primary-light); padding:12px; border-radius:var(--radius-sm); border:1px solid rgba(59,110,240,0.2); font-size:12.5px; margin-bottom:16px;">
          <div style="font-weight:600; color:var(--primary); margin-bottom:4px;">
            <i data-lucide="info" style="width:14px;height:14px;display:inline-block;vertical-align:middle"></i> Cara Mengambil Data:
          </div>
          1. Buka halaman detil layanan di SIRS Online.<br>
          2. Klik tombol <code>Copy</code> di atas tabel DataTables.<br>
          3. Tempelkan (Ctrl+V) langsung ke area teks di bawah ini.
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="tarikLayanan">Pilih Layanan Target <span class="req">*</span></label>
            <select id="tarikLayanan" name="no_layanan" class="form-control" required>
              <?php foreach ($layananList as $no => $lay): ?>
                <option value="<?= $no ?>"><?= $no ?>. <?= e($lay['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="tarikKomponen">Komponen <span class="req">*</span></label>
            <select id="tarikKomponen" name="komponen" class="form-control" required>
              <option value="SDM">SDM Medis (Tenaga Kesehatan)</option>
              <option value="Alkes">Alat Kesehatan & Sarana</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="snapshotName">Beri Label Periode / Snapshot Baru</label>
          <input type="text" id="snapshotName" name="snapshot_name" class="form-control" placeholder="Contoh: Tarikan SIRS April 2026">
        </div>

        <div style="margin-bottom:14px;">
          <button type="button" class="btn btn-outline w-100" onclick="autoReadClipboard()" style="justify-content:center; padding:11px; border-style:dashed; border-width:2px; font-weight:600;">
            <i data-lucide="clipboard-copy"></i> ⚡ Ambil Otomatis dari Clipboard (1-Klik Tanpa Perlu Paste)
          </button>
          <div id="clipStatus" style="font-size:12px; margin-top:4px; text-align:center;"></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="pastedData">Atau Tempel Manual di Sini (Hasil 'Copy' SIRS): <span class="req">*</span></label>
          <textarea 
            id="pastedData" 
            name="pasted_data" 
            class="form-control" 
            rows="6" 
            required 
            placeholder="Klik tombol 'Ambil Otomatis' di atas, atau tempel manual (Ctrl+V) di sini..."
            style="font-family:monospace; font-size:12px; white-space:pre;"
          ></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeTarikModal()">
          <i data-lucide="x"></i> Tutup
        </button>
        <button type="submit" class="btn btn-primary">
          <i data-lucide="check"></i> Proses & Simpan Tarikan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL PANDUAN & SCRIPT BOOKMARKLET ──────────────────────────────────── -->
<div class="modal-backdrop" id="bookmarkletHelpModal">
  <div class="modal" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title">
        <i data-lucide="zap" style="color:var(--warning); vertical-align:middle;"></i> Panduan &amp; Script Tarik 24 Layanan Otomatis
      </h3>
      <button type="button" class="modal-close" onclick="closeBookmarkletHelp()" title="Tutup Modal" aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:rgba(22,163,74,0.08); border:1px solid rgba(22,163,74,0.25); border-radius:8px; padding:12px 16px; margin-bottom:16px;">
        <strong style="color:rgb(22,163,74); display:flex; align-items:center; gap:6px; margin-bottom:4px;">
          <i data-lucide="check-circle" style="width:16px;height:16px;"></i> Cara 1 (Paling Cepat &amp; Anti-Gagal 100%): Lewat Console SIRS
        </strong>
        <ol style="margin:6px 0 0 18px; padding:0; font-size:13px; line-height:1.6;">
          <li>Klik tombol <strong>"Salin Script"</strong> di bawah.</li>
          <li>Buka situs portal SIRS Kemenkes: <a href="https://sirs.kemkes.go.id/fo/dashboard" target="_blank" style="font-weight:600; text-decoration:underline;">sirs.kemkes.go.id/fo/dashboard</a></li>
          <li>Tekan tombol <kbd>F12</kbd> (atau klik kanan &rarr; <em>Inspect</em>) &rarr; pilih tab <strong>Console</strong>.</li>
          <li>Tekan <kbd>Ctrl+V</kbd> (Paste) lalu tekan <kbd>Enter</kbd>.</li>
          <li>Kotak status warna hijau/emas akan muncul di pojok kanan atas SIRS dan menarik otomatis seluruh 24 Layanan!</li>
        </ol>
      </div>

      <div style="background:rgba(59,110,240,0.08); border:1px solid rgba(59,110,240,0.25); border-radius:8px; padding:12px 16px; margin-bottom:16px;">
        <strong style="color:var(--primary); display:flex; align-items:center; gap:6px; margin-bottom:4px;">
          <i data-lucide="bookmark" style="width:16px;height:16px;"></i> Cara 2: Pasang Manual di Bookmark Bar Browser
        </strong>
        <ol style="margin:6px 0 0 18px; padding:0; font-size:13px; line-height:1.6;">
          <li>Klik tombol <strong>"Salin Script"</strong> di bawah.</li>
          <li>Pada Bookmarks Bar browser Anda, klik kanan &rarr; pilih <strong>Add page / Tambah halaman</strong>.</li>
          <li>Beri Nama: <code>⚡ Tarik SIMRS</code>.</li>
          <li>Pada kolom <strong>URL</strong>, tempelkan (Ctrl+V) script yang sudah disalin, lalu klik <strong>Save / Simpan</strong>.</li>
          <li>Buka situs SIRS, lalu klik bookmark tersebut 1 kali!</li>
        </ol>
      </div>

      <div class="form-group" style="margin-bottom:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label class="form-label" style="margin:0; font-weight:600;">Kode Script Bookmarklet:</label>
          <button type="button" class="btn btn-sm btn-primary" onclick="copyBookmarkletScript()">
            <i data-lucide="copy"></i> Salin Script ke Clipboard
          </button>
        </div>
        <textarea 
          id="bookmarkletRawCode" 
          class="form-control" 
          rows="5" 
          readonly 
          style="font-family:monospace; font-size:11.5px; background:var(--bg-input); word-break:break-all;"
        ><?= e($bookmarkletUrl) ?></textarea>
      </div>
      <div id="copySuccessAlert" style="display:none; color:rgb(22,163,74); font-size:12.5px; font-weight:600; margin-top:4px;">
        ✓ Kode script bookmarklet berhasil disalin ke clipboard!
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeBookmarkletHelp()">
        <i data-lucide="x"></i> Tutup
      </button>
      <button type="button" class="btn btn-primary" onclick="copyBookmarkletScript()">
        <i data-lucide="copy"></i> Salin Script
      </button>
      <a href="https://sirs.kemkes.go.id/fo/dashboard" target="_blank" rel="noopener noreferrer" class="btn btn-warning">
        <i data-lucide="external-link"></i> Buka Portal SIRS
      </a>
    </div>
  </div>
</div>

<script>
  function openBookmarkletHelp() {
    document.getElementById('bookmarkletHelpModal').classList.add('open');
    if (window.lucide) lucide.createIcons();
  }

  function closeBookmarkletHelp() {
    document.getElementById('bookmarkletHelpModal').classList.remove('open');
  }

  document.getElementById('bookmarkletHelpModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeBookmarkletHelp();
  });

  function copyBookmarkletScript() {
    const ta = document.getElementById('bookmarkletRawCode');
    const text = ta ? ta.value : '';
    if (!text) return;
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function() {
        showCopySuccess();
      }).catch(function() {
        ta.select();
        document.execCommand('copy');
        showCopySuccess();
      });
    } else {
      ta.select();
      document.execCommand('copy');
      showCopySuccess();
    }
  }

  function showCopySuccess() {
    const alertBox = document.getElementById('copySuccessAlert');
    if (alertBox) {
      alertBox.style.display = 'block';
      setTimeout(function() { alertBox.style.display = 'none'; }, 4000);
    }
    alert("✅ Kode Script Bookmarklet BERHASIL DISALIN!\n\nCara pakai paling mudah di SIRS:\n1. Buka tab situs SIRS Kemenkes (sirs.kemkes.go.id)\n2. Tekan F12 -> Buka tab 'Console'\n3. Tekan Ctrl+V (Paste) lalu tekan Enter!\n\nProses tarikan 24 layanan akan langsung berjalan otomatis di layar SIRS.");
  }

  function openTarikModal(no, komp, nama) {
    document.getElementById('tarikLayanan').value = no;
    document.getElementById('tarikKomponen').value = komp || 'SDM';
    document.getElementById('snapshotName').value = 'Tarikan SIRS ' + (nama ? nama + ' ' : '') + 'per ' + new Date().toLocaleDateString('id-ID');
    document.getElementById('pastedData').value = '';
    document.getElementById('clipStatus').innerHTML = '';
    document.getElementById('tarikModal').classList.add('open');
  }

  function closeTarikModal() {
    document.getElementById('tarikModal').classList.remove('open');
  }

  document.getElementById('tarikModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeTarikModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeTarikModal();
      closeBookmarkletHelp();
    }
  });

  async function autoReadClipboard() {
    const status = document.getElementById('clipStatus');
    try {
      if (!navigator.clipboard || !navigator.clipboard.readText) {
        status.innerHTML = '<span class="text-danger">Browser tidak mendukung pembacaan otomatis clipboard. Silakan gunakan Ctrl+V di kotak bawah.</span>';
        return;
      }
      const text = await navigator.clipboard.readText();
      if (!text || !text.trim()) {
        status.innerHTML = '<span class="text-warning">Clipboard Anda kosong. Silakan klik tombol "Copy" di atas tabel SIRS terlebih dahulu.</span>';
        return;
      }
      document.getElementById('pastedData').value = text;
      const lines = text.trim().split('\n').filter(l => l.trim().length > 0);
      status.innerHTML = '<span class="text-success fw-600"><i data-lucide="check-circle" style="width:14px;height:14px;display:inline-block;vertical-align:middle"></i> Berhasil membaca clipboard! Terdeteksi ' + lines.length + ' baris data.</span>';
      lucide.createIcons();
    } catch (err) {
      status.innerHTML = '<span class="text-warning">Izin akses clipboard belum diberikan oleh browser. Silakan tempel manual dengan menekan Ctrl+V di kotak teks di bawah.</span>';
    }
  }

  function logSyncClick() {
    fetch('/api/sync-log', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '<?= Csrf::token() ?>'
      },
      body: JSON.stringify({
        action: 'deeplink_open',
        notes: 'Membuka Portal SIRS Kemenkes melalui Deeplink Langsung'
      })
    }).then(() => {
      setTimeout(() => window.location.reload(), 1500);
    }).catch(err => console.log('Sync log sent'));
  }

  // ─── Import dari Clipboard Bookmarklet ───────────────────────────────────
  // Dipanggil saat URL mengandung ?import_clipboard=1 (diarahkan oleh bookmarklet)
  async function doClipImport() {
    const btn = document.getElementById('btnClipImport');
    const status = document.getElementById('clipImportStatus');
    if (btn) btn.disabled = true;
    
    let clipText = '';
    try {
      clipText = await navigator.clipboard.readText();
    } catch (e) {
      if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Tidak bisa membaca clipboard: ' + e.message + '<br>Pastikan Anda memberi izin clipboard di browser.</span>';
      if (btn) btn.disabled = false;
      return;
    }

    if (!clipText || clipText.length < 10) {
      if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Clipboard kosong atau tidak ada data dari bookmarklet.</span>';
      if (btn) btn.disabled = false;
      return;
    }

    // Parse JSON dari bookmarklet
    let payload = null;
    try {
      payload = JSON.parse(clipText);
    } catch (e) {
      if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Format data tidak valid (bukan JSON). Pastikan bookmarklet terbaru digunakan.</span>';
      if (btn) btn.disabled = false;
      return;
    }

    if (!payload || !payload.items || !Array.isArray(payload.items)) {
      if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Struktur data tidak valid. Jalankan ulang bookmarklet.</span>';
      if (btn) btn.disabled = false;
      return;
    }

    if (status) status.innerHTML = '⏳ Mengirim ' + payload.items.length.toLocaleString('id-ID') + ' baris ke server...';

    try {
      const resp = await fetch('/api/ingest-sirs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= Csrf::token() ?>' },
        body: JSON.stringify(payload)
      });
      const result = await resp.json();
      if (result.status === 'success') {
        if (status) status.innerHTML = '<span style="color:var(--success)">✅ <strong>' + result.message + '</strong> Snapshot baru tersimpan!</span>';
        // Hapus query string dan reload setelah 2 detik untuk menampilkan snapshot baru
        setTimeout(() => {
          window.history.replaceState({}, '', '/master/sinkronisasi');
          window.location.reload();
        }, 2500);
      } else {
        if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Error: ' + (result.message || 'Unknown error') + '</span>';
        if (btn) btn.disabled = false;
      }
    } catch (e) {
      if (status) status.innerHTML = '<span style="color:var(--danger)">❌ Gagal kirim: ' + e.message + '</span>';
      if (btn) btn.disabled = false;
    }
  }

  // Auto-trigger jika URL mengandung ?import_clipboard=1
  window.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('import_clipboard') === '1') {
      const banner = document.getElementById('clipImportBanner');
      if (banner) {
        banner.style.display = 'block';
        banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Auto-import setelah 800ms agar halaman selesai render
        setTimeout(doClipImport, 800);
      }
    }
  });
</script>


<?php
$content = ob_get_clean();
view('layout/app', compact('pageTitle', 'breadcrumb', 'content'));
