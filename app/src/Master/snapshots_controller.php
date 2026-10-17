<?php
Auth::requireAdmin();

$user   = Auth::user();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $periode     = trim($_POST['periode'] ?? date('Y-m'));
        $description = trim($_POST['description'] ?? '');
        $source_type = trim($_POST['source_type'] ?? 'manual');
        $data_json   = $_POST['data_json'] ?? '';

        if (empty($name)) {
            Flash::set('error', 'Nama snapshot wajib diisi.', 'error');
            redirect('/dashboard/kompetensi');
        }

        // Validate JSON
        $decoded = json_decode($data_json, true);
        if (!is_array($decoded) || empty($decoded)) {
            Flash::set('error', 'Data kompetensi snapshot tidak valid atau kosong.', 'error');
            redirect('/dashboard/kompetensi');
        }

        Db::query(
            "INSERT INTO snapshots (name, periode, description, source_type, total_items, data_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $periode, $description, $source_type, count($decoded), $data_json, $user['id']]
        );

        // Also log sync
        Db::query(
            "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'create_snapshot', ?)",
            [$user['id'], "Membuat snapshot baru: {$name} ({$periode}) dengan " . count($decoded) . " item"]
        );

        Flash::set('success', "Snapshot '{$name}' berhasil disimpan.", 'success');
        redirect('/dashboard/kompetensi');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $snap = Db::fetchOne("SELECT name FROM snapshots WHERE id = ?", [$id]);
            Db::query("DELETE FROM snapshots WHERE id = ?", [$id]);

            if ($snap) {
                Db::query(
                    "INSERT INTO sync_log (user_id, action, notes) VALUES (?, 'delete_snapshot', ?)",
                    [$user['id'], "Menghapus snapshot: {$snap['name']} (#{$id})"]
                );
            }
            Flash::set('success', 'Snapshot berhasil dihapus.', 'success');
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard/kompetensi';
        redirect($referer);
    }
}

redirect('/dashboard');
