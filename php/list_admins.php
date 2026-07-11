<?php
/**
 * archivo: list_admins.php
 * función: Devuelve lista de todos los administradores registrados.
 * - Requiere que el usuario actual sea superadmin.
 * - Devuelve JSON array: [ { username, is_super }, ... ]
 */
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');

// Solo superadmin puede listar admins
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

$stmt = $db->query('SELECT username, is_super FROM admins ORDER BY username');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
?>
