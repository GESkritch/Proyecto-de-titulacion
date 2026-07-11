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
ensure_csrf_token(true);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) { http_response_code(400); echo json_encode(['error'=>'id requerido']); exit; }

$db = get_db();
$stmt = $db->prepare('UPDATE agendamientos SET estado = "atendido" WHERE id = :id');
$stmt->execute([':id'=>$data['id']]);

echo json_encode(['ok'=>true]);

