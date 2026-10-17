<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$isAuth = Auth::check();

// ─── PROTEKSI AKSES PUBLIK (TANPA LOGIN) ────────────────────────────────────
// Kebijakan Keamanan:
// 1. Publik HANYA diizinkan membaca 1 snapshot TERBARU (Data terkini yang berlaku).
// 2. Publik TIDAK BISA mengakses riwayat masa lalu (?id=X ditolak/diabaikan).
// 3. Publik TIDAK BISA melihat audit list snapshots.
// 4. Cache-Control 60s untuk melindungi server dari serangan DoS / repetitive queries.
if (!$isAuth) {
    header('Cache-Control: public, max-age=60');

    $latestSnap = Db::fetchOne(
        "SELECT id, name, periode, description, total_items, data_json, created_at 
         FROM snapshots 
         ORDER BY id DESC LIMIT 1"
    );

    if (!$latestSnap) {
        echo json_encode([
            'status'     => 'empty',
            'is_public'  => true,
            'message'    => 'Belum ada data tarikan snapshot terbaru di sistem',
            'items'      => []
        ]);
        exit;
    }

    $items = json_decode($latestSnap['data_json'], true) ?? [];
    echo json_encode([
        'status'     => 'success',
        'is_public'  => true,
        'id'         => $latestSnap['id'],
        'name'       => $latestSnap['name'],
        'periode'    => $latestSnap['periode'],
        'total'      => count($items),
        'created_at' => $latestSnap['created_at'],
        'items'      => $items
    ]);
    exit;
}

// ─── AKSES TEROTENTIKASI (PETUGAS / ADMIN RS) ──────────────────────────────
header('Cache-Control: private, no-cache');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $snap = Db::fetchOne(
        "SELECT id, name, periode, description, total_items, data_json, created_at 
         FROM snapshots 
         WHERE id = ?", 
        [$id]
    );
    if ($snap) {
        $items = json_decode($snap['data_json'], true) ?? [];
        echo json_encode([
            'status'     => 'success',
            'is_public'  => false,
            'id'         => $snap['id'],
            'name'       => $snap['name'],
            'periode'    => $snap['periode'],
            'total'      => count($items),
            'created_at' => $snap['created_at'],
            'items'      => $items
        ]);
        exit;
    }
}

// Jika tidak ada ID khusus, kembalikan daftar seluruh snapshots untuk dropdown internal
$snapshots = Db::fetchAll(
    "SELECT id, name, periode, description, total_items, created_at 
     FROM snapshots 
     ORDER BY id DESC"
);

echo json_encode([
    'status'    => 'success',
    'is_public' => false,
    'snapshots' => $snapshots
]);
