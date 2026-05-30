<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si el usuario NO tiene un pase de entrada válido, lo expulsamos
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    
    $url_actual = $_SERVER['REQUEST_URI'];

    // Si está intentando ver un archivo dentro de la carpeta "sub_menu"
    if (strpos($url_actual, '/sub_menu/') !== false) {
        // Sube dos niveles para salir a la raíz y entra a la vista de login
        header("Location: ../../registro/acceso.html");
    } else {
        // Si está en la carpeta de páginas principales, solo sube un nivel
        header("Location: ../registro/acceso.html");
    }
    
    // Cortamos la carga del HTML inmediatamente por seguridad
    exit();
}
?>