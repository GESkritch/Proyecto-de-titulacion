<?php
/**
 * archivo: delete_admin.php
 * función: Elimina una cuenta de administrador existente.
 * - Requiere que el usuario actual sea superadmin.
 * - Previene eliminación de cuentas superadmin y de la cuenta actual.
 * - Entrada JSON: { username }
 * - Devuelve: { ok: true } o error HTTP 400/401/403
 */
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$target = $data['username'] ?? null;
if (!$target) { http_response_code(400); echo json_encode(['error'=>'username requerido']); exit; }

$current = $_SESSION['admin'] ?? null;
if (!$current) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$db = get_db();
$isAllowed = false;
$chk = $db->prepare('SELECT is_super FROM admins WHERE username = :u LIMIT 1');
$chk->execute([':u' => $current]);
$rchk = $chk->fetch(PDO::FETCH_ASSOC);
if ($rchk && intval($rchk['is_super']) === 1) {
    $isAllowed = true;
} else {
    // revisar tabla superadmin
    $stmt2 = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='superadmin'");
    $stmt2->execute();
    $exists = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $chk2 = $db->prepare('SELECT COUNT(1) as c FROM superadmin WHERE username = :u');
        $chk2->execute([':u' => $current]);
        $r2 = $chk2->fetch(PDO::FETCH_ASSOC);
        if ($r2 && intval($r2['c']) > 0) $isAllowed = true;
    }
}

if (!$isAllowed) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); exit; }

// No permitir borrar superadmins
$stmt = $db->prepare('SELECT is_super FROM admins WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $target]);
$rt = $stmt->fetch(PDO::FETCH_ASSOC);
if ($rt && intval($rt['is_super']) === 1) {
    http_response_code(403);
    echo json_encode(['error'=>'No se puede borrar un superadmin']);
    exit;
}

// No permitir que el superadmin se borre a sí mismo
if ($target === $current) {
    http_response_code(403);
    echo json_encode(['error'=>'No se puede borrar la cuenta actual']);
    exit;
}

$del = $db->prepare('DELETE FROM admins WHERE username = :u');
$del->execute([':u' => $target]);

echo json_encode(['ok' => true]);
?>
