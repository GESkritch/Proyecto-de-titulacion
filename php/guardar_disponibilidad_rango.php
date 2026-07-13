<?php
/**
 * archivo: guardar_disponibilidad_rango.php
 * función: Crea o actualiza disponibilidades por rango de fechas.
 * - Entrada JSON: { inicio, fin, horaInicio, horaFin, bloque, cupos, tipo }
 * - Para cada fecha en el rango crea/actualiza fila en tabla `disponibilidad`.
 * - Marca todas las fechas como `estado = 1` (activas).
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

/*
  php/guardar_disponibilidad_rango.php
  - Inserta o actualiza disponibilidades para un rango de fechas.
  - Entrada JSON: { inicio, fin, horaInicio, horaFin, bloque, cupos, tipo }.
  - Para cada fecha en el rango crea o actualiza la fila en `disponibilidad` y la marca `estado = 1`.
  - Uso típico: panel admin -> crear rango de fechas disponibles para un trámite.
*/

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$inicio = new DateTime($data['inicio']);
$fin = new DateTime($data['fin']);
$horaInicio = $data['horaInicio'];
$horaFin = $data['horaFin'];
$bloque = intval($data['bloque'] ?? 20);
$cupos = intval($data['cupos'] ?? 30);
$tipo = $data['tipo'] ?? 'Ambos';

$db = get_db();

$stmtCheck = $db->prepare('SELECT id FROM disponibilidad WHERE fecha = :fecha LIMIT 1');
$stmtInsert = $db->prepare('INSERT INTO disponibilidad (fecha, hora_inicio, hora_fin, duracion_bloque, max_cupos, tipo, estado)
    VALUES (:fecha, :hi, :hf, :block, :cupos, :tipo, 1)');
$stmtUpdate = $db->prepare('UPDATE disponibilidad SET hora_inicio = :hi, hora_fin = :hf, duracion_bloque = :block, max_cupos = :cupos, tipo = :tipo, estado = 1 WHERE fecha = :fecha');

while ($inicio <= $fin) {
    $fecha = $inicio->format('Y-m-d');
    $stmtCheck->execute([':fecha' => $fecha]);
    $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        $stmtUpdate->execute([':fecha' => $fecha, ':hi' => $horaInicio, ':hf' => $horaFin, ':block' => $bloque, ':cupos' => $cupos, ':tipo' => $tipo]);
    } else {
        $stmtInsert->execute([':fecha' => $fecha, ':hi' => $horaInicio, ':hf' => $horaFin, ':block' => $bloque, ':cupos' => $cupos, ':tipo' => $tipo]);
    }

    $inicio->modify('+1 day');
}

echo json_encode(['ok'=>true]);

