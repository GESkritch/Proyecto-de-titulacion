/**
 * public/js/index_login.js
 * Modal de acceso administrativo desde página pública
 * 
 * Propósito: Permite a administradores acceder al dashboard sin cambiar de página
 * - Genera dinámicamente un modal de login superpuesto
 * - Envía credenciales a php/login.php con autenticación de sesión
 * - Redirige a admin/dashboard.php al autenticar exitosamente
 * - Muestra errores inline sin recargar página
 * 
 * No modular: Script de inyección de UI que se ejecuta al cargar index.html
 * Funciones: Manejo de botón "Ingreso", validación de credenciales, redirección
 */

/*
    public/js/index_login.js
    - Controla el modal de ingreso a la zona administrativa desde la página pública.
    - Envía credenciales a `php/login.php` y redirige a `admin/dashboard.php` al autenticar.
*/

// Maneja el ingreso al panel admin desde la página pública
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnIngreso');
    if (!btn) return;

    // Crear modal simple dinámicamente
    const overlay = document.createElement('div');
    overlay.id = 'admin-login-overlay';
    overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:3000;';

    overlay.innerHTML = `
        <div style="background:#fff;padding:20px;border-radius:8px;width:320px;box-shadow:0 6px 18px rgba(0,0,0,0.25);">
            <h3 style="margin-top:0;color:#2c7a4b;text-align:center;">Ingreso Administrador</h3>
            <label>Usuario</label>
            <input id="adminUsuario" type="text" style="width:100%;padding:8px;margin-top:6px;border:1px solid #ccc;border-radius:4px;" />
            <label style="margin-top:8px;display:block;">Contraseña</label>
            <input id="adminPassword" type="password" style="width:100%;padding:8px;margin-top:6px;border:1px solid #ccc;border-radius:4px;" />
            <p id="adminLoginError" style="color:#d32f2f;margin:8px 0 0;display:none;"></p>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <button id="adminCancel" style="flex:1;padding:8px;border-radius:6px;border:none;background:#eee;cursor:pointer;">Cancelar</button>
                <button id="adminSubmit" style="flex:1;padding:8px;border-radius:6px;border:none;background:#2c7a4b;color:#fff;cursor:pointer;">Ingresar</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const usuario = () => document.getElementById('adminUsuario');
    const password = () => document.getElementById('adminPassword');
    const errorP = () => document.getElementById('adminLoginError');

    btn.addEventListener('click', () => {
        overlay.style.display = 'flex';
        errorP().style.display = 'none';
        usuario().value = '';
        password().value = '';
    });

    document.getElementById('adminCancel').addEventListener('click', () => {
        overlay.style.display = 'none';
    });

    document.getElementById('adminSubmit').addEventListener('click', async () => {
        const u = usuario().value.trim();
        const p = password().value.trim();
        if (!u || !p) {
            errorP().textContent = 'Ingrese usuario y contraseña';
            errorP().style.display = 'block';
            return;
        }

        try {
            const res = await fetch('php/login.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ usuario: u, password: p })
            });
            const j = await res.json();
            if (j.ok) {
                // Redirigir al dashboard admin
                window.location.href = 'admin/dashboard.php';
            } else {
                errorP().textContent = 'Usuario o contraseña incorrectos';
                errorP().style.display = 'block';
            }
        } catch (err) {
            errorP().textContent = 'Error de conexión';
            errorP().style.display = 'block';
        }
    });
});
