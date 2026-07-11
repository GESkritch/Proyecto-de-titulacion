<?php
/**
 * archivo: eliminar_solicitud.php
 * función: Elimina un agendamiento por id.
 * NOTA: Actualmente desactivado para preservar integridad de datos.
 * - Entrada JSON: { id }
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

require_admin_session_json();
ensure_csrf_token(true);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) { http_response_code(400); echo json_encode(['error'=>'id requerido']); exit; }

$db = get_db();
$stmt = $db->prepare('DELETE FROM agendamientos WHERE id = :id');
$stmt->execute([':id'=>$data['id']]);

echo json_encode(['ok'=>true]);

