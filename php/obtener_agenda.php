<?php
/**
 * archivo: obtener_agenda.php
 * función: Devuelve mapa de horarios ocupados por agendamientos confirmados.
 * - Estructura: { 'YYYY-MM-DD': ['09:00', '09:20', ...], ... }
 * - Usado por frontend para marcar horas ya ocupadas en calendario.
 * - Registra errores en archivo de log para debugging.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

try {
    $db = get_db();
    $ocupadas = [];

    $stmt = $db->prepare('SELECT fecha, hora FROM agendamientos WHERE estado = "confirmado"');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ocupadas[$row['fecha']][] = $row['hora'];
    }

    echo json_encode($ocupadas);
} catch (Throwable $e) {
    // registrar error en archivo para debugging
    $logDir = __DIR__ . '/../data';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $msg = date('c') . " - obtener_agenda error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents($logDir . '/php_errors.log', $msg, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

?>
