<?php
/**
 * archivo: proteger.php
 * función: Verifica que exista una sesión admin activa.
 * - Si no hay sesión, redirige a inicio público.
 * - Usado por dashboard.php y otros archivos que requieren autenticación.
 */
session_start();

if (!isset($_SESSION["admin"])) {
  // Si no hay sesión, redirigir al inicio público
  header("Location: ../index.html");
  exit;
}

$admin = $_SESSION["admin"];
