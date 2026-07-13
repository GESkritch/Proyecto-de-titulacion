<?php
/**
 * archivo: marcar_atendido.php
 * función: Marca un agendamiento como 'atendido'.
 * - Entrada JSON: { id }
 * - Actualiza columna `estado` a 'atendido' en tabla `agendamientos`.
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

require_admin_session_json();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) { http_response_code(400); echo json_encode(['error'=>'id requerido']); exit; }

$db = get_db();
$selectStmt = $db->prepare('SELECT * FROM agendamientos WHERE id = :id LIMIT 1');
$selectStmt->execute([':id' => intval($data['id'])]);
$row = $selectStmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['error'=>'Solicitud no encontrada']); exit; }

$stmt = $db->prepare('UPDATE agendamientos SET estado = "atendido" WHERE id = :id');
$stmt->execute([':id' => intval($data['id'])]);

if ($stmt->rowCount() === 1) {
    sync_persona_lista($db, $row, 'atendido');
}

echo json_encode(['ok'=>true]);

