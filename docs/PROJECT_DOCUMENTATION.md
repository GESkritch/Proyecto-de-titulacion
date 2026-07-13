# Documentación: Proyecto Agendamiento - Depto. Transporte

**Última actualización:** 15 de enero de 2026

---

## Resumen

- **Proyecto:** Sistema de agendamiento de horas para atención vehicular (Municipalidad, Departamento Transporte)
- **Stack:** PHP 7.2+ | SQLite | JavaScript ES6 | CSS3 | HTML5
- **Estructura:** Frontend público + Panel administrativo + API REST-like + Base de datos
- **Estado:** ✅ **Completamente documentado** (36 archivos con comentarios profesionales)

---

## 📋 Índice

1. [Documentación de Código](#documentación-de-código)
2. [Visión General del Sistema](#visión-general-del-sistema)
3. [Estructura de Carpetas](#estructura-de-carpetas)
4. [Endpoints PHP](#endpoints-php)
5. [Frontend Público](#frontend-público)
6. [Panel Administrativo](#panel-administrativo)
7. [Base de Datos](#base-de-datos)
8. [Flujos de Negocio](#flujos-de-negocio)
9. [Seguridad](#seguridad)
10. [Instalación y Ejecución](#instalación-y-ejecución)

---

## 📚 Documentación de Código

### ✅ Status: 100% Documentado

| Componente | Archivos | Documentación |
|-----------|----------|----------------|
| **Admin** | 3 | JSDoc + comentarios de sección |
| **PHP Backend** | 23 | PHPDoc + comentarios de flujo |
| **Frontend JS** | 5 | JSDoc + comentarios de función |
| **CSS** | 3 | Comentarios de sección |
| **HTML** | 2 | Comentarios HTML |
| **TOTAL** | **36** | ✅ 100% |

### Estándares Aplicados

- **PHP:** PHPDoc (/** ... */) con descripción, parámetros, retorno
- **JavaScript:** JSDoc (/** ... */) con @param, @returns, descripción de flujo
- **CSS:** Comentarios de sección explicando propósito de estilos
- **HTML:** Comentarios explicando estructura y propósito de elementos

---

## 🎯 Visión General del Sistema

### Flujo de Usuario Final

```
1. Usuario accede a public/index.html
   ↓
2. Selecciona tipo de trámite (Renovación / Licencia nueva)
   ↓
3. Calendario carga disponibilidades desde php/obtener_disponibilidad.php
   ↓
4. Usuario selecciona fecha y hora
   ↓
5. Formulario se habilita para datos personales
   ↓
6. Validaciones: RUT chileno, teléfono, email
   ↓
7. Form.js crea bloqueo temporal (php/crear_bloqueo.php)
   ↓
8. Modal muestra confirmación con temporizador (120 seg)
   ↓
9. Si confirma → php/guardar_agenda.php persiste cita
   Si cancela → php/liberar_bloqueo.php libera hora
```

### Flujo Administrativo

```
1. Admin accede a admin/dashboard.php (requiere sesión)
   ↓
2. Crear disponibilidades:
   - Rango de fechas + horas + cupos + tipo de trámite
   - POST php/guardar_disponibilidad_rango.php
   ↓
3. Ver solicitudes:
   - Tabla de citas desde php/obtener_solicitudes.php
   - Filtrar por: fecha, estado, hoy, todos
   ↓
4. Editar solicitud:
   - Modal con formulario
   - POST php/editar_solicitud.php
   ↓
5. Cambiar estado:
   - Botones: Marcar cancelado, Marcar confirmado, Marcar atendido
   - POST php/actualizar_estado.php o php/marcar_atendido.php
```

---

## 📁 Estructura de Carpetas

```
proyecto-agendamiento/
│
├── 📄 README.md                     # Guía rápida del proyecto
│
├── 📁 docs/
│   └── PROJECT_DOCUMENTATION.md    # Este archivo
│
├── 📁 public/                       # Frontend público (usuario final)
│   ├── index.html                  # ✅ Página principal
│   ├── timeout.html                # ✅ Sesión expirada
│   │
│   ├── css/
│   │   ├── main.css                # ✅ Estilos globales
│   │   ├── calendar.css            # ✅ Estilos calendario
│   │   └── modal.css               # ✅ Estilos modal
│   │
│   └── js/
│       ├── api.js                  # ✅ Wrappers HTTP
│       ├── calendar.js             # ✅ Calendario interactivo
│       ├── form.js                 # ✅ Lógica formulario
│       ├── ui.js                   # ✅ Utilidades UI
│       └── index_login.js          # ✅ Login admin modal
│
├── 📁 admin/                        # Panel administrativo
│   ├── dashboard.php               # ✅ Template HTML (protegido)
│   ├── admin.js                    # ✅ Lógica panel
│   └── admin.css                   # ✅ Estilos panel
│
├── 📁 php/                          # Backend (23 endpoints)
│   │
│   ├── db.php                      # ✅ Conexión + migraciones
│   │
│   ├── 🔐 Autenticación (4)
│   │   ├── login.php               # ✅ Autentica usuarios
│   │   ├── logout.php              # ✅ Cierra sesión
│   │   ├── proteger.php            # ✅ Middleware
│   │   └── crear_admin.php         # ✅ Crea admin
│   │
│   ├── 👥 Administradores (2)
│   │   ├── delete_admin.php        # ✅ Elimina admin
│   │   └── list_admins.php         # ✅ Lista admins
│   │
│   ├── 📝 Solicitudes (5)
│   │   ├── obtener_solicitudes.php # ✅ Devuelve todas
│   │   ├── editar_solicitud.php    # ✅ Actualiza datos
│   │   ├── actualizar_estado.php   # ✅ Cambia estado
│   │   ├── marcar_atendido.php     # ✅ Marca atendida
│   │   └── eliminar_solicitud.php  # ✅ Elimina solicitud
│   │
│   ├── 📅 Disponibilidades (3)
│   │   ├── obtener_disponibilidad.php       # ✅ Devuelve rangos
│   │   ├── guardar_disponibilidad_rango.php # ✅ Crea rangos
│   │   └── desactivar_rango.php             # ✅ Desactiva rangos
│   │
│   ├── 🔒 Bloqueos (4)
│   │   ├── crear_bloqueo.php       # ✅ Crea bloqueo TTL
│   │   ├── obtener_bloqueos.php    # ✅ Devuelve bloqueos
│   │   ├── liberar_bloqueo.php     # ✅ Libera bloqueo
│   │   └── limpiar_bloqueos.php    # ✅ Limpia expirados
│   │
│   ├── 📋 Agendamiento (3)
│   │   ├── guardar_agenda.php      # ✅ Persiste cita
│   │   ├── obtener_agenda.php      # ✅ Devuelve horas ocupadas
│   │   └── obtener_por_rut.php     # ✅ Busca por RUT
│   │
│   └── 🔧 Utilidades (2)
│       ├── diag_agendamientos.php  # ✅ Diagnóstico
│       └── db.php                  # ✅ BD + migraciones
│
├── 📁 data/                         # Datos persistidos
│   ├── database.sqlite             # Base de datos SQLite
│   ├── php_errors.log              # Log de errores PHP
│   └── guardar_agenda_requests.log # Debug de agendamientos
│
└── 📁 build/                        # (Opcional) Archivos compilados
```

---

## 🔌 Endpoints PHP

### Autenticación

| Método | Endpoint | Función |
|--------|----------|---------|
| POST | `php/login.php` | Autentica usuario admin |
| POST | `php/logout.php` | Cierra sesión |
| GET | `php/proteger.php` | Middleware de validación |
| POST | `php/crear_admin.php` | Crea nueva cuenta admin |
| POST | `php/delete_admin.php` | Elimina cuenta admin |
| GET | `php/list_admins.php` | Lista administradores |

### Solicitudes / Agendamientos

| Método | Endpoint | Función |
|--------|----------|---------|
| GET | `php/obtener_solicitudes.php` | Devuelve todas las citas |
| POST | `php/editar_solicitud.php` | Actualiza detalles de cita |
| POST | `php/actualizar_estado.php` | Cambia estado (confirmado/cancelado/atendido) |
| POST | `php/marcar_atendido.php` | Marca como atendida |
| DELETE | `php/eliminar_solicitud.php` | Elimina cita |

### Disponibilidades

| Método | Endpoint | Función |
|--------|----------|---------|
| GET | `php/obtener_disponibilidad.php` | Devuelve rangos disponibles |
| POST | `php/guardar_disponibilidad_rango.php` | Crea/actualiza rangos |
| POST | `php/desactivar_rango.php` | Desactiva rangos |

### Bloqueos Temporales

| Método | Endpoint | Función |
|--------|----------|---------|
| POST | `php/crear_bloqueo.php` | Crea bloqueo TTL 15 min |
| GET | `php/obtener_bloqueos.php` | Devuelve bloqueos vigentes |
| POST | `php/liberar_bloqueo.php` | Libera bloqueo |
| GET | `php/limpiar_bloqueos.php` | Limpia expirados |

### Agenda

| Método | Endpoint | Función |
|--------|----------|---------|
| POST | `php/guardar_agenda.php` | Persiste cita confirmada |
| GET | `php/obtener_agenda.php` | Devuelve horas ocupadas |
| POST | `php/obtener_por_rut.php` | Busca última cita por RUT |

### Diagnóstico

| Método | Endpoint | Función |
|--------|----------|---------|
| GET | `php/diag_agendamientos.php` | Últimas 200 citas (debug) |
| GET | `php/db.php` | Conexión + migraciones |

---

## 🎨 Frontend Público

### Flujo de Datos en public/index.html

```
form.js (controlador principal)
  ├── Importa: calendar.js, api.js, ui.js
  │
  ├── calendar.js
  │   ├── Carga: obtenerDisponibilidad()
  │   ├── Carga: obtenerAgenda()
  │   ├── Carga: obtenerBloqueos()
  │   └── Exporta: selectedDay, selectedHour
  │
  ├── api.js (wrappers HTTP)
  │   ├── obtenerAgenda()
  │   ├── guardarAgenda()
  │   ├── obtenerDisponibilidad()
  │   ├── obtenerBloqueos()
  │   ├── crearBloqueo()
  │   └── liberarBloqueo()
  │
  ├── ui.js (interfaz)
  │   ├── showModal()
  │   ├── iniciarContador() // Modal timer (120 seg)
  │   ├── iniciarTemporizadorPagina() // Page timer (900 seg)
  │   └── onModalCancel() // Hook
  │
  └── index_login.js (standalone)
      └── Modal login admin
```

### Estados del Formulario

```
Inicio: .disabled-fieldset (bloqueado)
  ↓
Selecciona trámite: aún bloqueado
  ↓
Selecciona fecha+hora: .enabled-fieldset (habilitado)
  ↓
Usuario llena datos: formulario listo para submit
  ↓
Presiona "Agendar": 
  - Validaciones
  - crear_bloqueo()
  - showModal()
  - Timer 120 segundos
  ↓
Usuario confirma: guardarAgenda()
o Usuario cancela: liberarBloqueo()
```

---

## 👨‍💼 Panel Administrativo

### Acceso

```
URL: http://localhost/agendamiento/admin/dashboard.php
Requiere: Sesión admin (php/proteger.php)
Si no autenticado: Redirige a public/index.html
```

### Funcionalidades

**1. Crear Disponibilidades**
- Rango de fechas (inicio / fin)
- Hora inicio / fin
- Duración de bloque (minutos)
- Cupos máximos
- Tipo de trámite
- POST: `php/guardar_disponibilidad_rango.php`

**2. Desactivar Disponibilidades**
- Rango de fechas
- Tipo de trámite (opcional)
- POST: `php/desactivar_rango.php`

**3. Tabla de Solicitudes**
- Mostrar todas las citas
- GET: `php/obtener_solicitudes.php`
- Columnas: Fecha, Hora, Nombres, RUT, Teléfono, Email, Trámite, Estado

**4. Filtros**
- Por fecha (calendario)
- Por estado (confirmado/otros)
- Hoy con estado específico
- Todos sin filtro

**5. Editar Solicitud**
- Modal con formulario
- Campos: fecha, hora, email, teléfono, nombres, apellidos, tipo
- POST: `php/editar_solicitud.php`

**6. Cambiar Estado**
- Botones en tabla: Marcar cancelado, Marcar confirmado, Marcar atendido
- POST: `php/actualizar_estado.php` o `php/marcar_atendido.php`

**7. Gestionar Admins (Superadmin)**
- Crear nuevo admin
- Eliminar admin existente
- Lista de admins activos

---

## 🗄️ Base de Datos

### Tabla: `disponibilidad`

```sql
CREATE TABLE disponibilidad (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha TEXT NOT NULL,              -- YYYY-MM-DD
    hora_inicio TEXT NOT NULL,        -- HH:MM
    hora_fin TEXT NOT NULL,           -- HH:MM
    duracion_bloque INTEGER DEFAULT 20,   -- minutos
    max_cupos INTEGER DEFAULT 30,     -- cupos disponibles
    cupos_ocupados INTEGER DEFAULT 0, -- cupos usados
    tipo TEXT DEFAULT 'Ambos',        -- Tipo de trámite
    estado INTEGER DEFAULT 1          -- 1=activa, 0=inactiva
);
```

### Tabla: `agendamientos`

```sql
CREATE TABLE agendamientos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rut TEXT NOT NULL,                -- RUT sin formato
    nombre TEXT,
    apellido TEXT,
    correo TEXT,
    telefono TEXT,
    fecha TEXT NOT NULL,              -- YYYY-MM-DD
    hora TEXT NOT NULL,               -- HH:MM
    tipo_tramite TEXT,
    estado TEXT DEFAULT 'confirmado', -- confirmado/cancelado/atendido
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Tabla: `bloqueos_temporales`

```sql
CREATE TABLE bloqueos_temporales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rut TEXT,
    fecha TEXT NOT NULL,              -- YYYY-MM-DD
    hora TEXT NOT NULL,               -- HH:MM
    expires_at INTEGER NOT NULL       -- Unix timestamp (now + 900 seg)
);
```

### Tabla: `admins`

```sql
CREATE TABLE admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_super INTEGER DEFAULT 0        -- 1=superadmin, 0=admin regular
);
```

---

## 🔄 Flujos de Negocio

### 1. Crear Cita (Usuario Final)

**Input:**
- Trámite: "Renovación" o "Licencia nueva"
- Fecha: YYYY-MM-DD
- Hora: HH:MM
- RUT: digitos + verificador
- Teléfono: 9XXXXXXXX
- Email: válido

**Proceso:**
1. Validar RUT (dígito verificador chileno)
2. Validar teléfono y email
3. Crear bloqueo temporal (15 min)
4. Mostrar modal de confirmación (120 seg)
5. Si confirma: `guardar_agenda.php` verifica disponibilidad y persiste
6. Si cancela o expira: libera bloqueo

**Output:**
- ✅ Cita confirmada en base de datos
- 📧 (Potencial) Email de confirmación
- ❌ Error si colisiona con otro bloqueo/cita

### 2. Gestionar Disponibilidades (Admin)

**Input:**
- Rango fechas: desde / hasta (YYYY-MM-DD)
- Horarios: inicio / fin (HH:MM)
- Bloque: duración en minutos
- Cupos: máximo de citas
- Tipo: "Renovación", "Licencia nueva", "Ambos"

**Proceso:**
1. Validar fechas
2. Para cada día del rango: INSERT/UPDATE en tabla `disponibilidad`
3. Marcar como estado = 1 (activa)

**Output:**
- ✅ Disponibilidades creadas
- 📅 Visible en calendario público

### 3. Editar/Cambiar Estado (Admin)

**Input:**
- ID de cita
- Nuevos valores (fecha, hora, email, etc.)
- Nuevo estado (confirmado/cancelado/atendido)

**Proceso:**
1. Verificar sesión admin
2. UPDATE en tabla `agendamientos`
3. Si cambia a "atendido", se libera el cupo correspondiente.

**Output:**
- ✅ Cita actualizada

---

## 🔐 Seguridad

### Validaciones de Entrada

- **RUT:** Formato chileno con dígito verificador (19.123.456-K)
- **Teléfono:** Formato 9XXXXXXXX (9 dígitos)
- **Email:** Regex básico (^\S+@\S+\.\S+$)
- **Fecha:** YYYY-MM-DD (validado en PHP)
- **Hora:** HH:MM

### Protección contra Colisiones

1. **Bloqueos Temporales (TTL 15 min)**
   - Creados antes de confirmar
   - Evitan que dos usuarios booking la misma hora
   - Auto-limpiados cuando expiran

2. **Validación de Cupos**
   - `guardar_agenda.php` verifica `max_cupos` vs `cupos_ocupados`
   - Si está lleno: rechaza

### Autenticación Admin

- Sesiones PHP (`$_SESSION['admin']`)
- Passwords hasheados con `password_hash()`
- `proteger.php` valida en cada página admin
- Sin sesión: redirige a public/index.html

### Otros

- PDO con prepared statements (evita SQL injection)
- WAL mode + busy_timeout para concurrencia segura
- Logs de requests en `/data/` para audit

---

## ⚙️ Instalación y Ejecución

### Requisitos

- Apache 2.4+
- PHP 7.2+ (con extensión SQLite3)
- Acceso a `/var/www/html/` o equivalente

### Pasos

```bash
# 1. Clonar/copiar proyecto
cp -r proyecto-agendamiento /var/www/html/agendamiento

# 2. Crear directorio data con permisos
mkdir -p /var/www/html/agendamiento/data
chmod 755 /var/www/html/agendamiento/data

# 3. Acceder a página pública
# http://localhost/agendamiento/public/index.html

# 4. Para admin (crear usuario primero en DB o via PHP):
# http://localhost/agendamiento/admin/dashboard.php
```

### Crear Primer Admin (vía SQL)

```sql
-- Conectar a SQLite
sqlite3 /var/www/html/agendamiento/data/database.sqlite

-- Insertar admin
INSERT INTO admins (username, password_hash, is_super) 
VALUES ('admin', 
  '$2y$10$...hash...',  -- Resultado de password_hash('contraseña', PASSWORD_DEFAULT)
  1);
```

### Variables de Entorno

Ninguna requerida. El proyecto usa configuración hardcodeada:
- **TTL Bloqueo:** 15 min (900 segundos)
- **Timer Confirmación:** 120 segundos
- **Timer Página:** 900 segundos (15 min)
- **Ruta DB:** `/data/database.sqlite`

---

## 📝 Notas Técnicas

### Frontend

- **Vanilla JavaScript ES6:** Sin frameworks (React, Vue, etc.)
- **Modulos:** `form.js` importa `calendar.js`, `api.js`, `ui.js`
- **Temporizadores:** Usando `setInterval()`
- **AJAX:** `fetch()` nativo de navegadores

### Backend

- **PHP:** Procedural + algunas funciones helpers
- **PDO:** Para queries (prepared statements)
- **SQLite:** Archivo basado, no requiere servidor
- **WAL Mode:** Para concurrencia

### Base de Datos

- **Archivo:** `/data/database.sqlite`
- **Tamaño típico:** < 5 MB (miles de citas)
- **Backup:** Copiar archivo SQLite

### Performance

- Índices en tabla `disponibilidad` (fecha)
- Índices en tabla `agendamientos` (fecha, estado)
- Queries optimizadas con WHERE clauses

---

## 🎓 Recursos Relacionados

- [README.md](../README.md) - Guía rápida
- [Tabla de Rutas](#-endpoints-php) - Todos los endpoints
- [Estructura DB](#-base-de-datos) - Esquema completo


**Fin de documentación**