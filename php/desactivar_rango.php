<?php
/**
 * archivo: desactivar_rango.php
 * función: Desactiva disponibilidades en un rango de fechas.
 * - Entrada JSON: { inicio, fin, tipo? }
 * - Si `tipo` es "Todos" aplica a todas las disponibilidades en el rango.
 * - Si se especifica `tipo` aplica solo a ese tipo de trámite.
 * - Devuelve: { ok: true }
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

$inicio = $data['inicio'];
$fin = $data['fin'];
$tipo = $data['tipo'] ?? 'Todos';

$db = get_db();

// Actualizar estado = 0 para fechas en rango
$stmt = $db->prepare('UPDATE disponibilidad SET estado = 0 WHERE fecha >= :inicio AND fecha <= :fin' . ($tipo !== 'Todos' ? ' AND tipo = :tipo' : ''));
$params = [':inicio'=>$inicio, ':fin'=>$fin];
if ($tipo !== 'Todos') $params[':tipo'] = $tipo;
$stmt->execute($params);

echo json_encode(['ok'=>true]);

