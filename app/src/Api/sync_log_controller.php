<?php
header('Content-Type: application/json');

if (!Auth::check() || !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden. Hanya administrator yang berhak melakukan sinkronisasi.']);
    exit;
}

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$action = $input['action'] ?? 'api_sync';
$notes  = $input['notes'] ?? 'Sinkronisasi dipicu melalui interface web';

try {
    Db::query(
        "INSERT INTO sync_log (user_id, action, notes) VALUES (?, ?, ?)",
        [$user['id'], $action, $notes]
    );
    echo json_encode(['status' => 'success', 'message' => 'Logged successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
