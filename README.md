Usuario1(SuperAdmin): admin - contraseña: 123456

Usuario2(Admin): GESkritch - contraseña: 1234

Usuario3: el usuario 3 es el usuario comun que ingrese al sitio, es quien puede agendar la hora de atencion.

# Resumen del proyecto: Agendamiento - Depto. Transporte

Este repositorio implementa un sistema de agendamiento de horas para atención vehicular.

---

## Tabla de contenidos

- Resumen
- Estructura del proyecto (archivo por archivo)
- Lista completa de endpoints PHP
- Flujo de agendamiento (cliente → servidor)
- Panel administrativo
- Esquema de base de datos (DDL)
- Ejemplos de API (curl)
- Requisitos y despliegue local
- Inicializar primer admin

---

## Estructura del proyecto (archivo por archivo)

Raíz:

- README.md
- .gitignore
- tmp_extract_docx.py
- Mauricio_Carrillo.docx
- Mauricio_Carrillo_Fuentes.docx
- .git/ (si existe)
- .venv/ (entorno virtual opcional)
 - .gitattributes

Archivos públicos en la raíz del proyecto:

- index.html
- timeout.html
- css/calendar.css
- css/main.css
- css/modal.css
- js/api.js
- js/calendar.js
- js/form.js
- js/index_login.js
- js/ui.js

Carpeta `admin/`:

- admin/dashboard.php
- admin/dashboard.html
- admin/admin.js
- admin/admin.css

Carpeta `php/` (endpoints y utilidades):

- php/db.php
- php/login.php
- php/logout.php
- php/proteger.php
- php/crear_admin.php
- php/list_admins.php
- php/delete_admin.php
- php/crear_bloqueo.php
- php/obtener_bloqueos.php
- php/liberar_bloqueo.php
- php/limpiar_bloqueos.php
- php/guardar_agenda.php
- php/obtener_agenda.php
- php/obtener_por_rut.php
- php/obtener_disponibilidad.php
- php/guardar_disponibilidad_rango.php
- php/desactivar_rango.php
- php/obtener_solicitudes.php
- php/editar_solicitud.php
- php/actualizar_estado.php
- php/marcar_atendido.php
- php/eliminar_solicitud.php
- php/diag_agendamientos.php

Carpeta `data/`:

- data/ (carpeta de almacenamiento local; ya no se usa SQLite para la aplicación)

Carpeta `docs/`:

- PROJECT_DOCUMENTATION.md

---

## Endpoints PHP (lista completa y breve descripción)

Observación: todos los endpoints devuelven JSON salvo los que realizan redirect. Revisa los archivos en `php/` para detalles de parámetros y respuestas.

Autenticación y usuarios admin
- `php/db.php` — conexión PDO a MySQL, migraciones automáticas
- `php/login.php` — login admin (POST: user, pass)
- `php/logout.php` — logout (cierra sesión)
- `php/proteger.php` — middleware que protege rutas admin
- `php/crear_admin.php` — crear cuenta admin (CLI / POST)

Gestión de administradores
- `php/list_admins.php` — lista admins
- `php/delete_admin.php` — elimina admin por id

Solicitudes y estados (gestión de agendamientos)
- `php/obtener_solicitudes.php` — devuelve solicitudes / citas (filtros: fecha, estado)
- `php/editar_solicitud.php` — editar datos de una solicitud
- `php/actualizar_estado.php` — cambiar estado (confirmado/cancelado/atendido)
- `php/marcar_atendido.php` — marca como atendida
- `php/eliminar_solicitud.php` — elimina solicitud

Disponibilidades (rangos)
- `php/obtener_disponibilidad.php` — obtiene disponibilidades activas (filtros: fecha, rango)
- `php/guardar_disponibilidad_rango.php` — crea rango(s) de disponibilidad
- `php/desactivar_rango.php` — desactiva rango existente

Bloqueos temporales (previenen colisiones)
- `php/crear_bloqueo.php` — crea bloqueo TTL (POST: fecha, hora, datos)
- `php/obtener_bloqueos.php` — listado de bloqueos activos
- `php/liberar_bloqueo.php` — libera bloqueo por token
- `php/limpiar_bloqueos.php` — elimina bloqueos expirados (cron/manual)

Agendamiento & consultas
- `php/guardar_agenda.php` — confirma agendamiento (POST: token + datos usuario)
- `php/obtener_agenda.php` — devuelve horarios ocupados / agenda
- `php/obtener_por_rut.php` — consulta por RUT

Utilidades / diagnóstico
- `php/diag_agendamientos.php` — dump/diagnóstico (últimas N citas)

---

## Flujo de Agendamiento (cliente → servidor)

1. Usuario entra en `public/index.html` y selecciona tipo de trámite.
2. Frontend carga disponibilidades desde `php/obtener_disponibilidad.php`.
3. Usuario elige fecha/hora; frontend valida RUT chileno, teléfono (9XXXXXXXX) y email.
4. Se crea un bloqueo temporal con `php/crear_bloqueo.php` (retorna token y expiry).
5. Frontend muestra modal con resumen y temporizador de confirmación.
   - Si el usuario confirma: se llama a `php/guardar_agenda.php` con token y datos.
   - Si expira: se libera el bloqueo o se limpia por `php/limpiar_bloqueos.php`.
