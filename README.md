// ...existing code...
# Resumen del proyecto: Agendamiento - Depto. Transporte

Este repositorio implementa un sistema de agendamiento de horas para atención vehicular. Incluye:
- Interfaz pública para usuarios.
- Panel administrativo para gestión.
- API PHP basada en SQLite (archivo único) con migraciones automáticas.

---

## Tabla de contenidos

- Resumen
- Estructura del proyecto
- Lista completa de endpoints PHP
- Flujo de agendamiento (cliente)
- Panel administrativo
- Esquema de base de datos (DDL)
- Ejemplos de API (requests / responses)
- Requisitos y despliegue local (Windows / Linux)
- Inicializar primer admin
- Troubleshooting y notas de seguridad
- Archivos importantes / logs
- Posibles mejoras

---

## Estructura del proyecto

```
├── public/              # Frontend público (usuario final)
│   ├── index.html       # Página de agendamiento
│   ├── timeout.html     # Página de sesión expirada
│   ├── css/             # Estilos (main, calendar, modal)
│   └── js/              # Lógica (form, calendar, api, ui, index_login)
├── admin/               # Panel administrativo
│   ├── dashboard.php    # Página principal (protegida)
│   ├── admin.js         # Lógica del panel
│   └── admin.css        # Estilos del panel
├── php/                 # Backend (23+ endpoints)
│   ├── db.php           # Conexión y migraciones SQLite
│   ├── login.php        # Autenticación admin
│   ├── logout.php       # Cierre de sesión
│   ├── proteger.php     # Middleware de autenticación
│   └── [otros endpoints]
├── data/                # Datos persistidos
│   └── database.sqlite  # Base de datos SQLite (archivo)
└── docs/                # Documentación adicional
```

---

## Endpoints PHP (lista completa y breve descripción)

Observación: todos los endpoints devuelven JSON salvo los que realizan redirect. Revisa los archivos en php/ para detalles de parámetros y respuestas.

Autenticación y usuarios admin
- php/db.php — conexión PDO, configuración PRAGMA, migraciones automáticas
- php/login.php — login admin (POST: user, pass)
- php/logout.php — logout (cierra sesión)
- php/proteger.php — middleware que protege rutas admin
- php/crear_admin.php — crear cuenta admin (CLI / POST)

Gestión de administradores
- php/list_admins.php — lista admins
- php/delete_admin.php — elimina admin por id

Solicitudes y estados (gestión de agendamientos)
- php/obtener_solicitudes.php — devuelve solicitudes / citas (filtros: fecha, estado)
- php/editar_solicitud.php — editar datos de una solicitud
- php/actualizar_estado.php — cambiar estado (confirmado/cancelado/atendido)
- php/marcar_atendido.php — marca como atendida
- php/eliminar_solicitud.php — elimina solicitud

Disponibilidades (rangos)
- php/obtener_disponibilidad.php — obtiene disponibilidades activas (filtros: fecha, rango)
- php/guardar_disponibilidad_rango.php — crea rango(s) de disponibilidad
- php/desactivar_rango.php — desactiva rango existente

Bloqueos temporales (previenen colisiones)
- php/crear_bloqueo.php — crea bloqueo TTL (~15 min) (POST: fecha, hora, datos)
- php/obtener_bloqueos.php — listado de bloqueos activos
- php/liberar_bloqueo.php — libera bloqueo por token
- php/limpiar_bloqueos.php — elimina bloqueos expirados (cron/manual)

Agendamiento & consultas
- php/guardar_agenda.php — confirma agendamiento (POST: token + datos usuario)
- php/obtener_agenda.php — devuelve horarios ocupados / agenda
- php/obtener_por_rut.php — consulta por RUT

Utilidades / diagnóstico
- php/diag_agendamientos.php — dump/diagnóstico (últimas N citas, 200 por defecto)

---

## Flujo de Agendamiento (cliente → servidor)

1. Usuario entra en public/index.html y selecciona tipo de trámite.
2. Frontend carga disponibilidades desde php/obtener_disponibilidad.php.
3. Usuario elige fecha/hora; frontend valida: RUT chileno, teléfono (9XXXXXXXX), email.
4. Se crea un bloqueo temporal con php/crear_bloqueo.php (retorna token y expiry).
5. Frontend muestra modal con resumen y temporizador de confirmación (120s).
   - Si el usuario confirma dentro del tiempo: se llama php/guardar_agenda.php con token y datos.
   - Si expira: frontend llama php/liberar_bloqueo.php o el backend limpia bloqueos expirados.
6. php/guardar_agenda.php:
   - Verifica token y que el slot esté libre.
   - Inserta cliente (tabla clientes) si no existe.
   - Inserta agendamiento y libera bloqueo.
   - Retorna JSON con ok=true, id_agendamiento y mensaje.

