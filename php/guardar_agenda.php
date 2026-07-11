<?php
/**
 * archivo: guardar_agenda.php
 * función: Valida y persiste un agendamiento confirmado.
 * - Verifica disponibilidad, cupos, bloqueos y evita duplicados.
 * - Normaliza campos flexiblemente desde múltiples posibles nombres.
 * - Registra requests y errores en log para debugging.
 * - Entrada JSON: { fecha, horaInicio, horaFin?, rut, nombres, apellidos, telefono, correo, tipo }
 * - Devuelve: { ok: true, id } o error HTTP 400/409/500
 */
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// DEBUG: log request payload to help trace missing fields
$logDir = __DIR__ . '/../data';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents($logDir . '/guardar_agenda_requests.log', date('c') . " REQUEST: " . json_encode($data) . "\n", FILE_APPEND);

// Normalizar y extraer campos desde el payload
$fecha = isset($data['fecha']) ? trim($data['fecha']) : null;
$horaInicio = isset($data['horaInicio']) ? trim($data['horaInicio']) : (isset($data['hora']) ? trim($data['hora']) : null);
$horaFin = isset($data['horaFin']) ? trim($data['horaFin']) : null;
$rutRaw = isset($data['rut']) ? normalize_rut($data['rut']) : null;
$rut = isset($data['rut']) ? normalize_rut_digits($data['rut']) : null;
$token = isset($data['token']) ? trim((string)$data['token']) : null;
$id = isset($data['id']) && $data['id'] !== '' ? intval($data['id']) : null;
$nombres = '';
$apellidos = '';
$telefono = '';
$correo = '';
$tipo = null;

// posibles nombres de campos que pueden venir desde distintos frontends
$fieldMap = [
    'nombres' => ['nombres','nombre','name'],
    'apellidos' => ['apellidos','apellido','lastname'],
    'telefono' => ['telefono','tel','phone'],
    'correo' => ['correo','email'],
    'tipo' => ['tipo','tipo_tramite','tipoTramite']
];

foreach ($fieldMap['nombres'] as $k) if (isset($data[$k]) && trim((string)$data[$k]) !== '') { $nombres = trim((string)$data[$k]); break; }
foreach ($fieldMap['apellidos'] as $k) if (isset($data[$k]) && trim((string)$data[$k]) !== '') { $apellidos = trim((string)$data[$k]); break; }
foreach ($fieldMap['telefono'] as $k) if (isset($data[$k]) && trim((string)$data[$k]) !== '') { $telefono = trim((string)$data[$k]); break; }
foreach ($fieldMap['correo'] as $k) if (isset($data[$k]) && trim((string)$data[$k]) !== '') { $correo = trim((string)$data[$k]); break; }
foreach ($fieldMap['tipo'] as $k) if (isset($data[$k]) && trim((string)$data[$k]) !== '') { $tipo = trim((string)$data[$k]); break; }

// Normalizar posibles valores mal ubicados (heurística)
if ($nombres === '' && isset($data['name'])) $nombres = $data['name'];
if ($apellidos === '' && isset($data['lastname'])) $apellidos = $data['lastname'];
// caso donde el frontend pudo haber enviado telefono en el campo 'correo'
if ($telefono === '' && isset($data['email']) && preg_match('/^9\d{8}$/', $data['email'])) {
    $telefono = $data['email'];
}
// si correo no tiene '@' pero telefono contiene '@', swap
if ($correo === '' && strpos($telefono, '@') !== false) {
    $correo = $telefono;
    $telefono = '';
}

// Log resolved variables
file_put_contents($logDir . '/guardar_agenda_requests.log', date('c') . " RESOLVED: " . json_encode(['fecha'=>$fecha,'horaInicio'=>$horaInicio,'rut'=>$rut,'nombres'=>$nombres,'apellidos'=>$apellidos,'telefono'=>$telefono,'correo'=>$correo,'tipo'=>$tipo]) . "\n", FILE_APPEND);

if (!$nombres || !$apellidos) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombres y apellidos requeridos']);
    exit;
}

if (!$fecha || !$horaInicio || !$rutRaw) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros obligatorios']);
    exit;
}

if (!is_valid_rut($rutRaw)) {
    http_response_code(400);
    echo json_encode(['error' => 'RUT inválido']);
    exit;
}

if ($telefono !== '' && !is_valid_phone($telefono)) {
    http_response_code(400);
    echo json_encode(['error' => 'Teléfono inválido']);
    exit;
}

