<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$noLayanan   = (int)($input['no_layanan'] ?? 1);
$komponen    = trim($input['komponen'] ?? 'SDM');
$rawText     = trim($input['data'] ?? $input['pasted_data'] ?? '');
$snapshotName= trim($input['snapshot_name'] ?? '');

// Check if input directly provides full items array: items = [...]
if (isset($input['items']) && is_array($input['items']) && count($input['items']) > 0) {
    $parsedItems = $input['items'];
    $count = count($parsedItems);
    $snapTitle = $snapshotName ?: "Tarikan Otomatis Semua 24 Layanan SIRS (" . date('d/m/Y H:i') . ")";
    $periode = !empty($input['periode']) ? $input['periode'] : date('Y-m-d');
    $userId = $_SESSION['user_id'] ?? 1;

    Db::query(
        "INSERT INTO snapshots (name, periode, description, source_type, total_items, data_json, created_by)
         VALUES (?, ?, ?, 'sirs_auto_all', ?, ?, ?)",
        [
            $snapTitle,
            $periode,
            "Tarikan otomatis di belakang layar semua 24 layanan dari SIRS Online",
            $count,
            json_encode($parsedItems),
            $userId
        ]
    );

    Db::query(
        "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'auto_tarik_sirs_all', ?)",
        [$userId, "Tarikan otomatis komprehensif berhasil: {$count} baris data dari 24 layanan SIRS"]
    );

    echo json_encode([
        'status'  => 'success',
        'message' => "Berhasil menarik {$count} baris data seluruh 24 layanan SIRS",
        'total'   => $count,
        'snapshot'=> $snapTitle
    ]);
    exit;
}

if (empty($rawText)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data kosong']);
    exit;
}

// 24 Layanan list mapping
$layananNames = [
    1 => 'Jantung dan Pembuluh Darah', 2 => 'Paru dan Pernafasan', 3 => 'Uro Nefro', 4 => 'Neonatus',
    5 => 'Neoplasma', 6 => 'Ibu dan Ginekologi', 7 => 'Musculoskeletal dan Jaringan Lunak', 8 => 'THT',
    9 => 'Mata', 10 => 'Kulit & Penyakit Kelamin', 11 => 'Saraf/ Neuroscience', 12 => 'Infeksi dan Parasit',
    13 => 'Pencernaan dan Hepatobilier', 14 => 'Hematologi', 15 => 'Alergi Imunologi dan Rheumatologi',
    16 => 'Rekonstruksi', 17 => 'Keracunan', 18 => 'Endokrin, Nutrisi dan Metabolik', 19 => 'Luka Bakar',
    20 => 'Trauma', 21 => 'Jiwa', 22 => 'Gigi dan Mulut', 23 => 'Forensik', 24 => 'Rehabilitasi'
];
$namaLayanan = $layananNames[$noLayanan] ?? "Layanan {$noLayanan}";