---

## Panel Administrativo (admin/)

URL típica: http://localhost/<raiz>/admin/dashboard.php

Funciones:
- Gestión visual de disponibilidades (crear, desactivar).
- Ver tabla de solicitudes; filtros por fecha, estado, tipo trámite.
- Editar solicitudes y observaciones.
- Cambiar estados (confirmado, cancelado, atendido).
- Crear/eliminar cuentas admin (solo superadmin).
- Export/diagnóstico básico con php/diag_agendamientos.php.

Protección: todas las rutas del panel usan php/proteger.php para validar sesión.

---

## Esquema de Base de Datos (referencia DDL)

Las tablas se crean en php/db.php. DDL simplificada:

```sql
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  role TEXT DEFAULT 'admin',
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS clientes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rut TEXT NOT NULL,
  nombre TEXT,
  telefono TEXT,
  email TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS disponibilidad (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  fecha_inicio TEXT NOT NULL,
  fecha_fin TEXT NOT NULL,
  hora_inicio TEXT NOT NULL,
  hora_fin TEXT NOT NULL,
  activo INTEGER DEFAULT 1,
  notas TEXT,
  created_by INTEGER,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS agendamientos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cliente_id INTEGER,
  disponibilidad_id INTEGER,
  fecha TEXT NOT NULL,
  hora TEXT NOT NULL,
  tipo_tramite TEXT,
  estado TEXT DEFAULT 'pendiente',
  observaciones TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS bloqueos_temporales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT UNIQUE,
  fecha TEXT,
  hora TEXT,
  datos TEXT, -- JSON
  expires_at INTEGER, -- unix timestamp
  created_at TEXT DEFAULT (datetime('now'))
);
```

---

## Ejemplos de API (curl)

1) Crear bloqueo temporal
```bash
curl -s -X POST "http://localhost:8000/php/crear_bloqueo.php" \
  -H "Content-Type: application/json" \
  -d '{"fecha":"2026-06-10","hora":"10:00","tipo_tramite":"patente","datos":{"rut":"12345678-5"}}'
```
Respuesta:
```json
{ "ok": true, "token": "abc123", "expires_at": 1710000000 }
```

2) Confirmar/agendar
```bash
curl -s -X POST "http://localhost:8000/php/guardar_agenda.php" \
  -H "Content-Type: application/json" \
  -d '{"token":"abc123","rut":"12345678-5","nombre":"Juan Perez","telefono":"912345678","email":"a@b.cl","tipo_tramite":"patente"}'
```
Respuesta:
```json
{ "ok": true, "id": 345, "message": "Agendamiento confirmado" }
```

3) Obtener disponibilidades por rango
```bash
curl -s "http://localhost:8000/php/obtener_disponibilidad.php?desde=2026-06-01&hasta=2026-06-30"
```
Respuesta:
```json
[{ "id": 1, "fecha_inicio":"2026-06-01","fecha_fin":"2026-06-30","hora_inicio":"09:00","hora_fin":"17:00","activo":1 }]
```

---

## Requisitos y despliegue local

Requisitos mínimos
- PHP 7.2+
- Extensiones: pdo, pdo_sqlite, sqlite3
- Permisos de escritura en data/ para el usuario del servidor web

Recomendaciones
- Activar WAL: PRAGMA journal_mode=WAL;
- Configurar busy_timeout (ej.: 5000 ms) en php/db.php para reducir locked errors.
- Mantener data/database.sqlite fuera del árbol público o proteger por .htaccess / reglas del servidor.

Despliegue local (Windows — PowerShell o CMD)
```powershell
# desde la raíz del proyecto
php -S localhost:8000 -t .
# Acceder:
# http://localhost:8000/public/index.html
# http://localhost:8000/admin/dashboard.php
```

Despliegue en Linux (ejemplo Apache)
```bash
# copia archivos
sudo cp -r . /var/www/html/agendamiento
sudo mkdir -p /var/www/html/agendamiento/data
sudo chown -R www-data:www-data /var/www/html/agendamiento/data
# configurar vhost / DocumentRoot a /var/www/html/agendamiento/public o ajustar enlaces según tu setup
```

---

## Inicializar primer admin

Opción 1 — Script PHP (si existe)
```bash
php php/crear_admin.php --user=admin --pass="TuPassSeguro"
```

Opción 2 — Manual con sqlite3
1. Obtener hash de contraseña:
```bash
php -r "echo password_hash('TuPassSeguro', PASSWORD_DEFAULT).PHP_EOL;"
```
2. Insertar en DB:
```sql
INSERT INTO admins (username, password, role) VALUES ('admin','<hash>','superadmin');
```

