<?php
// Configuración de la base de datos para PostgreSQL (pgAdmin 4)
$host = 'localhost';
$port = '5432'; 
$db   = 'paseo_feliz'; 
$user = 'Usuarios_Paseo_Feliz'; 
$pass = 'feliz';      

// Configuración de opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

$dsn = "pgsql:host=$host;port=$port;dbname=$db";

try {
    // Creamos la instancia de la conexión
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si hay un error, se muestra un mensaje de error y se detiene la ejecución
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>