$lines = preg_split('/\r\n|\r|\n/', $rawText);
$parsedItems = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    $cols = preg_split('/\t/', $line);
    if (count($cols) < 3) {
        $cols = str_getcsv($line, ';');
        if (count($cols) < 3) $cols = str_getcsv($line, ',');
    }

    $first = strtolower(trim($cols[0] ?? ''));
    if (in_array($first, ['no', 'no.', 'kode', 'nama', 'strata', 'layanan'])) continue;

    $strata   = 'Dasar';
    $kode     = '';
    $nama     = '';
    $kelompok = ($komponen === 'SDM') ? 'SDM Medis' : 'Alat Kesehatan';
    $status   = 'wajib';
    $jumlah   = 1;
    $group    = ($komponen === 'SDM') ? 'ksdm01' : 'kalkes01';

    $kategori = '';

    $isStrata = in_array(strtolower(trim($cols[1] ?? '')), ['dasar', 'madya', 'utama', 'paripurna']);
    $col4IsKelompok = in_array(strtolower(trim($cols[4] ?? '')), ['alat kesehatan', 'sarana']);
    $col3IsSDM = strtolower(trim($cols[3] ?? '')) === 'sdm medis';
    $col5IsStatus = in_array(strtolower(trim($cols[5] ?? '')), ['substitusi', 'wajib']);
    $col4IsStatus = in_array(strtolower(trim($cols[4] ?? '')), ['substitusi', 'wajib']);

    // Format 8 kolom Alkes & Sarana RS Online: No (0), Strata (1), Kode (2), Nama (3), Kelompok (4), Status (5), Jumlah (6), Group (7)
    if ($isStrata && count($cols) >= 8 && ($col4IsKelompok || ($col5IsStatus && is_numeric(trim($cols[2] ?? ''))) || $komponen === 'Alkes')) {
        $strata   = ucfirst(strtolower(trim($cols[1])));
        $kode     = trim($cols[2]);
        $nama     = trim($cols[3]);
        $rawKel   = strtolower(trim($cols[4]));
        $kelompok = ($rawKel === 'sarana') ? 'Sarana' : 'Alat Kesehatan';
        $status   = strtolower(trim($cols[5])) === 'substitusi' ? 'substitusi' : 'wajib';
        $jumlah   = is_numeric(trim($cols[6])) ? (int)trim($cols[6]) : 0;
        $group    = trim($cols[7]) ?: $group;
        $kategori = '';
    } elseif ($isStrata && count($cols) >= 7 && ($col3IsSDM || $col4IsStatus || !is_numeric(trim($cols[2] ?? '')))) {
        // Format SDM Medis standar SIRS: No (0), Strata (1), Nama (2), Kelompok (3), Status (4), Jumlah (5), Group (6), [Kategori Nakes (7)]
        $strata   = ucfirst(strtolower(trim($cols[1])));
        $kode     = '';
        $nama     = trim($cols[2]);
        $kelompok = trim($cols[3]) ?: $kelompok;
        $status   = strtolower(trim($cols[4])) === 'substitusi' ? 'substitusi' : 'wajib';
        $jumlah   = is_numeric(trim($cols[5])) ? (int)trim($cols[5]) : 0;
        $group    = trim($cols[6]) ?: $group;
        $kategori = isset($cols[7]) ? trim($cols[7]) : '';
    } elseif (count($cols) >= 3) {
        foreach ($cols as $c) {
            $cl = strtolower(trim($c));
            if (in_array($cl, ['dasar', 'madya', 'utama', 'paripurna'])) $strata = ucfirst($cl);
            if (in_array($cl, ['wajib', 'substitusi'])) $status = $cl;
            if (preg_match('/^k(sdm|alkes|sarana)\d+/i', $cl)) $group = strtolower($cl);
            if (in_array($cl, ['sdm medis', 'alat kesehatan', 'sarana'])) $kelompok = ucwords($cl);
        }
        $reserved = ['dasar', 'madya', 'utama', 'paripurna', 'wajib', 'substitusi', 'sdm medis', 'alat kesehatan', 'sarana', 'sdm', 'alkes'];
        foreach ($cols as $idx => $c) {
            $cl = strtolower(trim($c));
            if ($idx > 0 && strlen($cl) > 2 && !is_numeric($cl) && !in_array($cl, $reserved) && !preg_match('/^k(sdm|alkes|sarana)\d+/i', $cl)) {
                $nama = trim($c);
                if ($idx > 1 && is_numeric(trim($cols[$idx - 1]))) {
                    $kode = trim($cols[$idx - 1]);
                }
                break;
            }
        }
        if (!$nama) $nama = trim($cols[2] ?? $cols[1] ?? $cols[0]);
        if (isset($cols[count($cols)-1]) && is_numeric(trim($cols[count($cols)-1]))) {
            $jumlah = (int)trim($cols[count($cols)-1]);
        }
        if (isset($cols[7])) $kategori = trim($cols[7]);
    } else {
        $nama = trim($cols[0]);
    }

    if (!$nama || strlen($nama) < 2) continue;

    $parsedItems[] = [
        $noLayanan,
        $namaLayanan,
        $strata,
        $kode,
        $nama,
        $kelompok,
        $status,
        $jumlah,
        $group,
        $kategori
    ];
}

$count = count($parsedItems);
if ($count === 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada baris data valid yang terdeteksi']);
    exit;
}

$snapTitle = $snapshotName ?: "Tarikan Otomatis SIRS ({$namaLayanan} - {$komponen}) " . date('d/m/Y H:i');
$userId = $_SESSION['user_id'] ?? 1;

Db::query(
    "INSERT INTO snapshots (name, periode, description, source_type, total_items, data_json, created_by)
     VALUES (?, ?, ?, 'sirs_auto', ?, ?, ?)",
    [
        $snapTitle,
        date('Y-m-d'),
        "Tarikan otomatis di belakang layar dari SIRS Online ({$namaLayanan} - {$komponen})",
        $count,
        json_encode($parsedItems),
        $userId
    ]
);

Db::query(
    "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'auto_tarik_sirs', ?)",
    [$userId, "Tarikan otomatis berhasil: {$count} baris data ({$namaLayanan} - {$komponen})"]
);

echo json_encode([
    'status'  => 'success',
    'message' => "Berhasil menarik {$count} baris data ({$namaLayanan} - {$komponen})",
    'total'   => $count,
    'snapshot'=> $snapTitle
]);
