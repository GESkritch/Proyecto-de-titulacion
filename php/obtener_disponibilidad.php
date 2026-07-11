<?php
/**
 * archivo: obtener_disponibilidad.php
 * función: Devuelve disponibilidades activas para el calendario.
 * - Si se pasa ?fecha=YYYY-MM-DD devuelve un objeto con esa fecha específica.
 * - Si no hay parámetro, devuelve mapa { fecha => config, ... } de todas las fechas.
 * - Mapea columnas DB a formato esperado por frontend (inicio/fin/bloque_min/cupos/tipo).
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

/*
  php/obtener_disponibilidad.php
  - Devuelve disponibilidades activas.
  - Si se pasa `?fecha=YYYY-MM-DD` devuelve objeto con claves esperadas por el frontend (inicio/fin/bloque_min/cupos/tipo).
  - Si no hay `fecha` devuelve un mapa `fecha => config` para todas las fechas activas.
*/

$db = get_db();

$fecha = $_GET['fecha'] ?? null;
// Helper to map DB columns to frontend keys expected by calendar.js
function map_row($r) {
    if (!$r) return null;
    return [
        'fecha' => $r['fecha'],
        'inicio' => $r['hora_inicio'],
        'fin' => $r['hora_fin'],
        'bloque_min' => isset($r['duracion_bloque']) ? intval($r['duracion_bloque']) : (isset($r['bloque_min']) ? intval($r['bloque_min']) : 20),
        'cupos' => isset($r['max_cupos']) ? intval($r['max_cupos']) : (isset($r['cupos']) ? intval($r['cupos']) : 30),
        'tipo' => $r['tipo'] ?? 'Ambos',
        'activo' => isset($r['estado']) ? ($r['estado'] == 1 ? '1' : '0') : '1'
    ];
}

if ($fecha) {
    $stmt = $db->prepare('SELECT * FROM disponibilidad WHERE fecha = :fecha AND estado = 1 LIMIT 1');
    $stmt->execute([':fecha'=>$fecha]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $mapped = map_row($row);
    echo json_encode($mapped ? $mapped : new stdClass());
    exit;
}

$stmt = $db->query('SELECT * FROM disponibilidad WHERE estado = 1');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
foreach ($rows as $r) {
    $mapped = map_row($r);
    if ($mapped) $out[$mapped['fecha']] = $mapped;
}

echo json_encode($out);

?>
