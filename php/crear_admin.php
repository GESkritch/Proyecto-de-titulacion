<?php
/**
 * archivo: crear_admin.php
 * función: Crea una nueva cuenta de administrador.
 * - Requiere que el usuario actual sea superadmin.
 * - Valida que el usuario no exista previamente.
 * - Entrada JSON: { username, password }
 * - Devuelve: { ok: true } o error HTTP 400/401/403/409
 */
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');

// Solo administradores autenticados pueden crear otros admins
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

$username = trim((string)$data['username']);
$password = (string)$data['password'];

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'username y password requeridos']);
    exit;
}

$db = get_db();
// Verificar que el admin actual sea superadmin (en admins.is_super) o esté en la tabla superadmin
$current = $_SESSION['admin'];
$isAllowed = false;
$chk = $db->prepare('SELECT is_super FROM admins WHERE username = :u LIMIT 1');
$chk->execute([':u' => $current]);
$rchk = $chk->fetch(PDO::FETCH_ASSOC);
if ($rchk && intval($rchk['is_super']) === 1) {
    $isAllowed = true;
} else {
    // verificar tabla superadmin
    $chk2 = $db->prepare('SELECT COUNT(1) as c FROM superadmin WHERE username = :u');
    $chk2->execute([':u' => $current]);
    $r2 = $chk2->fetch(PDO::FETCH_ASSOC);
    if ($r2 && intval($r2['c']) > 0) $isAllowed = true;
}
if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado: solo superadmin puede crear administradores']);
    exit;
}
// Verificar existencia
$stmt = $db->prepare('SELECT COUNT(1) as c FROM admins WHERE username = :u');
$stmt->execute([':u' => $username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && intval($row['c']) > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Usuario ya existe']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (:u, :h)');
$ins->execute([':u' => $username, ':h' => $hash]);

echo json_encode(['ok' => true]);

?>
