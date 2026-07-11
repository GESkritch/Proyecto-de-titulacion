<?php
/**
 * archivo: limpiar_bloqueos.php
 * función: Limpia bloqueos temporales expirados.
 * - Elimina registros con `expires_at` menor al timestamp actual.
 * - Puede ser ejecutado manualmente o por cron job.
 * - Sin salida HTTP por defecto (utilidad silenciosa).
 */
require_once __DIR__ . '/db.php';

$db = get_db();
$now = time();
$stmt = $db->prepare('DELETE FROM bloqueos_temporales WHERE expires_at < :now');
$stmt->execute([':now'=>$now]);

