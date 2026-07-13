<?php
/**
 * archivo: db.php
 * función: Inicializa y devuelve conexión PDO a base de datos MySQL.
 * - Ejecuta migraciones automáticas para crear tablas si no existen.
 * - Usa variables de entorno para host, usuario, contraseña y nombre de base de datos.
 *
 * Funciones principales:
 * - get_db(): Retorna conexión PDO singleton para MySQL.
 * - migrate(): Crea tablas (disponibilidad, agendamientos, clientes, bloqueos_temporales, admins, superadmin).
 *
 * Incluido por todos los endpoints PHP del proyecto.
 */

function load_dotenv($path) {
    if (!is_readable($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        $firstChar = substr($value, 0, 1);
        $lastChar = substr($value, -1);
        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            $value = substr($value, 1, -1);
        }

        $data[$name] = $value;
    }

    return $data;
}

function get_env_value($name, $default = null) {
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    static $dotenv = null;
    if ($dotenv === null) {
        $dotenv = load_dotenv(dirname(__DIR__, 1) . '/.env');
    }

    if (isset($dotenv[$name])) {
        return $dotenv[$name];
    }

    return $default;
}

function get_db_config() {
    return [
        'host' => get_env_value('DB_HOST', '127.0.0.1'),
        'port' => get_env_value('DB_PORT', '3306'),
        'name' => get_env_value('DB_NAME', 'agenda_db'),
        'user' => get_env_value('DB_USER', 'root'),
        'pass' => get_env_value('DB_PASS', ''),
        'charset' => get_env_value('DB_CHARSET', 'utf8mb4'),
    ];
}

/**
 * get_db()
 * Retorna la conexión PDO singleton a la base de datos MySQL.
 * @return PDO
 */
function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = get_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('SET NAMES utf8mb4');

    return $pdo;
}

function table_exists($db, $tableName) {
    $escaped = str_replace("'", "''", $tableName);
    $stmt = $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$escaped}'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return intval($row['c'] ?? 0) > 0;
}

function column_exists($db, $tableName, $columnName) {
    $escapedTable = str_replace("'", "''", $tableName);
    $escapedColumn = str_replace("'", "''", $columnName);
    $stmt = $db->query("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$escapedTable}' AND column_name = '{$escapedColumn}'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return intval($row['c'] ?? 0) > 0;
}

function ensure_column($db, $tableName, $columnName, $definition) {
    if (!column_exists($db, $tableName, $columnName)) {
        $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$definition}");
    }
}