if ($correo !== '' && !is_valid_email($correo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Correo inválido']);
    exit;
}

$db = get_db();
$now = time();

$existingAppointment = null;
if ($id !== null) {
    // Solo admins pueden actualizar citas existentes desde este endpoint
    require_admin_session_json();

    $stmt = $db->prepare('SELECT * FROM agendamientos WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $existingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingAppointment) {
        http_response_code(400);
        echo json_encode(['error' => 'Cita no encontrada']);
        exit;
    }
    if ($existingAppointment['rut'] !== $rut) {
        http_response_code(400);
        echo json_encode(['error' => 'El ID de cita no coincide con el RUT']);
        exit;
    }
    if ($existingAppointment['estado'] !== 'confirmado') {
        http_response_code(400);
        echo json_encode(['error' => 'Solo se pueden modificar citas confirmadas']);
        exit;
    }
}

// Limpiar bloqueos vencidos
$db->prepare('DELETE FROM bloqueos_temporales WHERE expires_at < :now')->execute([':now'=>$now]);

// Validación: verificar disponibilidad para la fecha y que la hora sea un bloque válido
$stmt = $db->prepare('SELECT * FROM disponibilidad WHERE fecha = :fecha AND estado = 1 LIMIT 1');
$stmt->execute([':fecha'=>$fecha]);
$disp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$disp) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay disponibilidad para la fecha']);
    exit;
}

// Verificar que horaInicio encaje en los bloques
$hIni = explode(':', $disp['hora_inicio']);
$hFin = explode(':', $disp['hora_fin']);
$start = intval($hIni[0]) * 60 + intval($hIni[1]);
$end = intval($hFin[0]) * 60 + intval($hFin[1]);
$block = intval($disp['duracion_bloque']);

$requested = explode(':', $horaInicio);
$reqMinutes = intval($requested[0]) * 60 + intval($requested[1]);
$valid = false;
for ($t = $start; $t + $block <= $end; $t += $block) {
    if ($t === $reqMinutes) { $valid = true; break; }
}
if (!$valid) {
    http_response_code(400);
    echo json_encode(['error' => 'Hora no permitida por disponibilidad']);
    exit;
}

// Verificar cupos: cuántos agendamientos confirmados ya hay en esa fecha/hora
if ($id !== null) {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE fecha = :fecha AND hora = :hora AND estado = "confirmado" AND id != :id');
    $stmt->execute([':fecha'=>$fecha, ':hora'=>$horaInicio, ':id'=>$id]);
} else {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE fecha = :fecha AND hora = :hora AND estado = "confirmado"');
    $stmt->execute([':fecha'=>$fecha, ':hora'=>$horaInicio]);
}
$c = intval($stmt->fetch(PDO::FETCH_ASSOC)['c']);

$stmt = $db->prepare('SELECT COUNT(1) as c FROM bloqueos_temporales WHERE fecha = :fecha AND hora = :hora');
$stmt->execute([':fecha'=>$fecha, ':hora'=>$horaInicio]);
$b = intval($stmt->fetch(PDO::FETCH_ASSOC)['c']);

if (($c + $b) >= intval($disp['max_cupos'])) {
    http_response_code(409);
    echo json_encode(['error' => 'Cupos agotados']);
    exit;
}

// Regla: un RUT no puede tener 2 horas activas del mismo trámite (confirmado)
if ($tipo) {
    if ($id !== null) {
        $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND tipo_tramite = :tipo AND estado = "confirmado" AND id != :id');
        $stmt->execute([':rut'=>$rut, ':tipo'=>$tipo, ':id'=>$id]);
    } else {
        $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND tipo_tramite = :tipo AND estado = "confirmado"');
        $stmt->execute([':rut'=>$rut, ':tipo'=>$tipo]);
    }
    if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'El RUT ya tiene una hora activa para este trámite']);
        exit;
    }
}

// Regla adicional: un RUT no puede tener ninguna cita "confirmado" activa.
// Esto asegura a nivel servidor que no se crean duplicados activos aunque el frontend falle.
if ($id !== null) {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND estado = "confirmado" AND id != :id');
    $stmt->execute([':rut'=>$rut, ':id'=>$id]);
} else {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND estado = "confirmado"');
    $stmt->execute([':rut'=>$rut]);
}
if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'El RUT ya tiene una cita activa (confirmada)']);
    exit;
}

// Validar token de bloqueo si se proporciona
if ($token !== null && $token !== '') {
    $stmt = $db->prepare('SELECT id FROM bloqueos_temporales WHERE fecha = :fecha AND hora = :hora AND token = :token AND expires_at >= :now LIMIT 1');
    $stmt->execute([':fecha'=>$fecha, ':hora'=>$horaInicio, ':token'=>$token, ':now'=>$now]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['error' => 'Token de bloqueo inválido o expirado']);
        exit;
    }
}

