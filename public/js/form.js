/**
 * public/js/form.js
 * Lógica principal del formulario de agendamiento
 * 
 * Flujo completo:
 * 1. Usuario selecciona trámite, fecha, hora (calendario.js)
 * 2. Se habilitan campos de datos personales
 * 3. Validaciones: validarRUT(), validarTelefono(), validarCorreo()
 * 4. En submit: crear bloqueo temporal (15 min)
 * 5. Mostrar modal de confirmación con temporizador
 * 6. Usuario confirma o cancela
 * 7. Si confirma: guardarAgenda() → agendamiento confirmado
 * 8. Si cancela: liberarBloqueo() → libera la hora
 * 
 * Manejo de estado:
 * - datosSolicitante: Fieldset bloqueado hasta que hay trámite+fecha+hora
 * - lastBloqueo: Almacena referencia al bloqueo creado
 * - page-timer: Sesión de 15 min (si expira, libera automáticamente)
 * 
 * Dependencias: calendar.js, api.js, ui.js
 */

/*
    public/js/form.js
    - Contiene la lógica del formulario de agendamiento.
    - Validaciones: `validarRUT`, `validarTelefono`, `validarCorreo`.
    - Manejo de estado: `lastBloqueo` (bloqueo temporal creado en el servidor).
    - Flujo principal en el `onsubmit` del formulario:
        1) Validar campos.
        2) Consultar si existe cita previa con `obtener_por_rut`.
        3) Crear bloqueo temporal con `crearBloqueo` antes de confirmar.
        4) Confirmar y llamar a `guardarAgenda`.
    - Hooks: libera bloqueo en `onModalCancel` y al expirar temporizador de página.
*/

const rutInput = document.getElementById("rut");
const telefonoInput = document.getElementById("telefono");
const correoInput = document.getElementById("correo");
const nombresInput = document.getElementById("nombres");
const apellidosInput = document.getElementById("apellidos");
const tipoTramiteSelect = document.getElementById("tipoTramite");
const datosFieldset = document.getElementById("datosSolicitante");
let lastBloqueo = null;

import {
    selectedDay,
    selectedHour,
    initCalendar
} from "./calendar.js";

import {
    guardarAgenda,
    crearBloqueo
} from "./api.js";

import {
    showModal,
    iniciarTemporizadorPagina,
    detenerTemporizadorPagina,
    onModalCancel
} from "./ui.js";
import { liberarBloqueo } from "./api.js";

/* ===== VALIDACIONES ===== */
function validarRUT(rut) {
    rut = rut.replace(/\./g, "").replace(/-/g, "");
    if (!/^\d{7,8}[0-9kK]$/.test(rut)) return false;

    let cuerpo = rut.slice(0, -1);
    let dv = rut.slice(-1).toUpperCase();

    let suma = 0, multiplo = 2;
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += multiplo * cuerpo[i];
        multiplo = multiplo < 7 ? multiplo + 1 : 2;
    }

    let dvEsperado = 11 - (suma % 11);
    dvEsperado = dvEsperado === 11 ? "0" :
                 dvEsperado === 10 ? "K" :
                 dvEsperado.toString();

    return dv === dvEsperado;
}

function validarTelefono(tel) {
    return /^9\d{8}$/.test(tel);
}

function validarCorreo(correo) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);
}

/* ===== BLOQUEO DE CAMPOS ===== */
function bloquearFormulario() {
    datosFieldset.classList.add("disabled-fieldset");
    datosFieldset.classList.remove("enabled-fieldset");

    datosFieldset.querySelectorAll("input").forEach(i => i.disabled = true);
}

function habilitarFormulario() {
    datosFieldset.classList.remove("disabled-fieldset");
    datosFieldset.classList.add("enabled-fieldset");

    datosFieldset.querySelectorAll("input").forEach(i => i.disabled = false);
}

/* Inicialmente bloqueado */
bloquearFormulario();

/* Se habilita SOLO si hay trámite + fecha + hora */
function verificarDesbloqueo() {
    if (tipoTramiteSelect.value && selectedDay && selectedHour) {
        habilitarFormulario();
    }
}

/* Detectar cambios */
tipoTramiteSelect.addEventListener("change", () => {
    bloquearFormulario();
    initCalendar();
});

/* Llamado desde calendar.js */
window.verificarDesbloqueo = verificarDesbloqueo;

