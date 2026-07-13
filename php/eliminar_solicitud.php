<?php
/**
 * archivo: eliminar_solicitud.php
 * función: Elimina un agendamiento por id.
 * - Solo permitido para superadmin.
 * - Entrada JSON: { id }
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$admin = require_admin_session_json();
$db = get_db();

$stmt = $db->prepare('SELECT is_super FROM admins WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $admin]);
$adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
$isSuper = $adminRow && intval($adminRow['is_super']) === 1;

if (!$isSuper) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo el superadmin puede eliminar solicitudes']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id requerido']);
    exit;
}

$stmt = $db->prepare('DELETE FROM agendamientos WHERE id = :id');
$stmt->execute([':id' => intval($data['id'])]);

echo json_encode(['ok' => true]);