function sync_persona_lista($db, $row, $estado = 'atendido') {
    if (!$row || !isset($row['id'])) return;
    if ($estado !== 'atendido') return;

    $now = time();
    $stmt = $db->prepare('INSERT INTO personas_listas (
        agendamiento_id, rut, nombre, apellido, correo, telefono, fecha, hora, tipo_tramite, estado, created_at, atendido_at
    ) VALUES (
        :agendamiento_id, :rut, :nombre, :apellido, :correo, :telefono, :fecha, :hora, :tipo_tramite, :estado, :created_at, :atendido_at
    ) ON DUPLICATE KEY UPDATE
        rut = VALUES(rut),
        nombre = VALUES(nombre),
        apellido = VALUES(apellido),
        correo = VALUES(correo),
        telefono = VALUES(telefono),
        fecha = VALUES(fecha),
        hora = VALUES(hora),
        tipo_tramite = VALUES(tipo_tramite),
        estado = VALUES(estado),
        atendido_at = VALUES(atendido_at)');

    $stmt->execute([
        ':agendamiento_id' => intval($row['id']),
        ':rut' => (string)($row['rut'] ?? ''),
        ':nombre' => (string)($row['nombre'] ?? ''),
        ':apellido' => (string)($row['apellido'] ?? ''),
        ':correo' => (string)($row['correo'] ?? ''),
        ':telefono' => (string)($row['telefono'] ?? ''),
        ':fecha' => (string)($row['fecha'] ?? ''),
        ':hora' => (string)($row['hora'] ?? ''),
        ':tipo_tramite' => (string)($row['tipo_tramite'] ?? ''),
        ':estado' => $estado,
        ':created_at' => intval($row['created_at'] ?? $now),
        ':atendido_at' => $now,
    ]);
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
 * - superadmin: Superadmins alternativos.
 * - Ejecutada automáticamente al incluir este archivo.
 */
function migrate() {
    $db = get_db();

    $db->exec("CREATE TABLE IF NOT EXISTS disponibilidad (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha VARCHAR(20) NOT NULL,
        hora_inicio VARCHAR(10) NOT NULL,
        hora_fin VARCHAR(10) NOT NULL,
        duracion_bloque INT NOT NULL DEFAULT 20,
        max_cupos INT NOT NULL DEFAULT 30,
        cupos_ocupados INT NOT NULL DEFAULT 0,
        tipo VARCHAR(50) DEFAULT 'Ambos',
        estado TINYINT NOT NULL DEFAULT 1
    )");

    try {
        $db->exec('CREATE UNIQUE INDEX ux_disponibilidad_fecha ON disponibilidad(fecha)');
    } catch (Exception $e) {
        // ignore if already exists
    }

    $db->exec("CREATE TABLE IF NOT EXISTS agendamientos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut VARCHAR(20) NOT NULL,
        nombre VARCHAR(255) NULL,
        apellido VARCHAR(255) NULL,
        correo VARCHAR(255) NULL,
        telefono VARCHAR(20) NULL,
        fecha VARCHAR(20) NOT NULL,
        hora VARCHAR(10) NOT NULL,
        tipo_tramite VARCHAR(100) NULL,
        estado VARCHAR(50) NOT NULL DEFAULT 'confirmado',
        created_at BIGINT NOT NULL
    )");

    try {
        $db->exec('CREATE UNIQUE INDEX ux_agendamiento_unico ON agendamientos(rut, fecha, hora, tipo_tramite)');
    } catch (Exception $e) {
        // ignore if already exists
    }

    $db->exec("CREATE TABLE IF NOT EXISTS personas_listas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agendamiento_id INT NOT NULL UNIQUE,
        rut VARCHAR(20) NOT NULL,
        nombre VARCHAR(255) NULL,
        apellido VARCHAR(255) NULL,
        correo VARCHAR(255) NULL,
        telefono VARCHAR(20) NULL,
        fecha VARCHAR(20) NOT NULL,
        hora VARCHAR(10) NOT NULL,
        tipo_tramite VARCHAR(100) NULL,
        estado VARCHAR(50) NOT NULL DEFAULT 'atendido',
        created_at BIGINT NOT NULL,
        atendido_at BIGINT NOT NULL
    )");

    try {
        $db->exec("INSERT IGNORE INTO personas_listas (agendamiento_id, rut, nombre, apellido, correo, telefono, fecha, hora, tipo_tramite, estado, created_at, atendido_at)
            SELECT id, rut, nombre, apellido, correo, telefono, fecha, hora, tipo_tramite, estado, created_at, created_at
            FROM agendamientos
            WHERE estado = 'atendido'");
    } catch (Exception $e) {
        // ignore if the table was just created or data is already present
    }

    $db->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut VARCHAR(20) NOT NULL UNIQUE,
        nombre VARCHAR(255) NULL,
        apellido VARCHAR(255) NULL,
        correo VARCHAR(255) NULL,
        telefono VARCHAR(20) NULL,
        created_at BIGINT NOT NULL
    )");

    ensure_column($db, 'agendamientos', 'cliente_id', 'cliente_id BIGINT NULL');

    $db->exec("CREATE TABLE IF NOT EXISTS bloqueos_temporales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut VARCHAR(20) NULL,
        fecha VARCHAR(20) NOT NULL,
        hora VARCHAR(10) NOT NULL,
        token VARCHAR(255) NULL,
        expires_at BIGINT NOT NULL
    )");

    ensure_column($db, 'bloqueos_temporales', 'token', 'token VARCHAR(255) NULL');

    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL
    )");

    ensure_column($db, 'admins', 'is_super', 'is_super TINYINT NOT NULL DEFAULT 0');

    $db->exec("CREATE TABLE IF NOT EXISTS superadmin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL
    )");

    ensure_column($db, 'disponibilidad', 'tipo', "tipo VARCHAR(50) NULL DEFAULT 'Ambos'");
}

/**
 * Ejecuta migraciones automáticamente cuando este archivo se incluye.
 * Inicializa la base de datos en el primer acceso.
 */
migrate();

?>
