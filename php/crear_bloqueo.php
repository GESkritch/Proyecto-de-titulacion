<?php
/**
 * archivo: crear_bloqueo.php
 * función: Crea un bloqueo temporal (TTL 15 min) para reservar una hora durante confirmación.
 * - Previene colisiones con otros bloqueos y agendamientos confirmados.
 * - Entrada JSON: { fecha, hora, rut? }
 * - Limpia bloqueos vencidos antes de verificar colisiones.
 * - Respuesta: { ok: true } o HTTP 409 si hay colisión
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['fecha']) || !isset($data['hora'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

$fecha = trim((string)$data['fecha']);
$hora = trim((string)$data['hora']);
$rut = isset($data['rut']) ? normalize_rut_digits($data['rut']) : null;
$now = time();
$ttl = 15 * 60;

$db = get_db();

$stmt = $db->prepare('DELETE FROM bloqueos_temporales WHERE expires_at < :now');
$stmt->execute([':now' => $now]);

$stmt = $db->prepare('SELECT COUNT(1) as c FROM bloqueos_temporales WHERE fecha = :fecha AND hora = :hora');
$stmt->execute([':fecha' => $fecha, ':hora' => $hora]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && intval($row['c']) > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Hora bloqueada temporalmente']);
    exit;
}

$stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE fecha = :fecha AND hora = :hora AND estado = "confirmado"');
$stmt->execute([':fecha' => $fecha, ':hora' => $hora]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && intval($row['c']) > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Horario ya ocupado']);
    exit;
}

$token = bin2hex(random_bytes(12));
$expires = $now + $ttl;
$stmt = $db->prepare('INSERT INTO bloqueos_temporales (rut, fecha, hora, token, expires_at) VALUES (:rut, :fecha, :hora, :token, :expires)');
$stmt->execute([':rut' => $rut, ':fecha' => $fecha, ':hora' => $hora, ':token' => $token, ':expires' => $expires]);

echo json_encode(['ok' => true, 'token' => $token, 'expires_at' => $expires]);

?>
