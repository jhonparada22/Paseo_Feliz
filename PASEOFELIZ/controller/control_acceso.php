<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$url_actual = $_SERVER['REQUEST_URI'];

// 1. Si no está logueado, redirigir al login
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    if (strpos($url_actual, '/sub_menu/') !== false) {
        header("Location: ../../registro/acceso.html");
    } else {
        header("Location: ../registro/acceso.html");
    }
    exit();
}

// 2. Si está en una ruta de admin pero NO es admin, redirigir al inicio de usuario normal
if (!isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
    // Detectar si está intentando acceder a una ruta de administrador
    if (strpos($url_actual, '/vista/admin/') !== false || strpos($url_actual, '/admin/') !== false) {
        header("Location: ../../registro/acceso.html");
        exit();
    }
}
?>
