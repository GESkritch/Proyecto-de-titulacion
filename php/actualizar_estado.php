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

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id']) || !isset($data['estado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id y estado requeridos']);
    exit;
}

$estado = trim((string)$data['estado']);
if (!in_array($estado, ['confirmado', 'cancelado', 'atendido'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'estado inválido']);
    exit;
}

$db = get_db();
$selectStmt = $db->prepare('SELECT * FROM agendamientos WHERE id = :id LIMIT 1');
$selectStmt->execute([':id' => intval($data['id'])]);
$row = $selectStmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Solicitud no encontrada']);
    exit;
}

$stmt = $db->prepare('UPDATE agendamientos SET estado = :estado WHERE id = :id');
$stmt->execute([':estado' => $estado, ':id' => intval($data['id'])]);

if ($stmt->rowCount() === 1 && $estado === 'atendido') {
    sync_persona_lista($db, $row, $estado);
}

echo json_encode(['ok' => true]);

