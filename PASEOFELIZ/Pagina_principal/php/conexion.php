<?php
// Configuración de la base de datos para XAMPP
$host = 'localhost';
$db   = 'paseo_feliz'; // El nombre que pusiste en phpMyAdmin
$user = 'root';        // Usuario por defecto de XAMPP
$pass = '';            // Contraseña por defecto (vacía)
$charset = 'utf8mb4';  // Importante para aceptar tildes y emojis en el chat

// Configuración de opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza errores si algo falla
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los datos como arreglos asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva emulación para mayor seguridad
];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    // Creamos la instancia de la conexión
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si hay un error, lo atrapamos y mostramos qué pasó
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>