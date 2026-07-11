<?php
/**
 * archivo: actualizar_estado.php
 * función: Actualiza el estado de un agendamiento (confirmado/cancelado/atendido).
 * - Requiere sesión admin.
 * - Entrada JSON: { id, estado }
 * - Actualiza la columna `estado` en tabla `agendamientos`.
 * - Devuelve: { ok: true } o error HTTP 400/401
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

require_admin_session_json();
ensure_csrf_token(true);

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id']) || !isset($data['estado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id y estado requeridos']);
    exit;
}

$db = get_db();
$stmt = $db->prepare('UPDATE agendamientos SET estado = :estado WHERE id = :id');
$stmt->execute([':estado' => $data['estado'], ':id' => $data['id']]);

echo json_encode(['ok' => true]);

