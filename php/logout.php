<?php
/**
 * archivo: logout.php
 * función: Cierra la sesión del administrador autenticado.
 * - Destruye la sesión y redirige al inicio público.
 */
session_start();
session_destroy();
// Redirigir al inicio público después de cerrar sesión
header("Location: ../index.html");
