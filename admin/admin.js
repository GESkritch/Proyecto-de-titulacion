/*
  admin/admin.js
  - Lógica del panel administrativo.
  - Funciones principales:
    - `cargarSolicitudes()` : obtiene filas desde `php/obtener_solicitudes.php`.
    - `renderTabla(data)` : renderiza la tabla de solicitudes con acciones (Editar, Marcar, Eliminar).
    - `abrirModal(...)` y `btnGuardar` handler : editar solicitud (llama a `php/editar_solicitud.php`).
    - Acciones: `marcarCancelado`, `marcarConfirmado`, `marcarAtendido` que llaman a los endpoints correspondientes.
*/

console.log('admin.js loaded');

document.addEventListener('DOMContentLoaded', () => {
  console.log('admin.js DOMContentLoaded');

  let solicitudActual = null;
  let solicitudesGlobal = [];
  let disponibilidadGlobal = {};
  let selectedAdminDay = null;

  /* ================== REFERENCIAS DOM ================== */
  const adminMonthSelect = document.getElementById('adminMonthSelect');
  const adminYearSelect = document.getElementById('adminYearSelect');
  const adminCalendar = document.getElementById('adminCalendar');
  const adminDayPanel = document.getElementById('adminDayPanel');
  const adminDayTitle = document.getElementById('adminDayTitle');
  const adminHoraInicio = document.getElementById('adminHoraInicio');
  const adminHoraFin = document.getElementById('adminHoraFin');
  const adminBloque = document.getElementById('adminBloque');
  const adminCupos = document.getElementById('adminCupos');
  const adminTipoTramite = document.getElementById('adminTipoTramite');
  const adminBtnActivar = document.getElementById('adminBtnActivar');
  const adminBtnDesactivar = document.getElementById('adminBtnDesactivar');
  const btnCrearAdmin = document.getElementById('btnCrearAdmin');
  const newAdminUsername = document.getElementById('newAdminUsername');
  const newAdminPassword = document.getElementById('newAdminPassword');

  const btnFiltrar = document.getElementById('btnFiltrar');
  const selectDayFilter = document.getElementById('selectDayFilter');
  const btnApplyDayFilter = document.getElementById('btnApplyDayFilter');
  const selectAllFilter = document.getElementById('selectAllFilter');
  const btnApplyAllFilter = document.getElementById('btnApplyAllFilter');
  const selectFilterByDate = document.getElementById('selectFilterByDate');
  const btnApplyFilterByDate = document.getElementById('btnApplyFilterByDate');

  const filtroFecha = document.getElementById('filtroFecha');
  const tablaSolicitudes = document.getElementById('tablaSolicitudes');
  const tableEl = tablaSolicitudes ? tablaSolicitudes.closest('table') : null;
  const existingAdmins = document.getElementById('existingAdmins');
  const btnBorrarAdmin = document.getElementById('btnBorrarAdmin');
  const exportStartDate = document.getElementById('exportStartDate');
  const exportEndDate = document.getElementById('exportEndDate');
  const btnExportarExcel = document.getElementById('btnExportarExcel');

  const modalEditar = document.getElementById('modalEditar');
  const btnCerrar = document.getElementById('btnCerrarModal');
  const btnGuardar = document.getElementById('btnGuardarCambios');

  const editFecha = document.getElementById('editFecha');
  const editHoraInicio = document.getElementById('editHoraInicio');
  const editHoraFin = document.getElementById('editHoraFin');
  const editCorreo = document.getElementById('editCorreo');
  const editTipo = document.getElementById('editTipo');
  const editTelefono = document.getElementById('editTelefono');
  const editNombres = document.getElementById('editNombres');
  const editApellidos = document.getElementById('editApellidos');

  /* ================== CALENDARIO ADMIN ================== */
  
  // Verificar que los elementos del calendario existan
  if (!adminMonthSelect || !adminYearSelect || !adminCalendar) {
    console.error('Error: No se encontraron los elementos del calendario admin. Asegúrate de que el HTML tenga los IDs correctos.');
    return;
  }

  // Meses
  const months = [
    "Enero","Febrero","Marzo","Abril","Mayo","Junio",
    "Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"
  ];

  months.forEach((m, i) => {
    adminMonthSelect.innerHTML += `<option value="${i}">${m}</option>`;
  });

  const currentDate = new Date();
  const currentYear = currentDate.getFullYear();
  for (let y = currentYear; y <= currentYear + 1; y++) {
    adminYearSelect.innerHTML += `<option value="${y}">${y}</option>`;
  }

  adminMonthSelect.value = currentDate.getMonth();
  adminYearSelect.value = currentYear;

  // Cargar disponibilidades desde el servidor
  /**
   * Obtiene todas las disponibilidades activas desde el endpoint PHP
   * y las almacena en `disponibilidadGlobal` para usarlas en el calendario.
   */
  async function cargarDisponibilidades() {
    try {
      const res = await fetch('../php/obtener_disponibilidad.php');
      disponibilidadGlobal = await res.json();
    } catch (err) {
      console.error('Error al cargar disponibilidades:', err);
    }
  }

  // Renderizar calendario
  /**
   * Construye y renderiza el calendario del mes seleccionado.
   * - Crea el encabezado con los días de la semana.
   * - Genera las celdas de días basado en el mes/año seleccionado.
   * - Aplica estilos según disponibilidad (verde si activo, gris si inactivo).
   * - Agrega event listener a cada día para permitir selección.
   */
  async function renderAdminCalendar() {
    adminCalendar.innerHTML = '';

    const month = +adminMonthSelect.value;
    const year = +adminYearSelect.value;

    const header = document.createElement('div');
    header.className = 'calendar-header';
    ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].forEach(d => {
      const div = document.createElement('div');
      div.textContent = d;
      header.appendChild(div);
    });
    adminCalendar.appendChild(header);

    const daysGrid = document.createElement('div');
    daysGrid.className = 'calendar-days';

    const firstDay = new Date(year, month, 1).getDay();
    const offset = firstDay === 0 ? 6 : firstDay - 1;
    for (let i = 0; i < offset; i++) daysGrid.appendChild(document.createElement('div'));

    const lastDay = new Date(year, month + 1, 0).getDate();

    for (let d = 1; d <= lastDay; d++) {
      const fecha = `${year}-${String(month + 1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;
      const div = document.createElement('div');
      div.textContent = d;

      const config = disponibilidadGlobal[fecha];
      if (config && config.activo === '1') {
        div.className = 'day available';
      } else {
        div.className = 'day disabled';
      }

      div.onclick = () => selectAdminDay(div, fecha);
      daysGrid.appendChild(div);
    }

    adminCalendar.appendChild(daysGrid);
  }

  // Seleccionar día en el calendario
  /**
   * Se ejecuta cuando el usuario hace click en un día del calendario.
   * - Remueve la clase "selected" de otros días.
   * - Marca el día clickeado como seleccionado.
   * - Muestra el panel de configuración (.admin-day-panel).
   * - Carga los datos de disponibilidad si existen para ese día.
   * - Muestra/oculta botones de activar/desactivar según corresponda.
   * 
   * @param {HTMLElement} dayDiv - Elemento del día clickeado
   * @param {string} fecha - Fecha en formato YYYY-MM-DD
   */
  function selectAdminDay(dayDiv, fecha) {
    document.querySelectorAll('#adminCalendar .day').forEach(d => d.classList.remove('selected'));
    dayDiv.classList.add('selected');
    selectedAdminDay = fecha;

    // Mostrar panel de configuración
    adminDayPanel.classList.remove('hidden');
    adminDayTitle.textContent = `Configurar: ${fecha}`;

    // Cargar datos si ya existe disponibilidad
    const config = disponibilidadGlobal[fecha];
    if (config) {
      adminHoraInicio.value = config.inicio || '08:00';
      adminHoraFin.value = config.fin || '17:00';
      adminBloque.value = config.bloque_min || 20;
      adminCupos.value = config.cupos || 30;
      adminTipoTramite.value = config.tipo || 'Ambos';
      adminBtnActivar.style.display = 'none';
      adminBtnDesactivar.style.display = 'inline-block';
    } else {
      adminHoraInicio.value = '08:00';
      adminHoraFin.value = '17:00';
      adminBloque.value = 20;
      adminCupos.value = 30;
      adminTipoTramite.value = 'Ambos';
      adminBtnActivar.style.display = 'inline-block';
      adminBtnDesactivar.style.display = 'none';
    }
  }

  // Activar disponibilidad
  /**
   * Guarda una nueva disponibilidad para el día seleccionado.
   * - Recolecta datos del formulario (hora inicio/fin, bloque, cupos, tipo).
   * - Envía POST a `php/guardar_disponibilidad_rango.php`.
   * - Recarga la lista de disponibilidades y re-renderiza el calendario.
   * - Oculta el panel de configuración después de guardar.
   */
  if (adminBtnActivar) {
    adminBtnActivar.addEventListener('click', async () => {
      if (!selectedAdminDay) return;

      const data = {
        inicio: selectedAdminDay,
        fin: selectedAdminDay,
        horaInicio: adminHoraInicio.value,
        horaFin: adminHoraFin.value,
        bloque: adminBloque.value,
        cupos: adminCupos.value,
        tipo: adminTipoTramite.value
      };

      try {
        const res = await fetch('../php/guardar_disponibilidad_rango.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        const j = await res.json();
        if (j.ok) {
          alert('Disponibilidad activada correctamente');
          await cargarDisponibilidades();
          await renderAdminCalendar();
          adminDayPanel.classList.add('hidden');
        } else {
          alert('Error: ' + (j.error || 'No se pudo guardar'));
        }
      } catch (err) {
        alert('Error de conexión');
      }
    });
  }

  // Desactivar disponibilidad
  /**
   * Marca una disponibilidad como inactiva para el día seleccionado.
   * - Solicita confirmación al usuario.
   * - Envía POST a `php/desactivar_rango.php` para marcar estado = 0.
   * - Recarga la lista de disponibilidades y re-renderiza el calendario.
   * - Oculta el panel de configuración después de desactivar.
   */
  if (adminBtnDesactivar) {
    adminBtnDesactivar.addEventListener('click', async () => {
      if (!selectedAdminDay) return;

      if (!confirm('¿Desactivar disponibilidad para esta fecha?')) return;

      const data = {
        inicio: selectedAdminDay,
        fin: selectedAdminDay,
        tipo: 'Todos'
      };

      try {
        const res = await fetch('../php/desactivar_rango.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        const j = await res.json();
        if (j.ok) {
          alert('Disponibilidad desactivada correctamente');
          await cargarDisponibilidades();
          await renderAdminCalendar();
          adminDayPanel.classList.add('hidden');
        } else {
          alert('Error: ' + (j.error || 'No se pudo desactivar'));
        }
      } catch (err) {
        alert('Error de conexión');
      }
    });
  }

  // Event listeners para cambios de mes/año
  /**
   * Reactualiza el calendario cuando se cambia el mes o año seleccionado.
   */
  adminMonthSelect.addEventListener('change', renderAdminCalendar);
  adminYearSelect.addEventListener('change', renderAdminCalendar);

  // Inicializar calendario
  /**
   * Carga las disponibilidades y renderiza el calendario al cargar la página.
   */
  cargarDisponibilidades().then(() => renderAdminCalendar());

  /* ================== CREAR ADMIN ================== */
  /**
   * Crea una nueva cuenta de administrador.
   * - Solicita usuario y contraseña.
   * - Envía POST a `php/crear_admin.php`.
   * - Limpia los campos y recarga la lista de admins después.
   */
  if (btnCrearAdmin) btnCrearAdmin.addEventListener('click', async () => {
    const username = (newAdminUsername && newAdminUsername.value) ? newAdminUsername.value.trim() : '';
    const password = (newAdminPassword && newAdminPassword.value) ? newAdminPassword.value : '';
    if (!username || !password) { alert('Ingrese usuario y contraseña'); return; }

    if (!confirm(`Crear cuenta admin "${username}"?`)) return;

    try {
      const res = await fetch("../php/crear_admin.php", {
        method: "POST",
        credentials: 'same-origin',
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({ username, password })
      });
      const j = await res.json();
      if (j && j.ok) {
        alert('Administrador creado correctamente');
        newAdminUsername.value = '';
        newAdminPassword.value = '';
        try { await loadAdmins(); } catch (e) { /* noop */ }
      } else {
        alert('Error: ' + (j && j.error ? j.error : 'No se pudo crear el admin'));
      }
    } catch (err) {
      alert('Error de conexión');
    }
  });

  /**
   * Elimina una cuenta de administrador existente.
   * - Solicita confirmación antes de proceder.
   * - Envía POST a `php/delete_admin.php`.
   * - Recarga la lista de admins después de la eliminación.
   */
  // Borrar admin seleccionado
  if (btnBorrarAdmin) btnBorrarAdmin.addEventListener('click', async () => {
    if (!existingAdmins) return;
    const username = existingAdmins.value;
    if (!username) { alert('Seleccione un admin'); return; }
    if (!confirm(`Borrar administrador "${username}"?`)) return;

    try {
      const res = await fetch('../php/delete_admin.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ username })
      });
      const j = await res.json();
      if (j && j.ok) {
        alert('Administrador borrado');
        await loadAdmins();
      } else {
        alert('Error: ' + (j && j.error ? j.error : 'No se pudo borrar'));
      }
    } catch (err) {
      alert('Error de conexión');
    }
  });

  /* ================== CARGAR SOLICITUDES ================== */
  /**
   * Carga las solicitudes desde el endpoint `php/obtener_solicitudes.php`
   * y almacena el resultado en la variable `solicitudesGlobal`.
   * Se usa para poblar los filtros y la tabla en el admin.
   */
  async function cargarSolicitudes() {
    const res = await fetch("../php/obtener_solicitudes.php");
    const data = await res.json();
    solicitudesGlobal = data;
  }

  /* ================== RENDER TABLA ================== */
  /**
   * Renderiza la tabla de solicitudes a partir del array `data`.
   * Crea filas y botones de acción (Editar, Marcar, Atendido) por cada entrada.
   */
  /**
   * Renderiza la tabla de solicitudes en el DOM con todos los detalles.
   * - Crea filas dinámicamente para cada solicitud.
   * - Colorea estados (confirmado=amarillo, atendido=verde, cancelado=rojo).
   * - Agrega botones de acción: Editar, Marcar cancelado, Marcar confirmado, Marcar atendido.
   * @param {Array} data - Array de solicitudes desde php/obtener_solicitudes.php
   */
  function renderTabla(data) {
    tablaSolicitudes.innerHTML = "";

    const isSuperAdmin = Boolean(document.body.dataset.isSuperAdmin === '1');

    data.forEach(s => {
      const tr = document.createElement('tr');

      const claseEstado =
        s.estado === 'confirmado' ? 'estado-confirmado' :
        s.estado === 'atendido' ? 'estado-atendido' :
        s.estado === 'cancelado' ? 'estado-cancelado' : '';

      const addTd = (text, className) => {
        const td = document.createElement('td');
        if (className) td.className = className;
        td.textContent = text || '';
        tr.appendChild(td);
      };

      addTd(s.fecha);
      addTd(s.hora_inicio);
      addTd(s.hora_fin);
      addTd(s.nombres);
      addTd(s.apellidos || '');
      addTd(s.rut);
      addTd(s.telefono);
      addTd(s.correo);
      addTd(s.tipo_tramite);
      addTd(s.estado, claseEstado);

      const tdActions = document.createElement('td');

      const btnEdit = document.createElement('button');
      btnEdit.textContent = 'Editar';
      btnEdit.addEventListener('click', () => abrirModal(
        s.id, s.fecha, s.hora_inicio, s.hora_fin,
        s.correo, s.tipo_tramite, s.telefono,
        s.nombres, s.apellidos
      ));

      const btnCancel = document.createElement('button');
      btnCancel.textContent = 'Marcar cancelado';
      btnCancel.addEventListener('click', () => marcarCancelado(s.id));

      const btnConfirm = document.createElement('button');
      btnConfirm.textContent = 'Marcar confirmado';
      btnConfirm.addEventListener('click', () => marcarConfirmado(s.id));

      const btnAtendido = document.createElement('button');
      btnAtendido.textContent = 'Atendido';
      btnAtendido.addEventListener('click', () => marcarAtendido(s.id));

      const btnEliminar = document.createElement('button');
      btnEliminar.textContent = 'Eliminar';
      btnEliminar.style.background = '#c0392b';
      btnEliminar.style.color = '#fff';
      btnEliminar.addEventListener('click', () => eliminarSolicitud(s.id));

      tdActions.append(btnEdit, btnCancel, btnConfirm, btnAtendido);
      if (isSuperAdmin) {
        tdActions.appendChild(btnEliminar);
      }
      tr.appendChild(tdActions);
      tablaSolicitudes.appendChild(tr);
    });
  }

  /* ================== FILTROS ================== */

  if (tableEl) tableEl.style.display = 'none';

  // Cargar datos UNA VEZ
  cargarSolicitudes();

  /**
   * Carga y actualiza la lista de administradores disponibles en el selector.
   * - Obtiene la lista desde php/list_admins.php
   * - Muestra (super) al lado si es administrador superusuario.
   * - Usado para el panel de eliminación de admins.
   */
  async function loadAdmins() {
    if (!existingAdmins) return;
    try {
      const res = await fetch('../php/list_admins.php', { credentials: 'same-origin' });
      if (!res.ok) { existingAdmins.innerHTML = '<option value="">(error al cargar)</option>'; return; }
      const data = await res.json();
      existingAdmins.innerHTML = '';
      data.forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.username;
        opt.textContent = a.username + (a.is_super == 1 ? ' (super)' : '');
        existingAdmins.appendChild(opt);
      });
    } catch (err) {
      existingAdmins.innerHTML = '<option value="">(error de conexión)</option>';
    }
  }
  loadAdmins();

  /**
   * Aplica filtro por fecha seleccionada en `filtroFecha`.
   * - Filtra solicitudes que coincidan con la fecha seleccionada.
   * - Muestra resultados en la tabla.
   */
  if (btnFiltrar) btnFiltrar.addEventListener('click', () => {
    const fechaVal = filtroFecha.value;
    if (!fechaVal) return;

    const filtradas = solicitudesGlobal.filter(s => s.fecha === fechaVal);
    renderTabla(filtradas);
    tableEl.style.display = 'table';
  });

  /**
   * Filtra solicitudes por estado para la fecha actual (hoy).
   * - Detecta "confirmado" o "otros" (atendido/cancelado) desde selectDayFilter.
   * - Solo muestra solicitudes de hoy.
   */
  // Handler: aplica el filtro seleccionado en `selectDayFilter` pero siempre para la fecha de hoy
  if (btnApplyDayFilter) btnApplyDayFilter.addEventListener('click', () => {
    const today = new Date().toISOString().slice(0,10);
    const sel = (selectDayFilter && selectDayFilter.value) ? selectDayFilter.value : 'confirmado';
    let filtradas = [];
    if (sel === 'confirmado') {
      filtradas = solicitudesGlobal.filter(s => s.fecha === today && (s.estado || '').toLowerCase() === 'confirmado');
    } else {
      filtradas = solicitudesGlobal.filter(s => s.fecha === today && (((s.estado||'').toLowerCase() === 'atendido') || ((s.estado||'').toLowerCase() === 'cancelado')));
    }
    renderTabla(filtradas);
    tableEl.style.display = 'table';
  });

  /**
   * Filtra solicitudes por estado sobre TODAS las fechas sin restricción temporal.
   * - Detecta "confirmado" o "otros" (atendido/cancelado) desde selectAllFilter.
   */
  // Handler: aplica el filtro seleccionado en `selectAllFilter` sobre todas las solicitudes (sin filtrar por fecha)
  if (btnApplyAllFilter) btnApplyAllFilter.addEventListener('click', () => {
    const sel = (selectAllFilter && selectAllFilter.value) ? selectAllFilter.value : 'confirmado';
    let filtradas = [];
    if (sel === 'confirmado') {
      filtradas = solicitudesGlobal.filter(s => (s.estado || '').toLowerCase() === 'confirmado');
    } else {
      filtradas = solicitudesGlobal.filter(s => {
        const est = (s.estado||'').toLowerCase();
        return est === 'atendido' || est === 'cancelado';
      });
    }
    renderTabla(filtradas);
    tableEl.style.display = 'table';
  });

  /**
   * Filtra solicitudes por estado para una fecha específica seleccionada.
   * - Requiere que se haya seleccionado una fecha en filtroFecha.
   * - Detecta "confirmado" o "otros" (atendido/cancelado) desde selectFilterByDate.
   */
  // Handler: aplica el filtro seleccionado en `selectFilterByDate` para la fecha establecida en `#filtroFecha`
  if (btnApplyFilterByDate) btnApplyFilterByDate.addEventListener('click', () => {
    const fechaVal = filtroFecha.value;
    if (!fechaVal) { alert('Seleccione una fecha primero'); return; }
    const sel = (selectFilterByDate && selectFilterByDate.value) ? selectFilterByDate.value : 'confirmado';
    let filtradas = [];
    if (sel === 'confirmado') {
      filtradas = solicitudesGlobal.filter(s => s.fecha === fechaVal && (s.estado || '').toLowerCase() === 'confirmado');
    } else {
      filtradas = solicitudesGlobal.filter(s => s.fecha === fechaVal && (((s.estado||'').toLowerCase() === 'atendido') || ((s.estado||'').toLowerCase() === 'cancelado')));
    }
    renderTabla(filtradas);
    tableEl.style.display = 'table';
  });

  if (btnExportarExcel) btnExportarExcel.addEventListener('click', async () => {
    const inicio = exportStartDate ? exportStartDate.value : '';
    const fin = exportEndDate ? exportEndDate.value : '';
    if (!inicio || !fin) {
      alert('Seleccione una fecha de inicio y una de término');
      return;
    }

    try {
      const res = await fetch('../php/exportar_excel.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fecha_inicio: inicio, fecha_fin: fin })
      });

      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        throw new Error(errData.error || 'No se pudo exportar');
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `registro_${inicio}_a_${fin}.xlsx`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    } catch (err) {
      alert(err.message || 'Error al generar el archivo');
    }
  });

  /* ================== MODAL ================== */
  /**
   * Abre el modal de edición y rellena los campos con los valores
   * de la solicitud seleccionada (recibe todos los campos necesarios).
   */
  function abrirModal(id, fecha, horaInicio, horaFin, correo, tipo, telefono, nombres, apellidos) {
    solicitudActual = id;
    editFecha.value = fecha;
    editHoraInicio.value = horaInicio;
    editHoraFin.value = horaFin;
    editCorreo.value = correo || '';
    editTipo.value = tipo || '';
    editTelefono.value = telefono || '';
    editNombres.value = nombres || '';
    editApellidos.value = apellidos || '';
    modalEditar.classList.remove("hidden");
  }

  /**
   * Cierra el modal de edición agregando la clase "hidden".
   */
  if (btnCerrar) btnCerrar.addEventListener('click', () => {
    modalEditar.classList.add("hidden");
  });

  /**
   * Guarda los cambios realizados en la solicitud.
   * - Envía POST a php/editar_solicitud.php con los valores del modal.
   * - Recarga solicitudes y tabla después de guardar.
   */
  if (btnGuardar) btnGuardar.addEventListener('click', async () => {
    const data = {
      id: solicitudActual,
      fecha: editFecha.value,
      hora_inicio: editHoraInicio.value,
      hora_fin: editHoraFin.value,
      correo: editCorreo.value,
      tipo: editTipo.value,
      telefono: editTelefono.value,
      nombres: editNombres.value,
      apellidos: editApellidos.value
    };

    await fetch("../php/editar_solicitud.php", {
      method: "POST",
      credentials: 'same-origin',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(data)
    });

    modalEditar.classList.add("hidden");
    await cargarSolicitudes();
    renderTabla(solicitudesGlobal);
  });

  /* ================== ESTADOS ================== */
  /**
   * Marca una solicitud como 'cancelado' llamando al endpoint
   * `php/actualizar_estado.php` y recarga la lista de solicitudes.
   */
  async function marcarCancelado(id) {
    if (!confirm("¿Marcar esta solicitud como cancelada?")) return;

    await fetch("../php/actualizar_estado.php", {
      method: "POST",
      credentials: 'same-origin',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id, estado: 'cancelado' })
    });

    await cargarSolicitudes();
    renderTabla(solicitudesGlobal);
  }

  /* MARCAR COMO CONFIRMADO (por si el admin se equivoca) */
  /**
   * Marca una solicitud como 'confirmado' llamando a `php/actualizar_estado.php`
   * y refresca la tabla tras la actualización.
   */
  /**
   * Marca una solicitud como 'confirmado' llamando al endpoint
   * `php/actualizar_estado.php` y recarga la lista de solicitudes.
   * @param {number} id - ID de la solicitud a confirmar
   */
  async function marcarConfirmado(id) {
    if (!confirm("¿Marcar esta solicitud como confirmada?")) return;

    await fetch("../php/actualizar_estado.php", {
      method: "POST",
      credentials: 'same-origin',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id, estado: 'confirmado' })
    });

    await cargarSolicitudes();
    renderTabla(solicitudesGlobal);
  }

  /**
   * Marca una solicitud como 'atendido' llamando a `php/marcar_atendido.php`
   * y recarga la lista de solicitudes.
   */
  async function marcarAtendido(id) {
    await fetch("../php/marcar_atendido.php", {
      method: "POST",
      credentials: 'same-origin',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id })
    });

    await cargarSolicitudes();
    renderTabla(solicitudesGlobal);
  }

  async function eliminarSolicitud(id) {
    if (!confirm('¿Eliminar esta solicitud? Esta acción solo debe usarse en casos de emergencia o error.')) return;

    const res = await fetch('../php/eliminar_solicitud.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
      alert(data.error || 'No se pudo eliminar la solicitud');
      return;
    }

    await cargarSolicitudes();
    renderTabla(solicitudesGlobal);
  }
});