6. `php/guardar_agenda.php` verifica token, crea/actualiza cliente, inserta agendamiento y libera el bloqueo.

---

## Panel Administrativo (admin/)

URL típica: `http://localhost/<raiz>/admin/dashboard.php`

Archivos del panel:
- `admin/dashboard.php`
- `admin/dashboard.html`
- `admin/admin.js`
- `admin/admin.css`

Funciones principales: gestión de disponibilidades, ver/editar solicitudes, cambiar estados y gestión de cuentas admin.

---

## Esquema de Base de Datos (referencia DDL)

Las tablas se crean en `php/db.php`. DDL simplificada:

```sql
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_super TINYINT NOT NULL DEFAULT 0
);

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rut VARCHAR(20) NOT NULL UNIQUE,
  nombre VARCHAR(255) NULL,
  telefono VARCHAR(20) NULL,
  correo VARCHAR(255) NULL,
  created_at BIGINT NOT NULL
);

CREATE TABLE disponibilidad (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha VARCHAR(20) NOT NULL,
  hora_inicio VARCHAR(10) NOT NULL,
  hora_fin VARCHAR(10) NOT NULL,
  duracion_bloque INT NOT NULL DEFAULT 20,
  max_cupos INT NOT NULL DEFAULT 30,
  cupos_ocupados INT NOT NULL DEFAULT 0,
  tipo VARCHAR(50) DEFAULT 'Ambos',
  estado TINYINT NOT NULL DEFAULT 1
);

CREATE TABLE agendamientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id BIGINT NULL,
  rut VARCHAR(20) NOT NULL,
  fecha VARCHAR(20) NOT NULL,
  hora VARCHAR(10) NOT NULL,
  tipo_tramite VARCHAR(100) NULL,
  estado VARCHAR(50) NOT NULL DEFAULT 'confirmado',
  created_at BIGINT NOT NULL
);

CREATE TABLE bloqueos_temporales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(255) NULL,
  fecha VARCHAR(20) NOT NULL,
  hora VARCHAR(10) NOT NULL,
  expires_at BIGINT NOT NULL
);
```

---

## Gestión de superadmin

Es posible crear un nuevo superadmin desde MySQL con este comando:

```sql
INSERT INTO admins (username, password_hash, is_super)
VALUES ('nuevo_super', '$2y$10$Q8uY5x2nV2h8xP6O2JjQ2e8s2h2u7hTn8q8Gz1jZy1YQ7M0D9ZK6', 1);
```

Para eliminarlo siendo el técnico de la página, se usa este comando:

```sql
DELETE FROM admins WHERE username = 'nuevo_super';
```

> También es posible crear un administrador normal desde el panel y luego dejarlo como superadmin con:

```sql
UPDATE admins SET is_super = 1 WHERE username = 'nuevo_super';
```

## Ejemplos de API (curl)

1) Crear bloqueo temporal
```bash
curl -s -X POST "http://localhost:8000/php/crear_bloqueo.php" \
  -H "Content-Type: application/json" \
  -d '{"fecha":"2026-06-10","hora":"10:00","tipo_tramite":"patente","datos":{"rut":"12345678-5"}}'
```

2) Confirmar/agendar
```bash
curl -s -X POST "http://localhost:8000/php/guardar_agenda.php" \
  -H "Content-Type: application/json" \
  -d '{"token":"abc123","rut":"12345678-5","nombre":"Juan Perez","telefono":"912345678","email":"a@b.cl","tipo_tramite":"patente"}'
```

3) Obtener disponibilidades por rango
```bash
curl -s "http://localhost:8000/php/obtener_disponibilidad.php?desde=2026-06-01&hasta=2026-06-30"
```

---

## Requisitos y despliegue local

Requisitos mínimos:

- PHP 7.2+
- Extensiones: pdo, pdo_sqlite, sqlite3
- Permisos de escritura en `data/` para el usuario del servidor web

Despliegue local (PHP built-in server):

```powershell
# desde la raíz del proyecto
php -S localhost:8000 -t .
# Acceder a:
# http://localhost:8000/public/index.html
# http://localhost:8000/admin/dashboard.php
```

Despliegue ejemplo en Linux (Apache): los archivos deben copiarse al DocumentRoot y `data/` debe ser writable por el usuario del servidor web.

---

## Inicializar primer admin

Opción 1 — Script PHP (si existe):

```bash
php php/crear_admin.php --user=admin --pass="TuPassSeguro"
```

Opción 2 — Manual con sqlite3:

1. Obtener hash de contraseña:
```bash
php -r "echo password_hash('TuPassSeguro', PASSWORD_DEFAULT).PHP_EOL;"
```
2. Insertar en DB:
```sql
INSERT INTO admins (username, password, role) VALUES ('admin','<hash>','superadmin');
```
