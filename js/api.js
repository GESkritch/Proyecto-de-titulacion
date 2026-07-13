/**
 * public/js/api.js
 * Wrappers HTTP para consumir endpoints PHP del backend
 * 
 * Funciones:
 * - obtenerAgenda(): GET php/obtener_agenda.php → { 'fecha': ['hora1', 'hora2', ...], ... }
 * - guardarAgenda(data): POST php/guardar_agenda.php → { ok, id } (guarda agendamiento confirmado)
 * - obtenerDisponibilidad(): GET php/obtener_disponibilidad.php → { 'fecha': { inicio, fin, bloque_min, cupos, tipo }, ... }
 * - obtenerBloqueos(): GET php/obtener_bloqueos.php → { 'fecha': ['hora1', 'hora2', ...], ... }
 * - crearBloqueo(data): POST php/crear_bloqueo.php → { ok } (bloqueo temporal TTL 15 min)
 * - liberarBloqueo(data): POST php/liberar_bloqueo.php → { ok } (libera bloqueo previo)
 * 
 * Manejo de errores: Lanza excepciones si el endpoint no responde OK.
 */

export async function obtenerAgenda() {
    const res = await fetch("php/obtener_agenda.php");
    if (!res.ok) throw new Error("Error al obtener agenda");
    return await res.json();
}

export async function guardarAgenda(data) {
    const res = await fetch("php/guardar_agenda.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    return await res.json();
}

export async function obtenerDisponibilidad() {
    const res = await fetch("php/obtener_disponibilidad.php");
    if (!res.ok) throw new Error("Error al obtener disponibilidad");
    return await res.json();
}

export async function obtenerBloqueos() {
    const res = await fetch("php/obtener_bloqueos.php");
    return await res.json();
}

export async function crearBloqueo(data) {
    const res = await fetch("php/crear_bloqueo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    return await res.json();
}

export async function liberarBloqueo(data) {
    const res = await fetch("php/liberar_bloqueo.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    return await res.json();
}
