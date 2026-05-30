<?php
session_start();
session_unset();    // Remueve las variables de sesión del usuario
session_destroy();  // Destruye la sesión en el servidor

// Redirige al login de inmediato
header("Location: ../view/registro/acceso.html");
exit();
?>