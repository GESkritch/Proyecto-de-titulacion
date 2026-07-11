<?php
/**
 * archivo: diag_agendamientos.php
 * función: Endpoint de diagnóstico para inspección de agendamientos.
 * - Devuelve las últimas 200 filas de tabla `agendamientos`.
 * - Uso: Admin panel para debugging rápido.
 * - Devuelve: { ok: true, count, rows } o { ok: false, error }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

try {
    $db = get_db();
    $stmt = $db->query('SELECT * FROM agendamientos ORDER BY created_at DESC LIMIT 200');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true, 'count'=>count($rows), 'rows'=>$rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}

?>
