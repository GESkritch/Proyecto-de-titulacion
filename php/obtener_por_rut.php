<?php
/**
 * archivo: obtener_por_rut.php
 * función: Obtiene el último agendamiento confirmado para un RUT.
 * - Entrada POST JSON: { rut }
 * - Normaliza RUT a solo dígitos para búsqueda flexible.
 * - Devuelve: { ok: true, row: {...} } o { ok: false } si no hay cita confirmada.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

 $raw = json_decode(file_get_contents('php://input'), true);
 $rut = $raw['rut'] ?? null;
 if (!$rut) { http_response_code(400); echo json_encode(['error'=>'rut requerido']); exit; }

// Normalizar RUT: buscar por los dígitos solamente
$rut = preg_replace('/\D/', '', trim((string)$rut));

$db = get_db();
$stmt = $db->prepare('SELECT * FROM agendamientos WHERE rut = :rut AND estado = "confirmado" ORDER BY created_at DESC LIMIT 1');
$stmt->execute([':rut'=>$rut]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(['ok'=>true,'row'=>$row]);
} else {
    echo json_encode(['ok'=>false]);
}

?>
