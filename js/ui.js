/**
 * public/js/ui.js
 * Utilidades de interfaz de usuario compartidas
 * 
 * Temporizadores:
 * - iniciarContador(seg, onExpire): Para modal de confirmación (típicamente 120 segundos)
 * - iniciarTemporizadorPagina(seg, onExpire): Para sesión de página (15 min = 900 seg)
 * - detenerContador() / detenerTemporizadorPagina(): Limpia timers
 * 
 * Modales:
 * - showModal(title, message, onConfirm): Muestra modal de confirmación
 * - onModalCancel(fn): Hook para ejecutar código cuando usuario cancela modal
 * 
 * Formato de tiempo: Convierte segundos a formato legible (e.g., "2m 30s")
 * 
 * Dependencias: DOM index.html (#page-timer, #modal-overlay, etc.)
 */

/*
    public/js/ui.js
    - Utilidades de interfaz usadas por el frontend:
        - `iniciarContador(segundos, onExpire)` / `detenerContador()` : temporizador usado dentro de modales.
        - `iniciarTemporizadorPagina(segundos, onExpire)` / `detenerTemporizadorPagina()` : temporizador visible en la página (sesión de 15min por carga).
        - `showModal(title, message, onConfirm)` : muestra modal de confirmación y enlaza botones.
        - `onModalCancel(fn)` : hook para actuar cuando el usuario cancela el modal (p. ej. liberar bloqueo).
*/

let timerInterval = null;

function formatSeconds(s) {
    if (s <= 0) return '0s';
    const min = Math.floor(s / 60);
    const sec = s % 60;
    return (min > 0 ? `${min}m ` : '') + `${sec}s`;
}

export function iniciarContador(segundos, onExpire) {
    const modalTimer = document.getElementById("modal-timer");

    let restante = segundos;

    clearInterval(timerInterval);
    // Immediately set initial value so when modal opens it's visible
    if (modalTimer) modalTimer.textContent = `Tiempo restante: ${formatSeconds(restante)}`;

    timerInterval = setInterval(() => {
        restante--;
        if (modalTimer) modalTimer.textContent = `Tiempo restante: ${formatSeconds(restante)}`;

        if (restante <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            if (typeof onExpire === 'function') onExpire();
        }
    }, 1000);
}

export function detenerContador() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    const modalTimer = document.getElementById("modal-timer");
    if (modalTimer) modalTimer.textContent = '';
}

// Temporizador de sesión visible en la página (ej: 15 minutos)
let pageTimerInterval = null;
export function iniciarTemporizadorPagina(segundos, onExpire) {
    const el = document.getElementById('page-timer');
    let restante = segundos;
    // establecer valor inmediato
    if (el) el.textContent = `Tiempo restante: ${formatSeconds(restante)}`;

    if (pageTimerInterval) clearInterval(pageTimerInterval);
    pageTimerInterval = setInterval(() => {
        restante--;
        if (el) el.textContent = `Tiempo restante: ${formatSeconds(restante)}`;
        if (restante <= 0) {
            clearInterval(pageTimerInterval);
            pageTimerInterval = null;
            if (typeof onExpire === 'function') onExpire();
        }
    }, 1000);
}

export function detenerTemporizadorPagina() {
    if (pageTimerInterval) {
        clearInterval(pageTimerInterval);
        pageTimerInterval = null;
    }
    const el = document.getElementById('page-timer');
    if (el) el.textContent = '';
}

export function showModal(title, message, onConfirm = null) {
    const overlay = document.getElementById("modal-overlay");
    document.getElementById("modal-title").textContent = title;
    document.getElementById("modal-message").textContent = message;
    // leave modal-timer intact if a contador is active; otherwise clear
    const modalTimer = document.getElementById("modal-timer");
    if (modalTimer && (!modalTimer.textContent || modalTimer.textContent.trim() === '')) {
        modalTimer.textContent = '';
    }

    overlay.classList.remove("hidden");

    const confirmBtn = document.getElementById("modal-confirm");
    confirmBtn.style.display = onConfirm ? "inline-block" : "none";

    confirmBtn.onclick = () => {
        overlay.classList.add("hidden");
        if (onConfirm) onConfirm();
    };

    document.getElementById("modal-cancel").onclick = () => {
        overlay.classList.add("hidden");
        if (typeof modalCancelHandler === 'function') modalCancelHandler();
    };
}

// Hook para que usuarios del modal puedan recibir el evento de cancelar
let modalCancelHandler = null;
export function onModalCancel(fn) { modalCancelHandler = fn; }