/* ===== FORM ===== */
document.getElementById("formAgendamiento").onsubmit = async e => {
    e.preventDefault();

    // Nota: no detenemos ni reiniciamos el temporizador de página aquí.

    const rut = rutInput.value;
    const tel = telefonoInput.value;
    const correo = correoInput.value;
    const nombres = nombresInput ? nombresInput.value.trim() : "";
    const apellidos = apellidosInput ? apellidosInput.value.trim() : "";
    const tipoTramite = tipoTramiteSelect.value;

    if (!tipoTramite || !selectedDay || !selectedHour) {
        showModal("Error", "Debe seleccionar trámite, fecha y hora");
        return;
    }

    if (!validarRUT(rut)) {
        showModal("Error", "RUT inválido");
        return;
    }

    if (!validarTelefono(tel)) {
        showModal("Error", "Teléfono inválido");
        return;
    }

    if (!validarCorreo(correo)) {
        showModal("Error", "Correo inválido");
        return;
    }

    if (!nombres) {
        showModal("Error", "Ingrese nombres");
        return;
    }

    if (!apellidos) {
        showModal("Error", "Ingrese apellidos");
        return;
    }

    // Antes de crear bloqueo: verificar si el RUT ya tiene una cita confirmada
    async function obtenerPorRut(rutVal) {
        try {
            const r = await fetch('../php/obtener_por_rut.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ rut: rutVal })
            });
            return await r.json();
        } catch (err) {
            return null;
        }
    }

    const existe = await obtenerPorRut(rut);
    if (existe && existe.ok && existe.row) {
        const old = existe.row;
        showModal(
            "Cita existente",
            `Usted ya tiene una cita confirmada el ${old.fecha} a las ${old.hora}. Si desea cambiarla, por favor acérquese presencialmente al departamento.`
        );
        return;
    }

    /* ===== BLOQUEO TEMPORAL ===== */
    const bloqueoRes = await crearBloqueo({ fecha: selectedDay, hora: selectedHour, rut });
    if (!bloqueoRes || bloqueoRes.error) {
        showModal('Error', bloqueoRes && bloqueoRes.error ? bloqueoRes.error : 'No se pudo crear bloqueo');
        return;
    }

    lastBloqueo = { fecha: selectedDay, hora: selectedHour, rut, token: bloqueoRes.token };
    // Nota: no iniciamos un contador de confirmación adicional.

    showModal(
        "Confirmar",
        `Confirmar ${tipoTramite} el ${selectedDay} a las ${selectedHour}`,
        async () => {
            // No hay contador de confirmación que detener.

            const [h, m] = selectedHour.split(":").map(Number);
            const horaFin = `${String(h).padStart(2,"0")}:${String(m + 20).padStart(2,"0")}`;

            const res = await guardarAgenda({
                fecha: selectedDay,
                horaInicio: selectedHour,
                horaFin,
                rut,
                nombres,
                apellidos,
                telefono: tel,
                correo,
                tipoTramite,
                token: lastBloqueo && lastBloqueo.token ? lastBloqueo.token : null
            });

            if (res.error) {
                showModal("Error", res.error);
                return;
            }

            // hora confirmada: limpiar bloqueo local
            try { if (lastBloqueo) { await liberarBloqueo(lastBloqueo); } } catch (err) { /* noop */ }
            lastBloqueo = null;

            showModal("Éxito", "Hora registrada");
            initCalendar();
            bloquearFormulario();
        }
    );
};

// Iniciar temporizador de sesión al cargar la página (15 minutos = 900s)
iniciarTemporizadorPagina(900, async () => {
    // Al expirar: liberar bloqueo si existe y redirigir
    try {
        if (lastBloqueo) {
            await liberarBloqueo(lastBloqueo);
            lastBloqueo = null;
        }
    } catch (err) { /* noop */ }
    showModal('Tiempo agotado', 'Pasó mucho tiempo en la página. Será redirigido.', () => {
        window.location.href = 'timeout.html';
    });
    // también deshabilitar formulario
    bloquearFormulario();
});

// Liberar bloqueo local cuando el usuario pulse "Cancelar" en cualquier modal
onModalCancel(async () => {
    try {
        if (typeof lastBloqueo !== 'undefined' && lastBloqueo) {
            await liberarBloqueo(lastBloqueo);
            lastBloqueo = null;
        }
    } catch (err) { /* noop */ }
    // No reiniciamos el temporizador de página: su duración es única por carga.
});

initCalendar();
