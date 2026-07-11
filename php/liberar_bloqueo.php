<?php
/**
 * archivo: liberar_bloqueo.php
 * función: Elimina un bloqueo temporal específico.
 * - Entrada JSON: { fecha, hora, rut? }
 * - Si se indica `rut`, solo elimina el bloqueo de ese usuario.
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

$fecha = $data['fecha'] ?? null;
$hora = $data['hora'] ?? null;
$rut = $data['rut'] ?? null;
if ($rut !== null) {
  // Normalizar rut entrante
  $rut = preg_replace('/\D/', '', trim((string)$rut));
}

$db = get_db();
$sql = 'DELETE FROM bloqueos_temporales WHERE fecha = :fecha AND hora = :hora';
$params = [':fecha'=>$fecha, ':hora'=>$hora];
if ($rut !== null) {
    $sql .= ' AND rut = :rut';
    $params[':rut'] = $rut;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);

echo json_encode(['ok'=>true]);

