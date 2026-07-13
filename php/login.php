<?php
/**
 * archivo: login.php
 * función: Autentica un usuario administrador.
 * - Valida credenciales contra tabla `admins` y `superadmin`.
 * - Establece sesión con `$_SESSION['admin']` si la autenticación es exitosa.
 * - Devuelve JSON: { ok: true/false }
 */
require_once __DIR__ . '/db.php';
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['usuario']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$db = get_db();
// Primero buscar en tabla admins
$stmt = $db->prepare('SELECT username, password_hash FROM admins WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $data['usuario']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && password_verify($data['password'], $row['password_hash'])) {
    $_SESSION['admin'] = $row['username'];
    $_SESSION['is_super'] = intval($row['is_super'] ?? 0) === 1;
    echo json_encode(['ok' => true]);
    exit;
}

// Si no encontró en admins o la contraseña no coincide, verificar tabla superadmin
if (table_exists($db, 'superadmin')) {
    $stmt2 = $db->prepare('SELECT username, password_hash FROM superadmin WHERE username = :u LIMIT 1');
    $stmt2->execute([':u' => $data['usuario']]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($row2 && password_verify($data['password'], $row2['password_hash'])) {
        // Usuario superadmin autenticado
        $_SESSION['admin'] = $row2['username'];
        // marcar flag opcional
        $_SESSION['is_super'] = true;
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['ok' => false]);
