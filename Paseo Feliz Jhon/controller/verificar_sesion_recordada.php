<?php
/**
 * verificar_sesion_recordada.php
 * acceso.html es HTML estático (no PHP), así que no puede revisar la
 * sesión por su cuenta. Esta es la llamada que hace acceso.js apenas
 * carga la página: si hay sesión de PHP activa o una cookie "recordarme"
 * válida, devuelve a dónde redirigir SIN mostrar el formulario de login.
 */
header("Content-Type: application/json; charset=UTF-8");
session_start();

include_once '../model/conexion.php';
include_once '../model/sesion_recordada.php';

$activa = intentarRestaurarSesion($conn);

if (!$activa) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode([
    'success'    => true,
    'esAdmin'    => !empty($_SESSION['usuario_admin']),
    'esPaseador' => !empty($_SESSION['es_paseador']),
    'nombre'     => $_SESSION['usuario_nombre'] ?? '',
]);
?>
