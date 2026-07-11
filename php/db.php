<?php
/**
 * archivo: db.php
 * función: Inicializa y devuelve conexión PDO a base de datos SQLite.
 * - Ejecuta migraciones automáticas para crear tablas si no existen.
 * - Configura WAL y busy_timeout para mejorar concurrencia.
 *
 * Funciones principales:
 * - get_db(): Retorna conexión PDO singleton (con optimizaciones de concurrencia).
 * - migrate(): Crea tablas (disponibilidad, agendamientos, clientes, bloqueos_temporales, admins).
 *
 * Incluido por todos los endpoints PHP del proyecto.
 */

/**
 * get_db()
 * Retorna la conexión PDO singleton a la base de datos SQLite.
 * - Crea el directorio /data si no existe.
 * - Habilita foreign keys y modo WAL para mejor concurrencia.
 * - Ejecuta migrate() automáticamente para setup inicial.
 * @return PDO
 */
function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = __DIR__ . "/../data";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . "/database.sqlite";

    $pdo = new PDO("sqlite:" . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');
    // Mejorar concurrencia: usar WAL y timeout para evitar "database is locked"
    try {
        $pdo->exec('PRAGMA journal_mode = WAL');
    } catch (Exception $e) {
        // ignore if not supported
    }
    try {
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } catch (Exception $e) {
        // ignore
    }

    return $pdo;
}

function normalize_rut($rut) {
    $rut = trim((string)$rut);
    $rut = preg_replace('/[^0-9Kk]/', '', $rut);
    return strtoupper($rut);
}

function normalize_rut_digits($rut) {
    return preg_replace('/\D/', '', trim((string)$rut));
}

function is_valid_rut($rut) {
    $rut = normalize_rut($rut);
    if ($rut === '' || !preg_match('/^\d{7,8}[0-9K]$/', $rut)) return false;

    $cuerpo = substr($rut, 0, -1);
    $dv = strtoupper(substr($rut, -1));
    if (!preg_match('/^\d+$/', $cuerpo)) return false;

    $suma = 0;
    $mult = 2;
    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += intval($cuerpo[$i]) * $mult;
        $mult = $mult < 7 ? $mult + 1 : 2;
    }

    $resto = $suma % 11;
    $dvCalc = 11 - $resto;
    if ($dvCalc === 11) $dvCalc = '0';
    elseif ($dvCalc === 10) $dvCalc = 'K';
    else $dvCalc = (string) $dvCalc;

    return $dv === $dvCalc;
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_phone($phone) {
    return preg_match('/^9\d{8}$/', $phone) === 1;
}

function require_admin_session_json() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    return $_SESSION['admin'];
}

function ensure_csrf_token($check = false) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    if ($check) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], (string) $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token inválido']);
            exit;
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * migrate()
 * Crea las tablas de la base de datos si no existen.
 * Tablas creadas:
 * - disponibilidad: Rangos de horarios disponibles para agendamiento.
 * - agendamientos: Citas confirmadas y datos del usuario.
 * - bloqueos_temporales: Bloqueos TTL (15 min) durante confirmación.
 * - admins: Credenciales de administradores.
 * - Ejecutada automáticamente al incluir este archivo.
 */
function migrate() {
    $db = get_db();

    $db->exec("CREATE TABLE IF NOT EXISTS disponibilidad (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fecha TEXT NOT NULL,
        hora_inicio TEXT NOT NULL,
        hora_fin TEXT NOT NULL,
        duracion_bloque INTEGER NOT NULL DEFAULT 20,
        max_cupos INTEGER NOT NULL DEFAULT 30,
        cupos_ocupados INTEGER NOT NULL DEFAULT 0,
        tipo TEXT DEFAULT 'Ambos',
        estado INTEGER NOT NULL DEFAULT 1
    )");

    // asegurar índice único por fecha
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_disponibilidad_fecha ON disponibilidad(fecha)');

    $db->exec("CREATE TABLE IF NOT EXISTS agendamientos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rut TEXT NOT NULL,
        nombre TEXT,
        apellido TEXT,
        correo TEXT,
        telefono TEXT,
        fecha TEXT NOT NULL,
        hora TEXT NOT NULL,
        tipo_tramite TEXT,
        estado TEXT NOT NULL DEFAULT 'confirmado',
        created_at INTEGER NOT NULL
    )");

    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_agendamiento_unico ON agendamientos(rut, fecha, hora, tipo_tramite)");

    // Tabla de clientes para asignar un id único por persona (persistente)
    $db->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rut TEXT NOT NULL UNIQUE,
        nombre TEXT,
        apellido TEXT,
        correo TEXT,
        telefono TEXT,
        created_at INTEGER NOT NULL
    )");

    // Añadir columna cliente_id a agendamientos si no existe
    $cols = $db->query("PRAGMA table_info(agendamientos)")->fetchAll(PDO::FETCH_ASSOC);
    $hasClienteId = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'cliente_id') { $hasClienteId = true; break; }
    }
    if (!$hasClienteId) {
        try {
            $db->exec("ALTER TABLE agendamientos ADD COLUMN cliente_id INTEGER NULL");
        } catch (Exception $e) {
            // ignore if cannot alter
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS bloqueos_temporales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rut TEXT,
        fecha TEXT NOT NULL,
        hora TEXT NOT NULL,
        token TEXT,
        expires_at INTEGER NOT NULL
    )");

    $cols = $db->query("PRAGMA table_info(bloqueos_temporales)")->fetchAll(PDO::FETCH_ASSOC);
    $hasToken = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'token') { $hasToken = true; break; }
    }
    if (!$hasToken) {
        try {
            $db->exec("ALTER TABLE bloqueos_temporales ADD COLUMN token TEXT");
        } catch (Exception $e) {
            // ignore if cannot alter
        }
    }

    // tabla de administradores para login
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL
    )");

    // Añadir columna 'is_super' a admins si no existe (permite un superadmin)
    $cols = $db->query("PRAGMA table_info(admins)")->fetchAll(PDO::FETCH_ASSOC);
    $hasIsSuper = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'is_super') { $hasIsSuper = true; break; }
    }
    if (!$hasIsSuper) {
        try {
            $db->exec("ALTER TABLE admins ADD COLUMN is_super INTEGER NOT NULL DEFAULT 0");
        } catch (Exception $e) {
            // ignore if cannot alter
        }
    }

    // Asegurar columna 'tipo' en disponibilidad si la tabla existía antes
    $cols = $db->query("PRAGMA table_info(disponibilidad)")->fetchAll(PDO::FETCH_ASSOC);
    $hasTipo = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'tipo') { $hasTipo = true; break; }
    }
    if (!$hasTipo) {
        try {
            $db->exec("ALTER TABLE disponibilidad ADD COLUMN tipo TEXT DEFAULT 'Ambos'");
        } catch (Exception $e) {
            // ignore if cannot alter
        }
    }

}

/**
 * Ejecuta migraciones automáticamente cuando este archivo se incluye.
 * Inicializa la base de datos en el primer acceso.
 */
migrate();

?>
