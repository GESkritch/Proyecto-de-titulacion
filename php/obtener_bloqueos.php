<?php
/**
 * archivo: obtener_bloqueos.php
 * función: Devuelve mapa de bloqueos temporales activos.
 * - Limpia bloqueos vencidos.
 * - Devuelve JSON: { 'YYYY-MM-DD': ['09:00', '09:20', ...], ... }
 * - Usado por frontend para marcar horas bloqueadas en calendario.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$db = get_db();
$now = time();

// Limpiar vencidos
$db->prepare('DELETE FROM bloqueos_temporales WHERE expires_at < :now')->execute([':now'=>$now]);

$stmt = $db->prepare('SELECT fecha, hora FROM bloqueos_temporales');
$stmt->execute();
$bloqueos = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $bloqueos[$r['fecha']][] = $r['hora'];
}

echo json_encode($bloqueos);

?>
