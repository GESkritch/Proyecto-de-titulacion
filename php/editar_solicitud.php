<?php
/**
 * archivo: editar_solicitud.php
 * función: Actualiza campos de un agendamiento existente.
 * - Acceso: Admin panel (requiere sesión).
 * - Entrada JSON: { id, fecha, hora_inicio, correo, tipo, telefono, nombres, apellidos }
 * - Devuelve: { ok: true } o error HTTP 400
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

require_admin_session_json();
ensure_csrf_token(true);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) { http_response_code(400); echo json_encode(['error'=>'id requerido']); exit; }

$db = get_db();
$stmt = $db->prepare('UPDATE agendamientos SET fecha = :fecha, hora = :hora, correo = :correo, tipo_tramite = :tipo, telefono = :telefono, nombre = :nombre, apellido = :apellido WHERE id = :id');
$stmt->execute([':fecha'=>$data['fecha'], ':hora'=>$data['hora_inicio'], ':correo'=>$data['correo'], ':tipo'=>$data['tipo'], ':telefono'=>$data['telefono'], ':nombre'=>$data['nombres'], ':apellido'=>$data['apellidos'], ':id'=>$data['id']]);

echo json_encode(['ok'=>true]);

