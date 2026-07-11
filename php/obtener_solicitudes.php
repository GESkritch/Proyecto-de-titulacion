<?php
/**
 * archivo: obtener_solicitudes.php
 * función: Devuelve lista de todos los agendamientos registrados.
 * - Acceso: Público y Admin (usado en admin panel).
 * - Normaliza nombres de columnas inconsistentes.
 * - Devuelve JSON array: [ { id, fecha, hora_inicio, hora_fin, nombres, apellidos, rut, telefono, correo, tipo_tramite, estado }, ... ]
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$db = get_db();

/*
 DEVUELVE SIEMPRE:
 id, fecha, hora_inicio, hora_fin,
 nombres, apellidos, rut, telefono,
 correo, tipo_tramite, estado
*/

$stmt = $db->query("SELECT * FROM agendamientos ORDER BY created_at DESC");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function add_minutes($time, $mins) {
  if (!$time || strpos($time, ':') === false) return null;

  $parts = explode(':', $time);
  if (count($parts) < 2) return null;

  $h = (int)$parts[0];
  $m = (int)$parts[1];

  $total = ($h * 60) + $m + (int)$mins;
  return sprintf('%02d:%02d', floor($total / 60) % 24, $total % 60);
}

$out = [];

foreach ($rows as $r) {

    // helper to pick first non-empty from several possible column names
    $pick = function($keys) use ($r) {
        foreach ($keys as $k) {
            if (isset($r[$k]) && trim((string)$r[$k]) !== '') return trim((string)$r[$k]);
        }
        return '';
    };

    $hora_inicio = $pick(['hora_inicio','hora','hora_inicio ']);
    $hora_fin = $pick(['hora_fin']);
    if ($hora_inicio && !$hora_fin) $hora_fin = add_minutes($hora_inicio, 20);

    $nombres = $pick(['nombres','nombre']);
    $apellidos = $pick(['apellidos','apellido']);
    $rut = $pick(['rut']);
    $telefono = $pick(['telefono','tel','phone']);
    $correo = $pick(['correo','email']);
    $tipo_tramite = $pick(['tipo_tramite','tipo']);
    $estado = $pick(['estado']);

    // heuristics: if telefono contains '@' it's likely the correo
    if ($telefono !== '' && strpos($telefono, '@') !== false && $correo === '') {
        $correo = $telefono;
        $telefono = '';
    }
    // if correo doesn't look like email but tipo_tramite contains '@', swap
    if ($correo === '' && $tipo_tramite !== '' && strpos($tipo_tramite, '@') !== false) {
        $correo = $tipo_tramite;
        $tipo_tramite = '';
    }

    // final fallback to raw columns if still empty
    if ($nombres === '' && isset($r['nombre'])) $nombres = trim((string)$r['nombre']);
    if ($apellidos === '' && isset($r['apellido'])) $apellidos = trim((string)$r['apellido']);

    $out[] = [
        'id' => isset($r['id']) ? $r['id'] : null,
        'fecha' => isset($r['fecha']) ? $r['fecha'] : '',
        'hora_inicio' => $hora_inicio,
        'hora_fin' => $hora_fin,
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'rut' => $rut,
        'telefono' => $telefono,
        'correo' => $correo,
        'tipo_tramite' => $tipo_tramite,
        'estado' => $estado
    ];
}

echo json_encode($out);
exit;
