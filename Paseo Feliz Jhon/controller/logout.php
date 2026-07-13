<?php
session_start();

require_once __DIR__ . '/../model/conexion.php';
require_once __DIR__ . '/../model/sesion_recordada.php';
borrarSesionRecordada($conn); // cierre explícito: revoca también el "recordarme"

session_unset();    // Remueve las variables de sesión del usuario
session_destroy();  // Destruye la sesión en el servidor

// Redirige al login de inmediato
header("Location: ../view/registro/acceso.html");
exit();
?>