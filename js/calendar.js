/**
 * public/js/calendar.js
 * Genera y renderiza el calendario interactivo de agendamiento
 * 
 * Exportados:
 * - selectedDay, selectedHour: Estado de selección del usuario
 * - agendaOcupada: Mapa { fecha: [horas_ocupadas] }
 * - initCalendar(): Carga datos del servidor y renderiza
 * 
 * Funciones internas:
 * - renderCalendar(): Dibuja cuadrícula de días según disponibilidad
 * - renderHours(fecha): Genera botones de horarios disponibles
 * - selectDay(fecha): Marca día seleccionado y muestra horarios
 * 
 * Lógica de colores:
 * - Verde (available): Tiene disponibilidad activa
 * - Gris (full): Sin cupos disponibles
 * - Deshabilitado (disabled): Fecha fuera de rango o no laborable
 * 
 * Dependencias: api.js (obtenerAgenda, obtenerDisponibilidad, obtenerBloqueos)
 */

/*
  public/js/calendar.js
  - Genera y renderiza el calendario mensual.
  - Exporta `selectedDay` y `selectedHour` (valores seleccionados por el usuario).
  - Funciones principales:
    - `initCalendar()` : carga `agendaOcupada`, `disponibilidad` y `bloqueos` desde la API.
    - `renderCalendar()` : dibuja los días disponibles según `disponibilidad` y tipo de trámite.
    - `renderHours(fecha)` : genera botones de horarios tomando en cuenta `agendaOcupada` y `bloqueos`.
    - `selectDay()` : marca día seleccionado y llama a `renderHours`.
*/

import {
    obtenerAgenda,
    obtenerDisponibilidad,
    obtenerBloqueos
} from "./api.js";

export let selectedDay = null;
export let selectedHour = null;

export let agendaOcupada = {};
let disponibilidad = {};
let bloqueos = {};

const calendar = document.getElementById("calendar");
const monthSelect = document.getElementById("monthSelect");
const yearSelect = document.getElementById("yearSelect");
const horasContainer = document.getElementById("horasContainer");
const horasGrid = document.getElementById("horasGrid");
const tipoSelect = document.getElementById("tipoTramite");

const currentDate = new Date();

/* ===== MESES ===== */
const months = [
    "Enero","Febrero","Marzo","Abril","Mayo","Junio",
    "Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"
];

months.forEach((m, i) => {
    monthSelect.innerHTML += `<option value="${i}">${m}</option>`;
});

const currentYear = currentDate.getFullYear();
for (let y = currentYear; y <= currentYear + 1; y++) {
    yearSelect.innerHTML += `<option value="${y}">${y}</option>`;
}

monthSelect.value = currentDate.getMonth();
yearSelect.value = currentYear;

monthSelect.onchange = yearSelect.onchange = renderCalendar;
tipoSelect.onchange = () => {
    selectedDay = null;
    selectedHour = null;
    horasContainer.style.display = "none";
    renderCalendar();
};

/* ===== INIT ===== */
export async function initCalendar() {
    agendaOcupada = await obtenerAgenda();
    disponibilidad = await obtenerDisponibilidad();
    bloqueos = await obtenerBloqueos();
    renderCalendar();
}

/* ===== CALENDARIO ===== */
function renderCalendar() {
    calendar.innerHTML = "";

    const month = +monthSelect.value;
    const year = +yearSelect.value;
    const tramite = tipoSelect.value;

    const header = document.createElement("div");
    header.className = "calendar-header";

    ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"].forEach(d => {
        const div = document.createElement("div");
        div.textContent = d;
        header.appendChild(div);
    });

    calendar.appendChild(header);

    const daysGrid = document.createElement("div");
    daysGrid.className = "calendar-days";

    const firstDay = new Date(year, month, 1).getDay();
    const offset = firstDay === 0 ? 6 : firstDay - 1;
    for (let i = 0; i < offset; i++) daysGrid.appendChild(document.createElement("div"));

    const lastDay = new Date(year, month + 1, 0).getDate();

    for (let d = 1; d <= lastDay; d++) {
        const fecha = `${year}-${String(month + 1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;
        const div = document.createElement("div");
        div.textContent = d;

        const config = disponibilidad[fecha];

        if (
            config &&
            (config.tipo === "Ambos" || config.tipo === tramite)
        ) {
            div.className = "day available";
            div.onclick = () => selectDay(div, fecha);
        } else {
            div.className = "day disabled";
        }

        daysGrid.appendChild(div);
    }

    calendar.appendChild(daysGrid);
}

/* ===== SELECCIÓN DE DÍA ===== */
function selectDay(dayDiv, fecha) {
    document.querySelectorAll(".day").forEach(d => d.classList.remove("selected"));
    dayDiv.classList.add("selected");

    selectedDay = fecha;
    selectedHour = null;

    renderHours(fecha);
}

/* ===== HORARIOS ===== */
function renderHours(fecha) {
    horasContainer.style.display = "block";
    horasGrid.innerHTML = "";

    const config = disponibilidad[fecha];
    const ocupadas = agendaOcupada[fecha] || [];
    const bloqueadas = bloqueos[fecha] || [];

    const [hIni, mIni] = config.inicio.split(":").map(Number);
    const [hFin, mFin] = config.fin.split(":").map(Number);
    const bloque = config.bloque_min;

    let start = hIni * 60 + mIni;
    const end = hFin * 60 + mFin;

    while (start + bloque <= end) {
        const h = String(Math.floor(start / 60)).padStart(2,"0");
        const m = String(start % 60).padStart(2,"0");
        const hora = `${h}:${m}`;

        const btn = document.createElement("button");
        btn.classList.add("hora-btn");
        btn.textContent = hora;

        if (ocupadas.includes(hora) || bloqueadas.includes(hora)) {
            btn.classList.add("full");
            btn.disabled = true;
        } else {
            btn.onclick = () => {
                document.querySelectorAll(".hora-btn").forEach(b => b.classList.remove("selected"));
                btn.classList.add("selected");

                selectedHour = hora;

                /* 👇 AQUÍ DESBLOQUEAMOS EL FORMULARIO */
                if (window.verificarDesbloqueo) {
                    window.verificarDesbloqueo();
                }
            };
        }

        horasGrid.appendChild(btn);
        start += bloque;
    }
}