// Evitar doble envío: ver si ya existe fila idéntica
if ($id !== null) {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND fecha = :fecha AND hora = :hora AND tipo_tramite = :tipo AND id != :id');
    $stmt->execute([':rut'=>$rut, ':fecha'=>$fecha, ':hora'=>$horaInicio, ':tipo'=>$tipo, ':id'=>$id]);
} else {
    $stmt = $db->prepare('SELECT COUNT(1) as c FROM agendamientos WHERE rut = :rut AND fecha = :fecha AND hora = :hora AND tipo_tramite = :tipo');
    $stmt->execute([':rut'=>$rut, ':fecha'=>$fecha, ':hora'=>$horaInicio, ':tipo'=>$tipo]);
}
if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Solicitud ya registrada']);
    exit;
}

// Insertar o actualizar agendamiento
// Asegurar existencia de registro en tabla clientes y obtener cliente_id
$clienteId = null;
$cstmt = $db->prepare('SELECT id FROM clientes WHERE rut = :rut LIMIT 1');
$cstmt->execute([':rut' => $rut]);
$c = $cstmt->fetch(PDO::FETCH_ASSOC);
if ($c && isset($c['id'])) {
    $clienteId = $c['id'];
    // Intentar actualizar datos del cliente si vienen nuevos valores
    $up = $db->prepare('UPDATE clientes SET nombre = :nombre, apellido = :apellido, correo = :correo, telefono = :telefono WHERE id = :id');
    $up->execute([':nombre'=>$nombres, ':apellido'=>$apellidos, ':correo'=>$correo, ':telefono'=>$telefono, ':id'=>$clienteId]);
} else {
    $insC = $db->prepare('INSERT INTO clientes (rut, nombre, apellido, correo, telefono, created_at) VALUES (:rut, :nombre, :apellido, :correo, :telefono, :created)');
    $insC->execute([':rut'=>$rut, ':nombre'=>$nombres, ':apellido'=>$apellidos, ':correo'=>$correo, ':telefono'=>$telefono, ':created'=>$now]);
    $clienteId = $db->lastInsertId();
}

// Insertar o actualizar agendamiento incluyendo cliente_id
if ($id !== null) {
    $stmt = $db->prepare('UPDATE agendamientos SET rut = :rut, nombre = :nombre, apellido = :apellido, correo = :correo, telefono = :telefono, fecha = :fecha, hora = :hora, tipo_tramite = :tipo WHERE id = :id');
    $stmt->execute([
        ':rut'=>$rut, ':nombre'=>$nombres, ':apellido'=>$apellidos, ':correo'=>$correo,
        ':telefono'=>$telefono, ':fecha'=>$fecha, ':hora'=>$horaInicio, ':tipo'=>$tipo, ':id'=>$id
    ]);

    $lastId = $id;
    file_put_contents($logDir . '/guardar_agenda_updates.log', date('c') . " UPDATE id={$lastId} " . json_encode(['id'=>$lastId,'rut'=>$rut,'nombre'=>$nombres,'apellido'=>$apellidos,'correo'=>$correo,'telefono'=>$telefono,'fecha'=>$fecha,'hora'=>$horaInicio,'tipo'=>$tipo]) . "\n", FILE_APPEND);
} else {
    $stmt = $db->prepare('INSERT INTO agendamientos (rut, nombre, apellido, correo, telefono, fecha, hora, tipo_tramite, estado, created_at, cliente_id)
        VALUES (:rut, :nombre, :apellido, :correo, :telefono, :fecha, :hora, :tipo, "confirmado", :created, :cliente_id)');
    $stmt->execute([
        ':rut'=>$rut, ':nombre'=>$nombres, ':apellido'=>$apellidos, ':correo'=>$correo,
        ':telefono'=>$telefono, ':fecha'=>$fecha, ':hora'=>$horaInicio, ':tipo'=>$tipo, ':created'=>$now, ':cliente_id'=>$clienteId
    ]);

    $lastId = $db->lastInsertId();
    file_put_contents($logDir . '/guardar_agenda_inserts.log', date('c') . " INSERT id={$lastId} " . json_encode(['id'=>$lastId,'rut'=>$rut,'nombre'=>$nombres,'apellido'=>$apellidos,'correo'=>$correo,'telefono'=>$telefono,'fecha'=>$fecha,'hora'=>$horaInicio,'tipo'=>$tipo]) . "\n", FILE_APPEND);
}

// Si había un bloqueo temporal del mismo rut en esa fecha/hora, eliminarlo
$stmt = $db->prepare('DELETE FROM bloqueos_temporales WHERE fecha = :fecha AND hora = :hora AND (rut = :rut OR token = :token)');
$stmt->execute([':fecha'=>$fecha, ':hora'=>$horaInicio, ':rut'=>$rut, ':token'=>$token]);

echo json_encode(['ok' => true, 'id' => intval($lastId)]);

?>
