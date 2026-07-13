<?php include("../php/proteger.php"); ?>
<?php
/*
  admin/dashboard.php
  - Versión PHP del dashboard administrativo que incluye `php/proteger.php` para requerir sesión.
  - Muestra los mismos formularios y tabla que `admin/dashboard.html` pero protege el acceso por sesión.
*/

// Determinar si el usuario conectado es superadmin.
require_once __DIR__ . "/../php/db.php";
$is_super = false;
// Si el login estableció la bandera en sesión
if (isset($_SESSION['is_super']) && $_SESSION['is_super']) {
  $is_super = true;
} else {
  try {
    $db = get_db();
    if (isset($admin) && $admin) {
      $stmt = $db->prepare('SELECT is_super FROM admins WHERE username = :u LIMIT 1');
      $stmt->execute([':u' => $admin]);
      $r = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($r && intval($r['is_super']) === 1) {
        $is_super = true;
      } else {
        // revisar tabla superadmin (si existe)
        $stmt2 = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='superadmin'");
        $stmt2->execute();
        $exists = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
          $chk = $db->prepare('SELECT COUNT(1) as c FROM superadmin WHERE username = :u');
          $chk->execute([':u' => $admin]);
          $r2 = $chk->fetch(PDO::FETCH_ASSOC);
          if ($r2 && intval($r2['c']) > 0) $is_super = true;
        }
      }
    }
  } catch (Throwable $e) {
    // ignore db errors here; tratar como no superadmin
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Administrador - Gestión de Horas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="admin.css">
</head>

<body data-is-super-admin="<?php echo $is_super ? '1' : '0'; ?>">

<!-- BARRA SUPERIOR -->
<div class="top-bar">
  <span class="admin-user">👤 <?php echo $admin; ?></span>
  <a href="../php/logout.php">
    <button class="btn-logout">Cerrar sesión</button>
  </a>
</div>

<h1>Panel de Administración</h1>

<div class="admin-container">

  <!-- GESTIÓN DE DISPONIBILIDAD CON CALENDARIO -->
  <section class="card">
    <h2>Gestión de Disponibilidad</h2>

    <div class="calendar-controls">
      <select id="adminMonthSelect"></select>
      <select id="adminYearSelect"></select>
    </div>

    <div id="adminCalendar" class="admin-calendar"></div>

    <div class="legend">
      <span class="available box"></span> Activo
      <span class="disabled box"></span> Inactivo
    </div>

    <!-- Panel de configuración para el día seleccionado -->
    <div id="adminDayPanel" class="admin-day-panel hidden">
      <h3 id="adminDayTitle"></h3>

      <label>Hora Inicio</label>
      <input type="time" id="adminHoraInicio" value="08:00">

      <label>Hora Fin</label>
      <input type="time" id="adminHoraFin" value="17:00">

      <label>Bloque (minutos)</label>
      <input type="number" id="adminBloque" value="20" min="5">

      <label>Cupos máximos</label>
      <input type="number" id="adminCupos" value="30" min="1">

      <label>Tipo de trámite</label>
      <select id="adminTipoTramite">
        <option value="Renovación">Renovación</option>
        <option value="Licencia nueva">Licencia nueva</option>
        <option value="Ambos">Ambos</option>
      </select>

      <div class="admin-day-actions">
        <button id="adminBtnActivar" class="btn-primary">Activar Disponibilidad</button>
        <button id="adminBtnDesactivar" class="btn-danger">Desactivar Disponibilidad</button>
      </div>
    </div>
  </section>

  <?php if ($is_super): ?>
  <!-- CREAR ADMIN -->
  <section class="card">
    <h2>Crear Administrador</h2>

    <label>Nombre de usuario</label>
    <input type="text" id="newAdminUsername" placeholder="nuevo.admin">

    <label>Contraseña</label>
    <input type="password" id="newAdminPassword" placeholder="contraseña segura">

    <button id="btnCrearAdmin">Crear Administrador</button>
    <p style="font-size:0.9em;color:#666;margin-top:8px;">Solo un superadministrador puede crear nuevas cuentas de administrador.</p>
  </section>
  <!-- BORRAR ADMIN -->
  <section class="card">
    <h2>Borrar Administrador</h2>

    <label>Admins existentes</label>
    <select id="existingAdmins" style="width:100%;padding:8px;margin-top:6px;border:1px solid #ccc;border-radius:4px;"></select>

    <button id="btnBorrarAdmin" style="background:#c0392b;margin-top:10px;">Borrar Administrador seleccionado</button>
    <p style="font-size:0.9em;color:#666;margin-top:8px;">No es posible borrar a un superadmin ni a la cuenta actual.</p>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Exportar Registro</h2>

    <label>Desde</label>
    <input type="date" id="exportStartDate">

    <label>Hasta</label>
    <input type="date" id="exportEndDate">

    <button id="btnExportarExcel" style="margin-top:10px;">Descargar Excel</button>
    <p style="font-size:0.9em;color:#666;margin-top:8px;">Genera un archivo Excel con las personas ya atendidas en el rango de fechas elegido.</p>
  </section>

  <!-- SOLICITUDES -->
  <section class="card">
    <h2>Solicitudes Agendadas</h2>

    <div class="solicitudes-filters">
      <div class="filtro-fecha">
        <label>Filtrar por fecha</label>
        <input type="date" id="filtroFecha">
        <button id="btnFiltrar">Filtrar</button>
      </div>

      <!-- Paneles de select colocados debajo de la barra de filtrar por fecha -->
      <div class="filter-panel-row">
        <div class="filter-panel">
          <label>Filtros del día (hoy)</label>
          <select id="selectDayFilter">
            <option value="confirmado">Confirmados</option>
            <option value="atendido_cancelado">Atendidos/Cancelados</option>
          </select>
          <button id="btnApplyDayFilter">Aplicar (hoy)</button>
        </div>

        <div class="filter-panel">
          <label>Filtros (todo)</label>
          <select id="selectAllFilter">
            <option value="confirmado">Confirmados</option>
            <option value="atendido_cancelado">Atendidos/Cancelados</option>
          </select>
          <button id="btnApplyAllFilter">Aplicar (todo)</button>
        </div>

        <div class="filter-panel">
          <label>Filtrar por filtro</label>
          <select id="selectFilterByDate">
            <option value="confirmado">Confirmados</option>
            <option value="atendido_cancelado">Atendidos/Cancelados</option>
          </select>
          <button id="btnApplyFilterByDate">Aplicar (fecha seleccionada)</button>
        </div>
      </div>
    </div>

    <div class="tabla-wrapper">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora Inicio</th>
            <th>Hora Fin</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>RUT</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Trámite</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tablaSolicitudes"></tbody>
      </table>
    </div>
  </section>

</div>

<!-- MODAL -->
<div id="modalEditar" class="modal hidden">
  <div class="modal-content">
    <h3>Editar Solicitud</h3>

    <label>Fecha</label>
    <input type="date" id="editFecha">

    <label>Hora inicio</label>
    <input type="time" id="editHoraInicio">

    <label>Hora fin</label>
    <input type="time" id="editHoraFin">

    <label>Correo</label>
    <input type="email" id="editCorreo">

    <label>Teléfono</label>
    <input type="tel" id="editTelefono">

    <label>Nombres</label>
    <input type="text" id="editNombres">

    <label>Apellidos</label>
    <input type="text" id="editApellidos">

    <label>Tipo de trámite</label>
    <select id="editTipo">
      <option value="Renovación">Renovación</option>
      <option value="Licencia nueva">Licencia nueva</option>
    </select>

    <div class="modal-actions">
      <button class="btn-primary" id="btnGuardarCambios">Guardar</button>
      <button class="btn-secondary" id="btnCerrarModal">Cancelar</button>
    </div>
  </div>
</div>

<script src="admin.js"></script>
</body>
</html>
